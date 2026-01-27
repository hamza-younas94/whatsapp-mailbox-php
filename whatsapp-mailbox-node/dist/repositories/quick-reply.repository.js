"use strict";
// src/repositories/quick-reply.repository.ts
// Quick reply data access
Object.defineProperty(exports, "__esModule", { value: true });
exports.QuickReplyRepository = void 0;
const base_repository_1 = require("./base.repository");
class QuickReplyRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'quickReply';
    }
    async findByUserId(userId) {
        return this.prisma.quickReply.findMany({
            where: { userId },
            orderBy: { createdAt: 'desc' },
        });
    }
    async findByShortcut(userId, shortcut) {
        return this.prisma.quickReply.findFirst({
            where: { userId, shortcut },
        });
    }
    async search(userId, query) {
        return this.prisma.quickReply.findMany({
            where: {
                userId,
                OR: [
                    { title: { contains: query } },
                    { content: { contains: query } },
                ],
            },
            take: 20,
        });
    }
}
exports.QuickReplyRepository = QuickReplyRepository;
//# sourceMappingURL=quick-reply.repository.js.map