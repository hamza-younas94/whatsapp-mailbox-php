"use strict";
// src/repositories/campaign.repository.ts
// Campaign data access
Object.defineProperty(exports, "__esModule", { value: true });
exports.CampaignRepository = void 0;
const base_repository_1 = require("./base.repository");
class CampaignRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'campaign';
    }
    async findByStatus(status) {
        return this.prisma.campaign.findMany({
            where: { status: status },
            orderBy: { createdAt: 'desc' },
        });
    }
    async updateStatus(id, status) {
        return this.prisma.campaign.update({
            where: { id },
            data: { status: status },
        });
    }
    async updateStats(id, stats) {
        return this.prisma.campaign.update({
            where: { id },
            data: {
                ...(stats.sentCount !== undefined && { sentCount: { increment: stats.sentCount } }),
                ...(stats.failedCount !== undefined && { failedCount: { increment: stats.failedCount } }),
            },
        });
    }
}
exports.CampaignRepository = CampaignRepository;
//# sourceMappingURL=campaign.repository.js.map