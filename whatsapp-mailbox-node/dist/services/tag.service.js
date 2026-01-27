"use strict";
// src/services/tag.service.ts
// Tag management business logic
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.TagService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class TagService {
    constructor(repository) {
        this.repository = repository;
    }
    async createTag(userId, input) {
        try {
            // Check for duplicate name
            const existing = await this.repository.findByName(userId, input.name);
            if (existing) {
                throw new errors_1.ConflictError(`Tag '${input.name}' already exists`);
            }
            const tag = await this.repository.create({
                userId,
                name: input.name,
                color: input.color || '#3B82F6',
            });
            logger_1.default.info({ id: tag.id }, 'Tag created');
            return tag;
        }
        catch (error) {
            logger_1.default.error({ input, error }, 'Failed to create tag');
            throw error;
        }
    }
    async getTags(userId) {
        return this.repository.findByUserId(userId);
    }
    async updateTag(id, data) {
        const existing = await this.repository.findById(id);
        if (!existing) {
            throw new errors_1.NotFoundError('Tag');
        }
        return this.repository.update(id, data);
    }
    async deleteTag(id) {
        await this.repository.delete(id);
        logger_1.default.info({ id }, 'Tag deleted');
    }
    async addTagToContact(contactId, tagId) {
        try {
            await this.repository.addToContact(contactId, tagId);
            logger_1.default.info({ contactId, tagId }, 'Tag added to contact');
        }
        catch (error) {
            logger_1.default.error({ contactId, tagId, error }, 'Failed to add tag to contact');
            throw error;
        }
    }
    async removeTagFromContact(contactId, tagId) {
        await this.repository.removeFromContact(contactId, tagId);
        logger_1.default.info({ contactId, tagId }, 'Tag removed from contact');
    }
    async getContactTags(contactId) {
        return this.repository.getContactTags(contactId);
    }
}
exports.TagService = TagService;
//# sourceMappingURL=tag.service.js.map