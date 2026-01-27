import { PrismaClient, DripCampaign } from '@prisma/client';
import { WhatsAppService } from './whatsapp.service';
export interface CreateDripCampaignData {
    name: string;
    description?: string;
    triggerType: 'MANUAL' | 'TAG_ADDED' | 'FORM_SUBMITTED';
    triggerValue?: string;
    steps: Array<{
        sequence: number;
        delayHours: number;
        message: string;
        mediaUrl?: string;
        mediaType?: 'IMAGE' | 'VIDEO' | 'AUDIO' | 'DOCUMENT';
    }>;
}
export interface IDripCampaignService {
    createDripCampaign(userId: string, data: CreateDripCampaignData): Promise<DripCampaign>;
    enrollContact(campaignId: string, contactId: string): Promise<void>;
    processDueSteps(): Promise<void>;
}
export declare class DripCampaignService implements IDripCampaignService {
    private prisma;
    private whatsappService;
    constructor(prisma: PrismaClient, whatsappService: WhatsAppService);
    createDripCampaign(userId: string, data: CreateDripCampaignData): Promise<DripCampaign>;
    enrollContact(campaignId: string, contactId: string): Promise<void>;
    processDueSteps(): Promise<void>;
    unenrollContact(campaignId: string, contactId: string): Promise<void>;
}
//# sourceMappingURL=drip-campaign.service.d.ts.map