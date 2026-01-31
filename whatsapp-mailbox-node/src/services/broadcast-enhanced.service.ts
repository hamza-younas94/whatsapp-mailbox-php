// src/services/broadcast-enhanced.service.ts
// Enhanced Broadcast service with full campaign management

import { PrismaClient, BroadcastStatus, BroadcastPriority, MessageType } from '@prisma/client';
import { AppError } from '@utils/errors';
import logger from '@utils/logger';

interface CreateBroadcastData {
  name: string;
  messageContent: string;
  messageType: MessageType;
  mediaUrl?: string;
  scheduledFor?: Date;
  priority?: BroadcastPriority;
  recipientType: 'ALL' | 'SEGMENT' | 'TAG' | 'MANUAL';
  segmentIds?: string[];
  tagIds?: string[];
  contactIds?: string[];
}

interface UpdateBroadcastData {
  name?: string;
  messageContent?: string;
  messageType?: MessageType;
  mediaUrl?: string;
  scheduledFor?: Date;
  priority?: BroadcastPriority;
  status?: BroadcastStatus;
}

export class BroadcastEnhancedService {
  constructor(private prisma: PrismaClient) {}

  async create(userId: string, data: CreateBroadcastData) {
    try {
      // Get recipients based on type
      const recipients = await this.getRecipients(userId, data);

      if (recipients.length === 0) {
        throw new AppError('No recipients found for broadcast', 400, 'NO_RECIPIENTS');
      }

      // Create broadcast
      const broadcast = await this.prisma.broadcast.create({
        data: {
          name: data.name,
          messageContent: data.messageContent,
          messageType: data.messageType,
          mediaUrl: data.mediaUrl,
          scheduledFor: data.scheduledFor || new Date(),
          priority: data.priority || 'MEDIUM',
          status: data.scheduledFor && data.scheduledFor > new Date() ? 'SCHEDULED' : 'DRAFT',
          totalRecipients: recipients.length,
          userId,
        },
      });

      // Create recipient records
      await this.prisma.broadcastRecipient.createMany({
        data: recipients.map(contactId => ({
          broadcastId: broadcast.id,
          contactId,
          status: 'PENDING',
        })),
      });

      logger.info(`Broadcast created: ${broadcast.id} with ${recipients.length} recipients`);
      
      return this.findById(broadcast.id);
    } catch (error) {
      logger.error('Error creating broadcast:', error);
      throw error;
    }
  }

  async findAll(userId: string, filters?: { status?: BroadcastStatus; limit?: number }) {
    const where: any = { userId };
    
    if (filters?.status) {
      where.status = filters.status;
    }

    const broadcasts = await this.prisma.broadcast.findMany({
      where,
      orderBy: { createdAt: 'desc' },
      take: filters?.limit || 50,
      include: {
        _count: {
          select: {
            recipients: true,
          },
        },
      },
    });

    return broadcasts;
  }

  async findById(id: string) {
    const broadcast = await this.prisma.broadcast.findUnique({
      where: { id },
      include: {
        user: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
        recipients: {
          include: {
            contact: {
              select: {
                id: true,
                phoneNumber: true,
                name: true,
              },
            },
          },
        },
      },
    });

    if (!broadcast) {
      throw new AppError('Broadcast not found', 404, 'NOT_FOUND');
    }

    return broadcast;
  }

  async update(id: string, data: UpdateBroadcastData) {
    const broadcast = await this.prisma.broadcast.findUnique({
      where: { id },
    });

    if (!broadcast) {
      throw new AppError('Broadcast not found', 404, 'NOT_FOUND');
    }

    if (broadcast.status === 'SENT' || broadcast.status === 'SENDING') {
      throw new AppError('Cannot update broadcast that is sent or sending', 400, 'INVALID_STATUS');
    }

    return this.prisma.broadcast.update({
      where: { id },
      data,
    });
  }

  async delete(id: string) {
    const broadcast = await this.prisma.broadcast.findUnique({
      where: { id },
    });

    if (!broadcast) {
      throw new AppError('Broadcast not found', 404, 'NOT_FOUND');
    }

    if (broadcast.status === 'SENDING') {
      throw new AppError('Cannot delete broadcast that is currently sending', 400, 'INVALID_STATUS');
    }

    // Delete recipients first
    await this.prisma.broadcastRecipient.deleteMany({
      where: { broadcastId: id },
    });

    // Delete broadcast
    await this.prisma.broadcast.delete({
      where: { id },
    });

    logger.info(`Broadcast deleted: ${id}`);
  }

  async send(id: string) {
    const broadcast = await this.findById(id);

    if (broadcast.status === 'SENT' || broadcast.status === 'SENDING') {
      throw new AppError('Broadcast already sent or sending', 400, 'INVALID_STATUS');
    }

    // Update status to sending
    await this.prisma.broadcast.update({
      where: { id },
      data: {
        status: 'SENDING',
        sentAt: new Date(),
      },
    });

    // Queue messages for sending (this will be handled by a background job)
    logger.info(`Broadcast ${id} queued for sending to ${broadcast.recipients.length} recipients`);

    return { message: 'Broadcast queued for sending', broadcastId: id };
  }

  async cancel(id: string) {
    const broadcast = await this.prisma.broadcast.findUnique({
      where: { id },
    });

    if (!broadcast) {
      throw new AppError('Broadcast not found', 404, 'NOT_FOUND');
    }

    if (broadcast.status === 'SENT') {
      throw new AppError('Cannot cancel broadcast that has been sent', 400, 'INVALID_STATUS');
    }

    await this.prisma.broadcast.update({
      where: { id },
      data: { status: 'CANCELLED' },
    });

    logger.info(`Broadcast cancelled: ${id}`);
  }

  async getAnalytics(id: string) {
    const broadcast = await this.findById(id);

    const recipientStats = await this.prisma.broadcastRecipient.groupBy({
      by: ['status'],
      where: { broadcastId: id },
      _count: true,
    });

    const stats: any = {
      total: broadcast.totalRecipients,
      sent: broadcast.sentCount || 0,
      delivered: broadcast.deliveredCount || 0,
      read: broadcast.readCount || 0,
      failed: broadcast.failedCount || 0,
      pending: 0,
    };

    recipientStats.forEach(stat => {
      if (stat.status === 'PENDING') stats.pending = stat._count;
      if (stat.status === 'SENT') stats.sent = stat._count;
      if (stat.status === 'DELIVERED') stats.delivered = stat._count;
      if (stat.status === 'READ') stats.read = stat._count;
      if (stat.status === 'FAILED') stats.failed = stat._count;
    });

    // Calculate rates
    const deliveryRate = stats.total > 0 ? (stats.delivered / stats.total) * 100 : 0;
    const readRate = stats.delivered > 0 ? (stats.read / stats.delivered) * 100 : 0;
    const failureRate = stats.total > 0 ? (stats.failed / stats.total) * 100 : 0;

    return {
      broadcast,
      stats,
      rates: {
        delivery: deliveryRate.toFixed(2),
        read: readRate.toFixed(2),
        failure: failureRate.toFixed(2),
      },
    };
  }

  private async getRecipients(userId: string, data: CreateBroadcastData): Promise<string[]> {
    let contactIds: string[] = [];

    switch (data.recipientType) {
      case 'ALL':
        const allContacts = await this.prisma.contact.findMany({
          where: { userId },
          select: { id: true },
        });
        contactIds = allContacts.map(c => c.id);
        break;

      case 'SEGMENT':
        if (data.segmentIds && data.segmentIds.length > 0) {
          // Get contacts from segments (simplified - you'll need to implement segment logic)
          const segments = await this.prisma.segment.findMany({
            where: {
              id: { in: data.segmentIds },
              userId,
            },
          });
          // TODO: Implement actual segment filtering logic based on criteria
          const segmentContacts = await this.prisma.contact.findMany({
            where: { userId },
            select: { id: true },
          });
          contactIds = segmentContacts.map(c => c.id);
        }
        break;

      case 'TAG':
        if (data.tagIds && data.tagIds.length > 0) {
          const taggedContacts = await this.prisma.contact.findMany({
            where: {
              userId,
              tags: {
                some: {
                  id: { in: data.tagIds },
                },
              },
            },
            select: { id: true },
          });
          contactIds = taggedContacts.map(c => c.id);
        }
        break;

      case 'MANUAL':
        contactIds = data.contactIds || [];
        break;
    }

    // Remove duplicates
    return [...new Set(contactIds)];
  }
}
