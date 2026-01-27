import { QuickReply } from '@prisma/client';
import { QuickReplyRepository } from '../repositories/quick-reply.repository';
export interface CreateQuickReplyInput {
    title: string;
    content: string;
    category?: string;
    shortcut?: string;
}
export interface IQuickReplyService {
    createQuickReply(userId: string, input: CreateQuickReplyInput): Promise<QuickReply>;
    getQuickReplies(userId: string): Promise<QuickReply[]>;
    searchQuickReplies(userId: string, query: string): Promise<QuickReply[]>;
    updateQuickReply(id: string, data: Partial<QuickReply>): Promise<QuickReply>;
    deleteQuickReply(id: string): Promise<void>;
}
export declare class QuickReplyService implements IQuickReplyService {
    private repository;
    constructor(repository: QuickReplyRepository);
    createQuickReply(userId: string, input: CreateQuickReplyInput): Promise<QuickReply>;
    getQuickReplies(userId: string): Promise<QuickReply[]>;
    searchQuickReplies(userId: string, query: string): Promise<QuickReply[]>;
    updateQuickReply(id: string, data: Partial<QuickReply>): Promise<QuickReply>;
    deleteQuickReply(id: string): Promise<void>;
}
//# sourceMappingURL=quick-reply.service.d.ts.map