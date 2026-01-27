import { PrismaClient } from '@prisma/client';
export interface AnalyticsStats {
    totalMessages: number;
    sentMessages: number;
    receivedMessages: number;
    totalContacts: number;
    activeContacts: number;
    campaignsSent: number;
    messagesByDay: Array<{
        date: string;
        count: number;
    }>;
    messagesByType: Array<{
        type: string;
        count: number;
    }>;
}
export interface IAnalyticsService {
    getStats(userId: string, startDate?: Date, endDate?: Date): Promise<AnalyticsStats>;
    getMessageTrends(userId: string, days: number): Promise<Array<{
        date: string;
        sent: number;
        received: number;
    }>>;
}
export declare class AnalyticsService implements IAnalyticsService {
    private prisma;
    constructor(prisma: PrismaClient);
    getStats(userId: string, startDate?: Date, endDate?: Date): Promise<AnalyticsStats>;
    getMessageTrends(userId: string, days: number): Promise<Array<{
        date: string;
        sent: number;
        received: number;
    }>>;
    private getMessagesByDay;
}
//# sourceMappingURL=analytics.service.d.ts.map