import { Message, MessageType, MessageDirection, MessageStatus } from '@prisma/client';
import { MessageRepository } from '../repositories/message.repository';
import { ContactRepository } from '../repositories/contact.repository';
import { ConversationRepository } from '../repositories/conversation.repository';
import { WhatsAppService } from './whatsapp.service';
interface CreateMessageInput {
    phoneNumber?: string;
    contactId?: string;
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
    getMessages(userId: string, conversationId: string, limit?: number, offset?: number): Promise<PaginatedResult<Message>>;
    listMessages(userId: string, filters: MessageFilters): Promise<PaginatedResult<Message>>;
    markAsRead(messageId: string): Promise<Message>;
    deleteMessage(messageId: string): Promise<void>;
}
export declare class MessageService implements IMessageService {
    private messageRepository;
    private whatsAppService;
    private contactRepository;
    private conversationRepository;
    constructor(messageRepository: MessageRepository, whatsAppService: WhatsAppService, contactRepository: ContactRepository, conversationRepository: ConversationRepository);
    sendMessage(userId: string, input: CreateMessageInput): Promise<Message>;
    receiveMessage(waMessageId: string, payload: any): Promise<Message>;
    getMessagesByContact(userId: string, contactId: string, limit?: number, offset?: number): Promise<PaginatedResult<Message>>;
    getMessages(userId: string, conversationId: string, limit?: number, offset?: number): Promise<PaginatedResult<Message>>;
    markAsRead(messageId: string): Promise<Message>;
    deleteMessage(messageId: string): Promise<void>;
    listMessages(userId: string, filters: MessageFilters): Promise<PaginatedResult<Message>>;
}
export {};
//# sourceMappingURL=message.service.d.ts.map