"use strict";
// src/services/broadcast.service.ts
// Broadcast campaign logic
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.BroadcastService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class BroadcastService {
    constructor(campaignRepository, segmentRepository, messageService) {
        this.campaignRepository = campaignRepository;
        this.segmentRepository = segmentRepository;
        this.messageService = messageService;
    }
    async createBroadcast(userId, input) {
        try {
            // Get recipient list
            let recipientIds = [];
            if (input.segmentId) {
                recipientIds = await this.segmentRepository.evaluateSegment(input.segmentId);
            }
            else if (input.recipients) {
                recipientIds = input.recipients;
            }
            else {
                throw new errors_1.ValidationError('Either recipients or segmentId is required');
            }
            const campaign = await this.campaignRepository.create({
                name: input.name,
                type: 'BROADCAST',
                status: input.scheduleTime ? 'SCHEDULED' : 'DRAFT',
                content: input.content,
                mediaUrl: input.mediaUrl,
                scheduleTime: input.scheduleTime,
                recipientCount: recipientIds.length,
                metadata: { recipientIds, userId },
            });
            logger_1.default.info({ id: campaign.id, recipients: recipientIds.length }, 'Broadcast created');
            // If no schedule time, send immediately
            if (!input.scheduleTime) {
                await this.sendBroadcast(campaign.id);
            }
            return campaign;
        }
        catch (error) {
            logger_1.default.error({ input, error }, 'Failed to create broadcast');
            throw error;
        }
    }
    async getBroadcasts() {
        return this.campaignRepository.findAll();
    }
    async sendBroadcast(campaignId) {
        try {
            const campaign = await this.campaignRepository.findById(campaignId);
            if (!campaign) {
                throw new errors_1.NotFoundError('Campaign');
            }
            // Update status to RUNNING
            await this.campaignRepository.updateStatus(campaignId, 'RUNNING');
            const metadata = campaign.metadata;
            const recipientIds = metadata.recipientIds || [];
            const userId = metadata.userId;
            logger_1.default.info({ campaignId, recipients: recipientIds.length }, 'Starting broadcast');
            // Send messages in batches
            const batchSize = 50;
            for (let i = 0; i < recipientIds.length; i += batchSize) {
                const batch = recipientIds.slice(i, i + batchSize);
                const results = await Promise.allSettled(batch.map((contactId) => this.messageService.sendMessage(userId, {
                    contactId,
                    content: campaign.content || '',
                    mediaUrl: campaign.mediaUrl || undefined,
                })));
                // Update stats
                const successCount = results.filter((r) => r.status === 'fulfilled').length;
                const failCount = results.filter((r) => r.status === 'rejected').length;
                await this.campaignRepository.updateStats(campaignId, {
                    sentCount: successCount,
                    failedCount: failCount,
                });
                // Rate limiting: Wait 1 second between batches
                if (i + batchSize < recipientIds.length) {
                    await new Promise((resolve) => setTimeout(resolve, 1000));
                }
            }
            // Mark as completed
            await this.campaignRepository.update(campaignId, {
                status: 'COMPLETED',
                completedAt: new Date(),
            });
            logger_1.default.info({ campaignId }, 'Broadcast completed');
        }
        catch (error) {
            logger_1.default.error({ campaignId, error }, 'Broadcast failed');
            await this.campaignRepository.updateStatus(campaignId, 'FAILED');
            throw error;
        }
    }
    async scheduleBroadcast(campaignId, scheduleTime) {
        return this.campaignRepository.update(campaignId, {
            scheduleTime,
            status: 'SCHEDULED',
        });
    }
    async cancelBroadcast(campaignId) {
        const campaign = await this.campaignRepository.findById(campaignId);
        if (!campaign) {
            throw new errors_1.NotFoundError('Campaign');
        }
        if (campaign.status === 'RUNNING') {
            throw new errors_1.ValidationError('Cannot cancel running campaign');
        }
        return this.campaignRepository.updateStatus(campaignId, 'DRAFT');
    }
}
exports.BroadcastService = BroadcastService;
//# sourceMappingURL=broadcast.service.js.map