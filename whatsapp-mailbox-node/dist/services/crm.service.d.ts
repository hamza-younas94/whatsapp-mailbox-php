import { PrismaClient } from '@prisma/client';
export interface CreateDealData {
    title: string;
    contactId: string;
    value?: number;
    stage: string;
    expectedCloseDate?: Date;
    description?: string;
}
export interface UpdateDealData {
    title?: string;
    value?: number;
    stage?: string;
    expectedCloseDate?: Date;
    description?: string;
    status?: 'OPEN' | 'WON' | 'LOST';
}
export interface ICRMService {
    createDeal(userId: string, data: CreateDealData): Promise<any>;
    getDeals(userId: string, filters?: any): Promise<any[]>;
    updateDeal(dealId: string, data: UpdateDealData): Promise<any>;
    moveDealToStage(dealId: string, stage: string): Promise<any>;
}
export declare class CRMService implements ICRMService {
    private prisma;
    constructor(prisma: PrismaClient);
    createDeal(userId: string, data: CreateDealData): Promise<any>;
    getDeals(userId: string, filters?: any): Promise<any[]>;
    updateDeal(dealId: string, data: UpdateDealData): Promise<any>;
    moveDealToStage(dealId: string, stage: string): Promise<any>;
    getDealStats(userId: string): Promise<any>;
}
//# sourceMappingURL=crm.service.d.ts.map