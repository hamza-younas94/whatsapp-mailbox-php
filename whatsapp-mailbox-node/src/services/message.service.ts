// src/services/message.service.ts
// Message business logic - Single Responsibility Principle

import { Message, Prisma, MessageType, MessageDirection, MessageStatus } from '@prisma/client';
import { MessageRepository } from '@repositories/message.repository';
import { ContactRepository } from '@repositories/contact.repository';
import { ConversationRepository } from '@repositories/conversation.repository';
import { NotFoundError, ValidationError, ExternalServiceError } from '@utils/errors';
import logger from '@utils/logger';
import { WhatsAppService } from './whatsapp.service';
import { whatsappWebService } from './whatsapp-web.service';

interface CreateMessageInput {
  phoneNumber?: string;
  contactId?: string;
  content: string;
  messageType?: MessageType;
  direction?: MessageDirection;
  status?: MessageStatus;
  mediaUrl?: string;
  mediaType?: string;
}

interface MessageFilters {
  query?: string;
  status?: string;
  messageType?: string;
  direction?: string;
  startDate?: Date;
  endDate?: Date;
  limit?: number;
  offset?: number;
}

interface PaginatedResult<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface IMessageService {
  sendMessage(userId: string, input: CreateMessageInput): Promise<Message>;
  receiveMessage(waMessageId: string, payload: unknown): Promise<Message>;
  getMessages(
    userId: string,
    conversationId: string,
    limit?: number,
    offset?: number,
  ): Promise<PaginatedResult<Message>>;
  listMessages(userId: string, filters: MessageFilters): Promise<PaginatedResult<Message>>;
  markAsRead(messageId: string): Promise<Message>;
  deleteMessage(messageId: string): Promise<void>;
}

export class MessageService implements IMessageService {
  constructor(
    private messageRepository: MessageRepository,
    private whatsAppService: WhatsAppService,
    private contactRepository: ContactRepository,
    private conversationRepository: ConversationRepository,
  ) {}

  async sendMessage(userId: string, input: CreateMessageInput): Promise<Message> {
    try {
      // Validate userId is present
      if (!userId) {
        throw new ValidationError('User ID is required - authentication failed');
      }

      // Validate input
      if (!input.content && !input.mediaUrl) {
        throw new ValidationError('Message must have content or media');
      }

      if (input.content && input.content.length > 4096) {
        throw new ValidationError('Message content exceeds 4096 characters');
      }

      // Resolve contactId (create if phoneNumber provided)
      let contactId = input.contactId;
      if (!contactId && input.phoneNumber) {
        const contact = await this.contactRepository.findOrCreate(userId, input.phoneNumber, { name: input.phoneNumber });
        contactId = contact.id;
      }

      if (!contactId) {
        throw new ValidationError('contactId or phoneNumber is required');
      }

      // Ensure conversation exists
      const conversation = await this.conversationRepository.findOrCreate(userId, contactId);

      // Create message in database (PENDING status)
      const message = await this.messageRepository.create({
        user: { connect: { id: userId } },
        contact: { connect: { id: contactId } },
        conversation: { connect: { id: conversation.id } },
        content: input.content,
        messageType: (input.messageType || MessageType.TEXT) as any,
        direction: MessageDirection.OUTGOING as any,
        status: MessageStatus.PENDING as any,
        mediaUrl: input.mediaUrl,
        mediaType: input.mediaType?.toString(),
      } as Prisma.MessageCreateInput);

      // Send via WhatsApp Web
      try {
        // Get user's active WhatsApp Web session
        const sessions = whatsappWebService.getUserSessions(userId);
        
        logger.info({ 
          userId, 
          totalSessions: sessions.length,
          sessionStatuses: sessions.map(s => ({ id: s.id, status: s.status }))
        }, 'Checking WhatsApp Web sessions');

        const activeSession = sessions.find((s) => s.status === 'READY' || s.status === 'AUTHENTICATED');

        if (!activeSession) {
          const statusList = sessions.map(s => s.status).join(', ') || 'none';
          throw new ValidationError(`WhatsApp is not connected (statuses: ${statusList}). Please scan the QR code to connect.`);
        }

        // Check if client is actually ready
        const state = await activeSession.client.getState();
        logger.info({ state, sessionId: activeSession.id }, 'WhatsApp Web client state');

        if (state !== 'CONNECTED') {
          throw new ValidationError(`WhatsApp client is ${state}. Please reconnect.`);
        }

        // Format phone number
        const phoneNumber = input.phoneNumber || '';
        const formattedNumber = phoneNumber.replace(/[^0-9]/g, ''); // Remove non-digits
        const chatId = `${formattedNumber}@c.us`;

        logger.info({ chatId, content: input.content.substring(0, 50) }, 'Sending WhatsApp message');

        // Get all chats and search for existing chat first
        const chats = await activeSession.client.getChats();
        const existingChat = chats.find(c => c.id._serialized === chatId);
        
        if (existingChat) {
          logger.info({ chatId }, 'Found existing chat, sending via chat object');
          const waMessage = await existingChat.sendMessage(input.content);
          logger.info({ messageId: waMessage.id.id }, 'Message sent via existing chat');
          return await this.messageRepository.update(message.id, {
            waMessageId: waMessage.id.id,
            status: MessageStatus.SENT,
          });
        }

        // Fallback: verify number exists and send to new chat
        logger.info({ chatId }, 'No existing chat found, verifying number');
        const numberId = await activeSession.client.getNumberId(formattedNumber);
        if (!numberId) {
          throw new ValidationError('Phone number is not registered on WhatsApp');
        }

        const waMessage = await activeSession.client.sendMessage(chatId, input.content);
        logger.info({ messageId: waMessage.id.id, to: chatId }, 'WhatsApp message sent successfully');

        // Update message with WhatsApp message ID
        return await this.messageRepository.update(message.id, {
          waMessageId: waMessage.id.id,
          status: MessageStatus.SENT,
        });
      } catch (error) {
        // Mark message as failed if WhatsApp Web fails
        await this.messageRepository.update(message.id, {
          status: MessageStatus.FAILED,
        });

        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
        logger.error({ error, phoneNumber: input.phoneNumber, errorMessage }, 'WhatsApp Web send failed');
        throw new ExternalServiceError('WhatsApp Web', errorMessage);
      }
    } catch (error) {
      logger.error({ input, error }, 'Failed to send message');
      throw error;
    }
  }

  async receiveMessage(waMessageId: string, payload: any): Promise<Message> {
    try {
      // Check if message already exists
      const existing = await this.messageRepository.findByWaMessageId(waMessageId);
      if (existing) {
        return existing;
      }

      // Create new message
      const message = await this.messageRepository.create({
        waMessageId,
        user: { connect: { id: payload.userId } },
        contact: { connect: { id: payload.contactId } },
        conversation: { connect: { id: payload.conversationId } },
        content: payload.content,
        messageType: MessageType.TEXT,
        direction: MessageDirection.INCOMING,
        status: MessageStatus.RECEIVED,
      } as any);

      logger.info({ messageId: message.id }, 'Message received');
      return message;
    } catch (error) {
      logger.error({ waMessageId, payload, error }, 'Failed to receive message');
      throw error;
    }
  }

  async getMessages(
    userId: string,
    conversationId: string,
    limit?: number,
    offset?: number,
  ): Promise<PaginatedResult<Message>> {
    try {
      // Verify user owns conversation (authorization)
      // ... additional check needed

      return await this.messageRepository.findByConversation(conversationId, {
        limit,
        offset,
      });
    } catch (error) {
      logger.error({ conversationId, error }, 'Failed to get messages');
      throw error;
    }
  }

  async markAsRead(messageId: string): Promise<Message> {
    try {
      const message = await this.messageRepository.findById(messageId);
      if (!message) {
        throw new NotFoundError('Message');
      }

      return await this.messageRepository.update(messageId, {
        status: MessageStatus.READ,
        readAt: new Date(),
      });
    } catch (error) {
      logger.error({ messageId, error }, 'Failed to mark message as read');
      throw error;
    }
  }

  async deleteMessage(messageId: string): Promise<void> {
    try {
      const message = await this.messageRepository.findById(messageId);
      if (!message) {
        throw new NotFoundError('Message');
      }

      // Can only delete outgoing messages
      if (message.direction !== 'OUTGOING') {
        throw new ValidationError('Can only delete outgoing messages');
      }

      await this.messageRepository.delete(messageId);
      logger.info({ messageId }, 'Message deleted');
    } catch (error) {
      logger.error({ messageId, error }, 'Failed to delete message');
      throw error;
    }
  }

  async listMessages(userId: string, filters: MessageFilters): Promise<PaginatedResult<Message>> {
    try {
      return await this.messageRepository.findByUser(userId, filters);
    } catch (error) {
      logger.error({ userId, filters, error }, 'Failed to list messages');
      throw error;
    }
  }
}
