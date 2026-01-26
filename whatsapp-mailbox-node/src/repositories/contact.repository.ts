// src/repositories/contact.repository.ts
// Contact data access layer

import { PrismaClient, Contact, Prisma } from '@prisma/client';
import { BaseRepository } from './base.repository';

interface ContactFilters {
  query?: string;
  search?: string;
  tags?: string[];
  isBlocked?: boolean;
  limit?: number;
  offset?: number;
}

interface PaginatedResult<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface IContactRepository {
  findById(id: string): Promise<Contact | null>;
  findByPhoneNumber(userId: string, phoneNumber: string): Promise<Contact | null>;
  search(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>>;
  create(data: Prisma.ContactCreateInput): Promise<Contact>;
  update(id: string, data: Prisma.ContactUpdateInput): Promise<Contact>;
  delete(id: string): Promise<Contact>;
  findOrCreate(
    userId: string,
    phoneNumber: string,
    data?: Partial<Contact>,
  ): Promise<Contact>;
}

export class ContactRepository extends BaseRepository<Contact> implements IContactRepository {
  protected modelName = 'contact' as const;

  constructor(prisma: PrismaClient) {
    super(prisma);
  }

  async findByPhoneNumber(userId: string, phoneNumber: string): Promise<Contact | null> {
    return this.prisma.contact.findUnique({
      where: { userId_phoneNumber: { userId, phoneNumber } },
      include: { tags: { include: { tag: true } } },
    });
  }

  async search(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>> {
    const limit = Math.min(filters.limit || 20, 100);
    const offset = filters.offset || 0;

    const where: Prisma.ContactWhereInput = {
      userId,
      isBlocked: filters.isBlocked ?? false,
      ...(filters.search && {
        OR: [
          { name: { contains: filters.search } },
          { phoneNumber: { contains: filters.search } },
          { email: { contains: filters.search } },
        ],
      }),
      ...(filters.tags?.length && {
        tags: { some: { tag: { name: { in: filters.tags } } } },
      }),
    };

    const [contacts, total] = await Promise.all([
      this.prisma.contact.findMany({
        where,
        skip: offset,
        take: limit,
        include: { tags: { include: { tag: true } }, conversations: true },
        orderBy: { lastMessageAt: 'desc' },
      }),
      this.prisma.contact.count({ where }),
    ]);

    return {
      data: contacts,
      total,
      page: Math.floor(offset / limit) + 1,
      limit,
      hasMore: offset + limit < total,
    };
  }

  async findOrCreate(
    userId: string,
    phoneNumber: string,
    data?: Partial<Contact>,
  ): Promise<Contact> {
    return this.prisma.contact.upsert({
      where: { userId_phoneNumber: { userId, phoneNumber } },
      update: (data || {}) as any,
      create: {
        userId,
        phoneNumber,
        ...data,
      } as any,
    });
  }
}
