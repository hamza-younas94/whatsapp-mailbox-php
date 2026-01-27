"use strict";
// src/services/quick-reply.service.ts
// Quick reply business logic
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.QuickReplyService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class QuickReplyService {
    constructor(repository) {
        this.repository = repository;
    }
    async createQuickReply(userId, input) {
        try {
            // Validate shortcut uniqueness if provided
            if (input.shortcut) {
                const existing = await this.repository.findByShortcut(userId, input.shortcut);
                if (existing) {
                    throw new errors_1.ConflictError(`Shortcut '${input.shortcut}' already exists`);
                }
            }
            const quickReply = await this.repository.create({
                userId,
                ...input,
            });
            logger_1.default.info({ id: quickReply.id }, 'Quick reply created');
            return quickReply;
        }
        catch (error) {
            logger_1.default.error({ input, error }, 'Failed to create quick reply');
            throw error;
        }
    }
    async getQuickReplies(userId) {
        return this.repository.findByUserId(userId);
    }
    async searchQuickReplies(userId, query) {
        return this.repository.search(userId, query);
    }
    async updateQuickReply(id, data) {
        const existing = await this.repository.findById(id);
        if (!existing) {
            throw new errors_1.NotFoundError('Quick reply');
        }
        return this.repository.update(id, data);
    }
    async deleteQuickReply(id) {
        await this.repository.delete(id);
        logger_1.default.info({ id }, 'Quick reply deleted');
    }
}
exports.QuickReplyService = QuickReplyService;
//# sourceMappingURL=quick-reply.service.js.map