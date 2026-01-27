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
    findOrCreate(userId: string, phoneNumber: string, data?: Partial<Contact>): Promise<Contact>;
}
export declare class ContactRepository extends BaseRepository<Contact> implements IContactRepository {
    protected modelName: "contact";
    constructor(prisma: PrismaClient);
    findByPhoneNumber(userId: string, phoneNumber: string): Promise<Contact | null>;
    search(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>>;
    findOrCreate(userId: string, phoneNumber: string, data?: Partial<Contact>): Promise<Contact>;
}
export {};
//# sourceMappingURL=contact.repository.d.ts.map