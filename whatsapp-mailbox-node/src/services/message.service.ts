// src/services/message.service.ts
// Message business logic - Single Responsibility Principle

import { Message, Prisma, MessageType, MessageDirection, MessageStatus } from '@prisma/client';
import { MessageMedia } from 'whatsapp-web.js';
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
      const withTimeout = async <T>(promise: Promise<T>, ms: number, errorMsg: string): Promise<T> => {
        let timer: NodeJS.Timeout;
        try {
          return await Promise.race([
            promise,
            new Promise<T>((_, reject) => {
              timer = setTimeout(() => reject(new Error(errorMsg)), ms);
            }),
          ]);
        } finally {
          clearTimeout(timer!);
        }
      };

      const sanitizePhone = (raw: string): string => {
        if (!raw) return '';
        // Remove @domain if present (WhatsApp format)
        const base = raw.split('@')[0];
        // Remove all non-digit characters
        const digits = base.replace(/\D/g, '');
        // Return last 20 digits (max phone number length)
        // This handles numbers starting with 0, +, or country codes
        return digits.slice(-20);
      };

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
        const sanitizedPhone = sanitizePhone(input.phoneNumber);
        if (!sanitizedPhone || sanitizedPhone.length < 10) {
          throw new ValidationError(`Phone number is invalid. Got: "${input.phoneNumber}", sanitized: "${sanitizedPhone}". Please provide a valid phone number with at least 10 digits.`);
        }
        const contact = await this.contactRepository.findOrCreate(userId, sanitizedPhone, { name: sanitizedPhone });
        contactId = contact.id;
      }

      if (!contactId) {
        throw new ValidationError('contactId or phoneNumber is required');
      }

      // Ensure conversation exists
      const conversation = await this.conversationRepository.findOrCreate(userId, contactId);

      // Create message in database (PENDING status)
      const messageTypeToUse = input.messageType || (input.mediaUrl ? MessageType.DOCUMENT : MessageType.TEXT);

      const message = await this.messageRepository.create({
        user: { connect: { id: userId } },
        contact: { connect: { id: contactId } },
        conversation: { connect: { id: conversation.id } },
        content: input.content,
        messageType: messageTypeToUse as any,
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

        // Get phone number - either from input or from contact
        let phoneNumberToUse = input.phoneNumber;
        if (!phoneNumberToUse && contactId) {
          const contact = await this.contactRepository.findById(contactId);
          if (!contact) {
            throw new ValidationError('Contact not found');
          }
          phoneNumberToUse = contact.phoneNumber;
        }

        if (!phoneNumberToUse) {
          throw new ValidationError('Phone number is required to send message');
        }

        // Format phone number
        const sanitizedPhone = sanitizePhone(phoneNumberToUse);
        if (!sanitizedPhone || sanitizedPhone.length < 10) {
          throw new ValidationError(`Phone number is invalid after sanitization. Got: "${phoneNumberToUse}", sanitized: "${sanitizedPhone}"`);
        }
        const formattedNumber = sanitizedPhone;
        const chatId = `${formattedNumber}@c.us`;

        logger.info({ chatId, content: input.content.substring(0, 50) }, 'Sending WhatsApp message');

        // Verify number is registered and get numberId
        const numberId = await withTimeout(
          activeSession.client.getNumberId(formattedNumber),
          30000,
          'WhatsApp getNumberId timed out',
        );
        
        if (!numberId) {
          throw new ValidationError('Phone number is not registered on WhatsApp');
        }

        // Log NumberId structure for debugging
        logger.info({ 
          numberIdString: typeof numberId === 'string' ? numberId : numberId._serialized,
          type: typeof numberId,
          isObject: typeof numberId === 'object'
        }, 'Number verified, sending message');

        // Normalize target chat id for sendMessage
        const targetChatId = typeof numberId === 'object' ? numberId._serialized : numberId;
        
        let waMessage: any;

        if (input.mediaUrl) {
          const media = await withTimeout(
            MessageMedia.fromUrl(input.mediaUrl),
            30000,
            'WhatsApp media fetch timed out',
          );

          waMessage = await withTimeout(
            activeSession.client.sendMessage(targetChatId as any, media, { caption: input.content || '' }),
            30000,
            'WhatsApp send timed out',
          );
        } else {
          // Ensure content is not empty
          if (!input.content || input.content.trim().length === 0) {
            throw new ValidationError('Message content cannot be empty');
          }
          
          waMessage = await withTimeout(
            activeSession.client.sendMessage(targetChatId as any, input.content.trim()),
            30000,
            'WhatsApp send timed out',
          );
        }
        logger.info({ messageId: waMessage.id.id }, 'WhatsApp message sent successfully');

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

        // Better error message extraction
        let errorMessage = 'Unknown error';
        try {
          if (error instanceof Error) {
            errorMessage = error.message || error.name || error.toString();
            // Check for nested error properties
            if ((error as any).description) {
              errorMessage = (error as any).description;
            } else if ((error as any).text) {
              errorMessage = (error as any).text;
            } else if ((error as any).error) {
              errorMessage = String((error as any).error);
            }
          } else if (typeof error === 'string') {
            errorMessage = error;
          } else if (error && typeof error === 'object') {
            // Try to extract meaningful error message from object
            const errObj = error as any;
            errorMessage = errObj.message || errObj.error || errObj.description || errObj.text || JSON.stringify(error);
          }
        } catch (parseError) {
          errorMessage = String(error);
        }
        
        logger.error({ 
          error, 
          errorType: typeof error,
          errorConstructor: error?.constructor?.name,
          errorStack: error instanceof Error ? error.stack : undefined,
          phoneNumber: input.phoneNumber || 'N/A',
          contactId: input.contactId || 'N/A',
          errorMessage 
        }, 'WhatsApp Web send failed');
        
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

  async getMessagesByContact(
    userId: string,
    contactId: string,
    limit?: number,
    offset?: number,
  ): Promise<PaginatedResult<Message>> {
    try {
      const contact = await this.contactRepository.findById(contactId);
      if (!contact || contact.userId !== userId) {
        throw new NotFoundError('Contact');
      }

      const conversation = await this.conversationRepository.findOrCreate(userId, contactId);

      return await this.messageRepository.findByConversation(conversation.id, {
        limit,
        offset,
      });
    } catch (error) {
      logger.error({ contactId, error }, 'Failed to get messages by contact');
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
