"use strict";
// src/services/automation.service.ts
// Automation and workflow engine
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.AutomationService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class AutomationService {
    constructor(repository, messageService, tagService) {
        this.repository = repository;
        this.messageService = messageService;
        this.tagService = tagService;
    }
    async createAutomation(userId, input) {
        const automation = await this.repository.create({
            userId,
            name: input.name,
            trigger: input.trigger,
            actions: input.actions,
            isActive: true,
        });
        logger_1.default.info({ id: automation.id }, 'Automation created');
        return automation;
    }
    async getAutomations(userId) {
        return this.repository.findActive(userId);
    }
    async updateAutomation(id, data) {
        const existing = await this.repository.findById(id);
        if (!existing) {
            throw new errors_1.NotFoundError('Automation');
        }
        return this.repository.update(id, data);
    }
    async deleteAutomation(id) {
        await this.repository.delete(id);
        logger_1.default.info({ id }, 'Automation deleted');
    }
    async toggleAutomation(id, isActive) {
        return this.repository.update(id, { isActive });
    }
    async executeAutomation(automationId, context) {
        const automation = await this.repository.findById(automationId);
        if (!automation || !automation.isActive) {
            return;
        }
        const actions = automation.actions;
        for (const action of actions) {
            await this.executeAction(action, context);
        }
        logger_1.default.info({ automationId }, 'Automation executed');
    }
    async triggerAutomations(trigger, context) {
        const automations = await this.repository.findByTrigger(trigger);
        for (const automation of automations) {
            try {
                await this.executeAutomation(automation.id, context);
            }
            catch (error) {
                logger_1.default.error({ automationId: automation.id, error }, 'Automation execution failed');
            }
        }
    }
    async executeAction(action, context) {
        switch (action.type) {
            case 'SEND_MESSAGE':
                await this.messageService.sendMessage(context.userId, {
                    contactId: context.contactId,
                    content: action.params.content,
                    mediaUrl: action.params.mediaUrl,
                });
                break;
            case 'ADD_TAG':
                await this.tagService.addTagToContact(context.contactId, action.params.tagId);
                break;
            case 'REMOVE_TAG':
                await this.tagService.removeTagFromContact(context.contactId, action.params.tagId);
                break;
            case 'WAIT':
                await new Promise((resolve) => setTimeout(resolve, action.params.delay || 1000));
                break;
            case 'WEBHOOK':
                // Call external webhook
                await fetch(action.params.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(context),
                });
                break;
            default:
                logger_1.default.warn({ action: action.type }, 'Unknown action type');
        }
    }
}
exports.AutomationService = AutomationService;
//# sourceMappingURL=automation.service.js.map