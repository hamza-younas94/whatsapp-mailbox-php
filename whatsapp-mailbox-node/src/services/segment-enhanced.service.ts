// src/services/segment-enhanced.service.ts
// Enhanced Segment service with query builder

import { PrismaClient, Prisma } from '@prisma/client';
import { AppError } from '@utils/errors';
import logger from '@utils/logger';

interface SegmentCondition {
  field: string;
  operator: 'equals' | 'not_equals' | 'contains' | 'not_contains' | 'greater_than' | 'less_than' | 'in' | 'not_in';
  value: any;
}

interface SegmentCriteria {
  logic: 'AND' | 'OR';
  conditions: SegmentCondition[];
}

interface CreateSegmentData {
  name: string;
  description?: string;
  criteria: SegmentCriteria;
}

export class SegmentEnhancedService {
  constructor(private prisma: PrismaClient) {}

  async create(userId: string, data: CreateSegmentData) {
    try {
      const segment = await this.prisma.segment.create({
        data: {
          name: data.name,
          description: data.description,
          criteria: data.criteria as any,
          userId,
        },
      });

      // Calculate initial contact count
      const count = await this.getSegmentContactCount(userId, data.criteria);
      await this.prisma.segment.update({
        where: { id: segment.id },
        data: { contactCount: count },
      });

      logger.info(`Segment created: ${segment.id} with ${count} contacts`);
      return this.findById(segment.id);
    } catch (error) {
      logger.error('Error creating segment:', error);
      throw error;
    }
  }

  async findAll(userId: string) {
    return this.prisma.segment.findMany({
      where: { userId },
      orderBy: { createdAt: 'desc' },
    });
  }

  async findById(id: string) {
    const segment = await this.prisma.segment.findUnique({
      where: { id },
      include: {
        user: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
      },
    });

    if (!segment) {
      throw new AppError('Segment not found', 404, 'NOT_FOUND');
    }

    return segment;
  }

  async update(id: string, data: Partial<CreateSegmentData>) {
    const segment = await this.prisma.segment.findUnique({
      where: { id },
      include: { user: true },
    });

    if (!segment) {
      throw new AppError('Segment not found', 404, 'NOT_FOUND');
    }

    const updateData: any = {};
    if (data.name) updateData.name = data.name;
    if (data.description !== undefined) updateData.description = data.description;
    if (data.criteria) {
      updateData.criteria = data.criteria;
      // Recalculate contact count
      const count = await this.getSegmentContactCount(segment.userId, data.criteria);
      updateData.contactCount = count;
    }

    return this.prisma.segment.update({
      where: { id },
      data: updateData,
    });
  }

  async delete(id: string) {
    const segment = await this.prisma.segment.findUnique({
      where: { id },
    });

    if (!segment) {
      throw new AppError('Segment not found', 404, 'NOT_FOUND');
    }

    await this.prisma.segment.delete({
      where: { id },
    });

    logger.info(`Segment deleted: ${id}`);
  }

  async preview(userId: string, criteria: SegmentCriteria) {
    const count = await this.getSegmentContactCount(userId, criteria);
    const contacts = await this.getSegmentContacts(userId, criteria, 10);

    return {
      count,
      preview: contacts,
    };
  }

  async getContacts(id: string, limit: number = 100) {
    const segment = await this.findById(id);
    const criteria = segment.criteria as any as SegmentCriteria;
    
    return this.getSegmentContacts(segment.userId, criteria, limit);
  }

  async refresh(id: string) {
    const segment = await this.findById(id);
    const criteria = segment.criteria as any as SegmentCriteria;
    const count = await this.getSegmentContactCount(segment.userId, criteria);

    await this.prisma.segment.update({
      where: { id },
      data: { contactCount: count },
    });

    return { count };
  }

  private async getSegmentContactCount(userId: string, criteria: SegmentCriteria): Promise<number> {
    const where = this.buildWhereClause(userId, criteria);
    return this.prisma.contact.count({ where });
  }

  private async getSegmentContacts(userId: string, criteria: SegmentCriteria, limit: number) {
    const where = this.buildWhereClause(userId, criteria);
    return this.prisma.contact.findMany({
      where,
      take: limit,
      select: {
        id: true,
        phoneNumber: true,
        name: true,
        email: true,
        createdAt: true,
      },
      orderBy: { createdAt: 'desc' },
    });
  }

  private buildWhereClause(userId: string, criteria: SegmentCriteria): Prisma.ContactWhereInput {
    const baseWhere: Prisma.ContactWhereInput = { userId };

    if (!criteria.conditions || criteria.conditions.length === 0) {
      return baseWhere;
    }

    const conditionClauses = criteria.conditions.map(condition => 
      this.buildConditionClause(condition)
    );

    if (criteria.logic === 'AND') {
      return { ...baseWhere, AND: conditionClauses };
    } else {
      return { ...baseWhere, OR: conditionClauses };
    }
  }

  private buildConditionClause(condition: SegmentCondition): Prisma.ContactWhereInput {
    const { field, operator, value } = condition;

    switch (operator) {
      case 'equals':
        return { [field]: value };
      
      case 'not_equals':
        return { [field]: { not: value } };
      
      case 'contains':
        return { [field]: { contains: value, mode: 'insensitive' } };
      
      case 'not_contains':
        return { [field]: { not: { contains: value, mode: 'insensitive' } } };
      
      case 'greater_than':
        return { [field]: { gt: value } };
      
      case 'less_than':
        return { [field]: { lt: value } };
      
      case 'in':
        return { [field]: { in: Array.isArray(value) ? value : [value] } };
      
      case 'not_in':
        return { [field]: { notIn: Array.isArray(value) ? value : [value] } };
      
      default:
        return {};
    }
  }
}
