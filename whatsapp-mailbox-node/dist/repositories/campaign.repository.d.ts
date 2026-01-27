import { PrismaClient, Campaign } from '@prisma/client';
import { BaseRepository } from './base.repository';
export interface ICampaignRepository {
    findByStatus(status: string): Promise<Campaign[]>;
    updateStatus(id: string, status: string): Promise<Campaign>;
    updateStats(id: string, stats: {
        sentCount?: number;
        failedCount?: number;
    }): Promise<Campaign>;
}
export declare class CampaignRepository extends BaseRepository<Campaign> implements ICampaignRepository {
    protected modelName: "campaign";
    constructor(prisma: PrismaClient);
    findByStatus(status: string): Promise<Campaign[]>;
    updateStatus(id: string, status: string): Promise<Campaign>;
    updateStats(id: string, stats: {
        sentCount?: number;
        failedCount?: number;
    }): Promise<Campaign>;
}
//# sourceMappingURL=campaign.repository.d.ts.map