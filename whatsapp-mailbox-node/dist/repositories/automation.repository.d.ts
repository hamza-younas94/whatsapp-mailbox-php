import { PrismaClient, Automation } from '@prisma/client';
import { BaseRepository } from './base.repository';
export interface IAutomationRepository {
    findByTrigger(trigger: string): Promise<Automation[]>;
    findActive(userId: string): Promise<Automation[]>;
}
export declare class AutomationRepository extends BaseRepository<Automation> implements IAutomationRepository {
    protected modelName: "automation";
    constructor(prisma: PrismaClient);
    findByTrigger(trigger: string): Promise<Automation[]>;
    findActive(userId: string): Promise<Automation[]>;
}
//# sourceMappingURL=automation.repository.d.ts.map