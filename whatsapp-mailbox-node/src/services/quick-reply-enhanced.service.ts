import { PrismaClient } from '@prisma/client';
import { AppError } from '../utils/errors';
import { logger } from '../utils/logger';

export interface CreateQuickReplyDto {
  title: string;
  content: string;
  categoryId?: string;
  shortcut?: string;
  variables?: any[];
  mediaUrl?: string;
  mediaType?: string;
  tags?: string[];
}

export interface UpdateQuickReplyDto extends Partial<CreateQuickReplyDto> {}

export class QuickReplyEnhancedService {
  constructor(private prisma: PrismaClient) {}

  async create(userId: string, data: CreateQuickReplyDto) {
    try {
      return await this.prisma.quickReply.create({
        data: {
          userId,
          title: data.title,
          content: data.content,
          categoryId: data.categoryId,
          shortcut: data.shortcut,
          variables: data.variables || [],
          mediaUrl: data.mediaUrl,
          mediaType: data.mediaType,
          tags: data.tags || [],
          isActive: true,
        },
        include: {
          quickReplyCategory: true,
        },
      });
    } catch (error) {
      logger.error({ error, data }, 'Failed to create quick reply');
      throw new AppError('Failed to create quick reply', 'CREATE_FAILED', 500);
    }
  }

  async update(id: string, userId: string, data: UpdateQuickReplyDto) {
    try {
      const quickReply = await this.prisma.quickReply.findFirst({
        where: { id, userId },
      });

      if (!quickReply) {
        throw new AppError('Quick reply not found', 'NOT_FOUND', 404);
      }

      return await this.prisma.quickReply.update({
        where: { id },
        data: {
          ...data,
          updatedAt: new Date(),
        },
        include: {
          quickReplyCategory: true,
        },
      });
    } catch (error) {
      logger.error({ error, id, data }, 'Failed to update quick reply');
      if (error instanceof AppError) throw error;
      throw new AppError('Failed to update quick reply', 'UPDATE_FAILED', 500);
    }
  }

  async delete(id: string, userId: string) {
    try {
      const quickReply = await this.prisma.quickReply.findFirst({
        where: { id, userId },
      });

      if (!quickReply) {
        throw new AppError('Quick reply not found', 'NOT_FOUND', 404);
      }

      await this.prisma.quickReply.delete({ where: { id } });
      return { success: true };
    } catch (error) {
      logger.error({ error, id }, 'Failed to delete quick reply');
      if (error instanceof AppError) throw error;
      throw new AppError('Failed to delete quick reply', 'DELETE_FAILED', 500);
    }
  }

  async findAllWithCategories(userId: string) {
    try {
      const [quickReplies, categories] = await Promise.all([
        this.prisma.quickReply.findMany({
          where: { userId, isActive: true },
          include: { quickReplyCategory: true },
          orderBy: [{ usageCount: 'desc' }, { createdAt: 'desc' }],
        }),
        this.prisma.quickReplyCategory.findMany({
          where: { userId },
          orderBy: { sortOrder: 'asc' },
        }),
      ]);

      return { quickReplies, categories };
    } catch (error) {
      logger.error({ error, userId }, 'Failed to fetch quick replies');
      throw new AppError('Failed to fetch quick replies', 'FETCH_FAILED', 500);
    }
  }

  async trackUsage(quickReplyId: string, userId: string, contactId?: string) {
    try {
      await Promise.all([
        this.prisma.quickReply.update({
          where: { id: quickReplyId },
          data: {
            usageCount: { increment: 1 },
            usageTodayCount: { increment: 1 },
            lastUsedAt: new Date(),
          },
        }),
        this.prisma.quickReplyUsage.create({
          data: { quickReplyId, userId, contactId },
        }),
      ]);
    } catch (error) {
      logger.error({ error, quickReplyId }, 'Failed to track quick reply usage');
      // Don't throw - usage tracking failure shouldn't break the flow
    }
  }

  async getAnalytics(userId: string, startDate: Date, endDate: Date) {
    try {
      const usage = await this.prisma.quickReplyUsage.groupBy({
        by: ['quickReplyId'],
        where: {
          userId,
          usedAt: { gte: startDate, lte: endDate },
        },
        _count: { id: true },
      });

      const quickReplyIds = usage.map((u) => u.quickReplyId);
      const quickReplies = await this.prisma.quickReply.findMany({
        where: { id: { in: quickReplyIds } },
        select: { id: true, title: true, shortcut: true, categoryId: true },
      });

      return usage.map((u) => ({
        ...quickReplies.find((qr) => qr.id === u.quickReplyId),
        usageCount: u._count.id,
      }));
    } catch (error) {
      logger.error({ error, userId }, 'Failed to get quick reply analytics');
      throw new AppError('Failed to get analytics', 'ANALYTICS_FAILED', 500);
    }
  }

  // Category management
  async createCategory(userId: string, data: { name: string; description?: string; icon?: string; color?: string }) {
    try {
      return await this.prisma.quickReplyCategory.create({
        data: {
          userId,
          ...data,
        },
      });
    } catch (error) {
      logger.error({ error, data }, 'Failed to create category');
      throw new AppError('Failed to create category', 'CREATE_FAILED', 500);
    }
  }

  async updateCategory(id: string, userId: string, data: Partial<{ name: string; description?: string; icon?: string; color?: string }>) {
    try {
      const category = await this.prisma.quickReplyCategory.findFirst({
        where: { id, userId },
      });

      if (!category) {
        throw new AppError('Category not found', 'NOT_FOUND', 404);
      }

      return await this.prisma.quickReplyCategory.update({
        where: { id },
        data,
      });
    } catch (error) {
      logger.error({ error, id }, 'Failed to update category');
      if (error instanceof AppError) throw error;
      throw new AppError('Failed to update category', 'UPDATE_FAILED', 500);
    }
  }

  async deleteCategory(id: string, userId: string) {
    try {
      const category = await this.prisma.quickReplyCategory.findFirst({
        where: { id, userId },
      });

      if (!category) {
        throw new AppError('Category not found', 'NOT_FOUND', 404);
      }

      // Set categoryId to null for all quick replies in this category
      await this.prisma.quickReply.updateMany({
        where: { categoryId: id },
        data: { categoryId: null },
      });

      await this.prisma.quickReplyCategory.delete({ where: { id } });
      return { success: true };
    } catch (error) {
      logger.error({ error, id }, 'Failed to delete category');
      if (error instanceof AppError) throw error;
      throw new AppError('Failed to delete category', 'DELETE_FAILED', 500);
    }
  }
}
