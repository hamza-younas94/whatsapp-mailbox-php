import { Campaign } from '@prisma/client';
import { CampaignRepository } from '../repositories/campaign.repository';
import { SegmentRepository } from '../repositories/segment.repository';
import { MessageService } from './message.service';
export interface CreateBroadcastInput {
    name: string;
    content: string;
    mediaUrl?: string;
    recipients?: string[];
    segmentId?: string;
    scheduleTime?: Date;
}
export interface IBroadcastService {
    createBroadcast(userId: string, input: CreateBroadcastInput): Promise<Campaign>;
    getBroadcasts(): Promise<Campaign[]>;
    sendBroadcast(campaignId: string): Promise<void>;
    scheduleBroadcast(campaignId: string, scheduleTime: Date): Promise<Campaign>;
    cancelBroadcast(campaignId: string): Promise<Campaign>;
}
export declare class BroadcastService implements IBroadcastService {
    private campaignRepository;
    private segmentRepository;
    private messageService;
    constructor(campaignRepository: CampaignRepository, segmentRepository: SegmentRepository, messageService: MessageService);
    createBroadcast(userId: string, input: CreateBroadcastInput): Promise<Campaign>;
    getBroadcasts(): Promise<Campaign[]>;
    sendBroadcast(campaignId: string): Promise<void>;
    scheduleBroadcast(campaignId: string, scheduleTime: Date): Promise<Campaign>;
    cancelBroadcast(campaignId: string): Promise<Campaign>;
}
//# sourceMappingURL=broadcast.service.d.ts.map