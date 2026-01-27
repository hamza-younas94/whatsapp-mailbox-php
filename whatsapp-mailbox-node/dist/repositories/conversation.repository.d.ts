import { PrismaClient, Conversation } from '@prisma/client';
export declare class ConversationRepository {
    private prisma;
    constructor(prisma: PrismaClient);
    findOrCreate(userId: string, contactId: string): Promise<Conversation>;
}
//# sourceMappingURL=conversation.repository.d.ts.map