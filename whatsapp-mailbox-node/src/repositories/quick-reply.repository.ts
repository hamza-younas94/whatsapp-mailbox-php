// src/repositories/quick-reply.repository.ts
// Quick reply data access

import { PrismaClient, QuickReply, Prisma } from '@prisma/client';
import { BaseRepository } from './base.repository';

export interface IQuickReplyRepository {
  findByUserId(userId: string): Promise<QuickReply[]>;
  findByShortcut(userId: string, shortcut: string): Promise<QuickReply | null>;
  search(userId: string, query: string): Promise<QuickReply[]>;
}

export class QuickReplyRepository extends BaseRepository<QuickReply> implements IQuickReplyRepository {
  protected modelName = 'quickReply' as const;

  constructor(prisma: PrismaClient) {
    super(prisma);
  }

  async findByUserId(userId: string): Promise<QuickReply[]> {
    return this.prisma.quickReply.findMany({
      where: { userId },
      orderBy: { createdAt: 'desc' },
    });
  }

  async findByShortcut(userId: string, shortcut: string): Promise<QuickReply | null> {
    return this.prisma.quickReply.findFirst({
      where: { userId, shortcut },
    });
  }

  async search(userId: string, query: string): Promise<QuickReply[]> {
    return this.prisma.quickReply.findMany({
      where: {
        userId,
        OR: [
          { title: { contains: query, mode: 'insensitive' } },
          { content: { contains: query, mode: 'insensitive' } },
        ],
      },
      take: 20,
    });
  }
}
