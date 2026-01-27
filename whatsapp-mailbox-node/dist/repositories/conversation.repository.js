"use strict";
// src/repositories/conversation.repository.ts
// Conversation data access helper
Object.defineProperty(exports, "__esModule", { value: true });
exports.ConversationRepository = void 0;
class ConversationRepository {
    constructor(prisma) {
        this.prisma = prisma;
    }
    async findOrCreate(userId, contactId) {
        const existing = await this.prisma.conversation.findUnique({
            where: { userId_contactId: { userId, contactId } },
        });
        if (existing)
            return existing;
        return this.prisma.conversation.create({
            data: {
                userId,
                contactId,
                isActive: true,
                lastMessageAt: new Date(),
            },
        });
    }
}
exports.ConversationRepository = ConversationRepository;
//# sourceMappingURL=conversation.repository.js.map