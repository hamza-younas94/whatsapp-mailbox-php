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
    findByConversation(conversationId: string, filters?: Omit<MessageFilters, 'conversationId'>): Promise<PaginatedResult<Message>>;
    findByContact(userId: string, contactId: string, filters?: MessageFilters): Promise<PaginatedResult<Message>>;
    findByUser(userId: string, filters: MessageFilters): Promise<PaginatedResult<Message>>;
    create(data: Prisma.MessageCreateInput): Promise<Message>;
    update(id: string, data: Prisma.MessageUpdateInput): Promise<Message>;
    delete(id: string): Promise<Message>;
    findByWaMessageId(waMessageId: string): Promise<Message | null>;
    updateStatus(id: string, status: string): Promise<Message>;
    getUnreadCount(conversationId: string): Promise<number>;
}
export declare class MessageRepository extends BaseRepository<Message> implements IMessageRepository {
    protected modelName: "message";
    constructor(prisma: PrismaClient);
    findByConversation(conversationId: string, filters?: Omit<MessageFilters, 'conversationId'>): Promise<PaginatedResult<Message>>;
    findByContact(userId: string, contactId: string, filters?: MessageFilters): Promise<PaginatedResult<Message>>;
    findByWaMessageId(waMessageId: string): Promise<Message | null>;
    updateStatus(id: string, status: string): Promise<Message>;
    getUnreadCount(conversationId: string): Promise<number>;
    findByUser(userId: string, filters: MessageFilters): Promise<PaginatedResult<Message>>;
}
export {};
//# sourceMappingURL=message.repository.d.ts.map