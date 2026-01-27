import { Message, PrismaClient } from '@prisma/client';
import { MessageRepository } from '../repositories/message.repository';
import { WhatsAppService } from './whatsapp.service';
export interface ScheduleMessageInput {
    contactId: string;
    content: string;
    mediaUrl?: string;
    scheduledAt: Date;
}
export interface IScheduledMessageService {
    scheduleMessage(userId: string, input: ScheduleMessageInput): Promise<Message>;
    getScheduledMessages(userId: string): Promise<Message[]>;
    cancelScheduledMessage(messageId: string): Promise<void>;
    processDueMessages(): Promise<void>;
}
export declare class ScheduledMessageService implements IScheduledMessageService {
    private messageRepository;
    private whatsAppService;
    private prisma;
    constructor(messageRepository: MessageRepository, whatsAppService: WhatsAppService, prisma: PrismaClient);
    scheduleMessage(userId: string, input: ScheduleMessageInput): Promise<Message>;
    getScheduledMessages(userId: string): Promise<Message[]>;
    cancelScheduledMessage(messageId: string): Promise<void>;
    processDueMessages(): Promise<void>;
}
//# sourceMappingURL=scheduled-message.service.d.ts.map