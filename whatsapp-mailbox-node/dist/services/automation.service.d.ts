import { Automation } from '@prisma/client';
import { AutomationRepository } from '../repositories/automation.repository';
import { MessageService } from './message.service';
import { TagService } from './tag.service';
export interface CreateAutomationInput {
    name: string;
    trigger: string;
    actions: AutomationAction[];
}
export interface AutomationAction {
    type: 'SEND_MESSAGE' | 'ADD_TAG' | 'REMOVE_TAG' | 'SEND_EMAIL' | 'WEBHOOK' | 'WAIT';
    params: Record<string, any>;
}
export interface IAutomationService {
    createAutomation(userId: string, input: CreateAutomationInput): Promise<Automation>;
    getAutomations(userId: string): Promise<Automation[]>;
    updateAutomation(id: string, data: Partial<Automation>): Promise<Automation>;
    deleteAutomation(id: string): Promise<void>;
    toggleAutomation(id: string, isActive: boolean): Promise<Automation>;
    executeAutomation(automationId: string, context: Record<string, any>): Promise<void>;
    triggerAutomations(trigger: string, context: Record<string, any>): Promise<void>;
}
export declare class AutomationService implements IAutomationService {
    private repository;
    private messageService;
    private tagService;
    constructor(repository: AutomationRepository, messageService: MessageService, tagService: TagService);
    createAutomation(userId: string, input: CreateAutomationInput): Promise<Automation>;
    getAutomations(userId: string): Promise<Automation[]>;
    updateAutomation(id: string, data: Partial<Automation>): Promise<Automation>;
    deleteAutomation(id: string): Promise<void>;
    toggleAutomation(id: string, isActive: boolean): Promise<Automation>;
    executeAutomation(automationId: string, context: Record<string, any>): Promise<void>;
    triggerAutomations(trigger: string, context: Record<string, any>): Promise<void>;
    private executeAction;
}
//# sourceMappingURL=automation.service.d.ts.map