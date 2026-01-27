"use strict";
// src/repositories/message.repository.ts
// Message data access layer
Object.defineProperty(exports, "__esModule", { value: true });
exports.MessageRepository = void 0;
const base_repository_1 = require("./base.repository");
class MessageRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'message';
    }
    async findByConversation(conversationId, filters) {
        const limit = Math.min(filters?.limit || 50, 100);
        const offset = filters?.offset || 0;
        const where = {
            conversationId,
            ...(filters?.status && { status: filters.status }),
            ...(filters?.direction && { direction: filters.direction }),
            ...(filters?.startDate && { createdAt: { gte: filters.startDate } }),
            ...(filters?.endDate && { createdAt: { lte: filters.endDate } }),
        };
        const [messages, total] = await Promise.all([
            this.prisma.message.findMany({
                where,
                skip: offset,
                take: limit,
                orderBy: { createdAt: 'desc' },
            }),
            this.prisma.message.count({ where }),
        ]);
        return {
            data: messages,
            total,
            page: Math.floor(offset / limit) + 1,
            limit: limit,
        };
    }
    async findByContact(userId, contactId, filters) {
        const limit = Math.min(filters?.limit || 50, 100);
        const offset = filters?.offset || 0;
        const where = {
            userId,
            contactId,
            ...(filters?.status && { status: filters.status }),
            ...(filters?.direction && { direction: filters.direction }),
        };
        const [messages, total] = await Promise.all([
            this.prisma.message.findMany({
                where,
                skip: offset,
                take: limit,
                orderBy: { createdAt: 'desc' },
                include: { conversation: true },
            }),
            this.prisma.message.count({ where }),
        ]);
        return {
            data: messages,
            total,
            page: Math.floor(offset / limit) + 1,
            limit: limit,
        };
    }
    async findByWaMessageId(waMessageId) {
        return this.prisma.message.findUnique({
            where: { waMessageId },
        });
    }
    async updateStatus(id, status) {
        return this.prisma.message.update({
            where: { id },
            data: { status: status },
        });
    }
    async getUnreadCount(conversationId) {
        return this.prisma.message.count({
            where: {
                conversationId,
                direction: 'INCOMING',
                status: 'RECEIVED',
            },
        });
    }
    async findByUser(userId, filters) {
        const limit = Math.min(filters.limit || 20, 100);
        const offset = filters.offset || 0;
        const where = {
            userId,
            ...(filters.direction && { direction: filters.direction }),
            ...(filters.status && { status: filters.status }),
            ...(filters.messageType && { messageType: filters.messageType }),
            ...(filters.query && { content: { contains: filters.query } }),
            ...(filters.startDate && { createdAt: { gte: filters.startDate } }),
            ...(filters.endDate && { createdAt: { lte: filters.endDate } }),
        };
        const [messages, total] = await Promise.all([
            this.prisma.message.findMany({
                where,
                skip: offset,
                take: limit,
                orderBy: { createdAt: 'desc' },
                include: { contact: true },
            }),
            this.prisma.message.count({ where }),
        ]);
        return {
            data: messages,
            total,
            page: Math.floor(offset / limit) + 1,
            limit,
        };
    }
}
exports.MessageRepository = MessageRepository;
//# sourceMappingURL=message.repository.js.map