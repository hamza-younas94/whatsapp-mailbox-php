// src/services/message.service.ts
// Message business logic - Single Responsibility Principle

import { Message, Prisma, MessageType, MessageDirection, MessageStatus } from '@prisma/client';
import { MessageRepository } from '@repositories/message.repository';
import { NotFoundError, ValidationError, ExternalServiceError } from '@utils/errors';
import logger from '@utils/logger';
import { WhatsAppService } from './whatsapp.service';

interface CreateMessageInput {
  contactId: string;
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
  markAsRead(messageId: string): Promise<Message>;
  deleteMessage(messageId: string): Promise<void>;
}

export class MessageService implements IMessageService {
  constructor(
    private messageRepository: MessageRepository,
    private whatsAppService: WhatsAppService,
  ) {}

  async sendMessage(userId: string, input: CreateMessageInput): Promise<Message> {
    try {
      // Validate input
      if (!input.content && !input.mediaUrl) {
        throw new ValidationError('Message must have content or media');
      }

      if (input.content && input.content.length > 4096) {
        throw new ValidationError('Message content exceeds 4096 characters');
      }

      // Create message in database (PENDING status)
      const message = await this.messageRepository.create({
        userId,
        contactId: input.contactId,
        conversationId: input.contactId, // Simplified for demo
        content: input.content,
        messageType: (input.messageType || MessageType.TEXT) as any,
        direction: MessageDirection.OUTGOING as any,
        status: MessageStatus.PENDING as any,
        mediaUrl: input.mediaUrl,
        mediaType: input.mediaType?.toString(),
      } as Prisma.MessageCreateInput);

      // Send via WhatsApp API
      try {
        const waResponse = await this.whatsAppService.sendMessage(
          input.contactId,
          input.content,
          input.mediaUrl,
        );

        // Update message with WhatsApp message ID
        return await this.messageRepository.update(message.id, {
          waMessageId: waResponse.messageId,
          status: MessageStatus.SENT,
        });
      } catch (error) {
        // Mark message as failed if WhatsApp API fails
        await this.messageRepository.update(message.id, {
          status: MessageStatus.FAILED,
        });

        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
        throw new ExternalServiceError('WhatsApp API', errorMessage);
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
}
