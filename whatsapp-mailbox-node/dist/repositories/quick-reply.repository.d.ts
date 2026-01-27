import { PrismaClient, QuickReply } from '@prisma/client';
import { BaseRepository } from './base.repository';
export interface IQuickReplyRepository {
    findByUserId(userId: string): Promise<QuickReply[]>;
    findByShortcut(userId: string, shortcut: string): Promise<QuickReply | null>;
    search(userId: string, query: string): Promise<QuickReply[]>;
}
export declare class QuickReplyRepository extends BaseRepository<QuickReply> implements IQuickReplyRepository {
    protected modelName: "quickReply";
    constructor(prisma: PrismaClient);
    findByUserId(userId: string): Promise<QuickReply[]>;
    findByShortcut(userId: string, shortcut: string): Promise<QuickReply | null>;
    search(userId: string, query: string): Promise<QuickReply[]>;
}
//# sourceMappingURL=quick-reply.repository.d.ts.map