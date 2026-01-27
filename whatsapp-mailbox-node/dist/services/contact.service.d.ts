import { Contact } from '@prisma/client';
import { ContactRepository } from '../repositories/contact.repository';
interface CreateContactInput {
    phoneNumber: string;
    name?: string;
    email?: string;
    tags?: string[];
}
interface ContactFilters {
    query?: string;
    tags?: string[];
    isBlocked?: boolean;
}
interface PaginatedResult<T> {
    data: T[];
    total: number;
    page: number;
    limit: number;
}
export interface IContactService {
    createContact(userId: string, input: CreateContactInput): Promise<Contact>;
    getContact(id: string): Promise<Contact>;
    searchContacts(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>>;
    updateContact(id: string, data: Partial<Contact>): Promise<Contact>;
    deleteContact(id: string): Promise<void>;
    blockContact(id: string): Promise<Contact>;
    unblockContact(id: string): Promise<Contact>;
}
export declare class ContactService implements IContactService {
    private contactRepository;
    private tagRepository?;
    constructor(contactRepository: ContactRepository, tagRepository?: any | undefined);
    createContact(userId: string, input: CreateContactInput): Promise<Contact>;
    getContact(id: string): Promise<Contact>;
    searchContacts(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>>;
    updateContact(id: string, data: Partial<Contact>): Promise<Contact>;
    deleteContact(id: string): Promise<void>;
    blockContact(id: string): Promise<Contact>;
    unblockContact(id: string): Promise<Contact>;
}
export {};
//# sourceMappingURL=contact.service.d.ts.map