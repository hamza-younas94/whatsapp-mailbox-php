"use strict";
// src/services/analytics.service.ts
// Analytics and reporting
Object.defineProperty(exports, "__esModule", { value: true });
exports.AnalyticsService = void 0;
class AnalyticsService {
    constructor(prisma) {
        this.prisma = prisma;
    }
    async getStats(userId, startDate, endDate) {
        const dateFilter = {
            ...(startDate && { gte: startDate }),
            ...(endDate && { lte: endDate }),
        };
        const [totalMessages, sentMessages, receivedMessages, totalContacts, activeContacts, campaignsSent, messagesByType,] = await Promise.all([
            // Total messages
            this.prisma.message.count({
                where: { userId, ...(Object.keys(dateFilter).length && { createdAt: dateFilter }) },
            }),
            // Sent messages
            this.prisma.message.count({
                where: {
                    userId,
                    direction: 'OUTGOING',
                    ...(Object.keys(dateFilter).length && { createdAt: dateFilter }),
                },
            }),
            // Received messages
            this.prisma.message.count({
                where: {
                    userId,
                    direction: 'INCOMING',
                    ...(Object.keys(dateFilter).length && { createdAt: dateFilter }),
                },
            }),
            // Total contacts
            this.prisma.contact.count({ where: { userId } }),
            // Active contacts (messaged in last 30 days)
            this.prisma.contact.count({
                where: {
                    userId,
                    lastMessageAt: { gte: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000) },
                },
            }),
            // Campaigns sent
            this.prisma.campaign.count({
                where: { status: 'COMPLETED', ...(Object.keys(dateFilter).length && { completedAt: dateFilter }) },
            }),
            // Messages by type
            this.prisma.message.groupBy({
                by: ['messageType'],
                where: { userId },
                _count: { id: true },
            }),
        ]);
        // Messages by day (last 7 days)
        const messagesByDay = await this.getMessagesByDay(userId, 7);
        return {
            totalMessages,
            sentMessages,
            receivedMessages,
            totalContacts,
            activeContacts,
            campaignsSent,
            messagesByDay,
            messagesByType: messagesByType.map((m) => ({
                type: m.messageType,
                count: m._count.id,
            })),
        };
    }
    async getMessageTrends(userId, days) {
        const startDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000);
        const messages = await this.prisma.message.findMany({
            where: {
                userId,
                createdAt: { gte: startDate },
            },
            select: {
                createdAt: true,
                direction: true,
            },
        });
        // Group by date
        const trends = {};
        messages.forEach((msg) => {
            const date = msg.createdAt.toISOString().split('T')[0];
            if (!trends[date]) {
                trends[date] = { sent: 0, received: 0 };
            }
            if (msg.direction === 'OUTGOING') {
                trends[date].sent++;
            }
            else {
                trends[date].received++;
            }
        });
        return Object.entries(trends).map(([date, counts]) => ({
            date,
            ...counts,
        }));
    }
    async getMessagesByDay(userId, days) {
        const startDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000);
        const messages = await this.prisma.message.findMany({
            where: {
                userId,
                createdAt: { gte: startDate },
            },
            select: { createdAt: true },
        });
        const countByDay = {};
        messages.forEach((msg) => {
            const date = msg.createdAt.toISOString().split('T')[0];
            countByDay[date] = (countByDay[date] || 0) + 1;
        });
        return Object.entries(countByDay).map(([date, count]) => ({ date, count }));
    }
}
exports.AnalyticsService = AnalyticsService;
//# sourceMappingURL=analytics.service.js.map