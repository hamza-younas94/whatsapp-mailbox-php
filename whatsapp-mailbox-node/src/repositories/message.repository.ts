// src/repositories/message.repository.ts
// Message data access layer

import { PrismaClient, Message, Prisma } from '@prisma/client';
import { BaseRepository } from './base.repository';

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

export interface IMessageRepository {
  findById(id: string): Promise<Message | null>;
  findByConversation(
    conversationId: string,
    filters?: Omit<MessageFilters, 'conversationId'>,
  ): Promise<PaginatedResult<Message>>;
  findByContact(
    userId: string,
    contactId: string,
    filters?: MessageFilters,
  ): Promise<PaginatedResult<Message>>;
  create(data: Prisma.MessageCreateInput): Promise<Message>;
  update(id: string, data: Prisma.MessageUpdateInput): Promise<Message>;
  delete(id: string): Promise<Message>;
  findByWaMessageId(waMessageId: string): Promise<Message | null>;
  updateStatus(id: string, status: string): Promise<Message>;
  getUnreadCount(conversationId: string): Promise<number>;
}

export class MessageRepository extends BaseRepository<Message> implements IMessageRepository {
  protected modelName = 'message' as const;

  constructor(prisma: PrismaClient) {
    super(prisma);
  }

  async findByConversation(
    conversationId: string,
    filters?: Omit<MessageFilters, 'conversationId'>,
  ): Promise<PaginatedResult<Message>> {
    const limit = Math.min(filters?.limit || 50, 100);
    const offset = filters?.offset || 0;

    const where: Prisma.MessageWhereInput = {
      conversationId,
      ...(filters.status && { status: filters.status as any }),
      ...(filters?.direction && { direction: filters.direction as any }),
      ...(filters?.startDate && { createdAt: { gte: filters.startDate } }),
      ...(filters?.endDate && { createdAt: { lte: filters.endDate } }),
    };

    const [messages, total] = await Promise.all([
      this.prisma.message.findMany({
        where,
        skip: offset,
        take: limit,
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.message.count({ where }),
    ]);

    return {
      data: messages,
      total,
      page: Math.floor(offset / limit) + 1,
      limit: limit,
    };
  }

  async findByContact(
    userId: string,
    contactId: string,
    filters?: MessageFilters,
  ): Promise<PaginatedResult<Message>> {
    const limit = Math.min(filters?.limit || 50, 100);
    const offset = filters?.offset || 0;

    const where: Prisma.MessageWhereInput = {
      userId,
      contactId,
      ...(filters.status && { status: filters.status as any }),
      ...(filters?.direction && { direction: filters.direction as any }),
    };

    const [messages, total] = await Promise.all([
      this.prisma.message.findMany({
        where,
        skip: offset,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: { conversation: true },
      }),
      this.prisma.message.count({ where }),
    ]);

    return {
      data: messages,
      total,
      page: Math.floor(offset / limit) + 1,
      limit: limit,
    };
  }

  async findByWaMessageId(waMessageId: string): Promise<Message | null> {
    return this.prisma.message.findUnique({
      where: { waMessageId },
    });
  }

  async updateStatus(id: string, status: string): Promise<Message> {
    return this.prisma.message.update({
      where: { id },
      data: { status: status as any },
    });
  }

  async getUnreadCount(conversationId: string): Promise<number> {
    return this.prisma.message.count({
      where: {
        conversationId,
        direction: 'INCOMING',
        status: 'RECEIVED',
      },
    });
  }
}
