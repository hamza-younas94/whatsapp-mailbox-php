"use strict";
// src/services/contact.service.ts
// Contact management business logic
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.ContactService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class ContactService {
    constructor(contactRepository, tagRepository) {
        this.contactRepository = contactRepository;
        this.tagRepository = tagRepository;
    }
    async createContact(userId, input) {
        try {
            // Validate phone number format
            const phonePattern = /^\+?[1-9]\d{1,14}$/; // E.164 format
            if (!phonePattern.test(input.phoneNumber)) {
                throw new errors_1.ValidationError('Invalid phone number format. Use E.164 format (e.g., +1234567890)');
            }
            // Check if contact already exists
            const existing = await this.contactRepository.findByPhoneNumber(userId, input.phoneNumber);
            if (existing) {
                throw new errors_1.ConflictError(`Contact with phone ${input.phoneNumber} already exists`);
            }
            const contact = await this.contactRepository.create({
                userId,
                phoneNumber: input.phoneNumber,
                name: input.name,
                email: input.email,
            });
            // Add tags if provided
            if (input.tags && input.tags.length > 0 && this.tagRepository) {
                for (const tagName of input.tags) {
                    let tag = await this.tagRepository.findByName(userId, tagName);
                    if (!tag) {
                        tag = await this.tagRepository.create({ userId, name: tagName });
                    }
                    await this.tagRepository.addToContact(contact.id, tag.id);
                }
            }
            logger_1.default.info({ contactId: contact.id }, 'Contact created');
            return contact;
        }
        catch (error) {
            logger_1.default.error({ input, error }, 'Failed to create contact');
            throw error;
        }
    }
    async getContact(id) {
        try {
            const contact = await this.contactRepository.findById(id);
            if (!contact) {
                throw new errors_1.NotFoundError('Contact');
            }
            return contact;
        }
        catch (error) {
            logger_1.default.error({ id, error }, 'Failed to get contact');
            throw error;
        }
    }
    async searchContacts(userId, filters) {
        try {
            return await this.contactRepository.search(userId, filters);
        }
        catch (error) {
            logger_1.default.error({ filters, error }, 'Failed to search contacts');
            throw error;
        }
    }
    async updateContact(id, data) {
        try {
            const contact = await this.contactRepository.findById(id);
            if (!contact) {
                throw new errors_1.NotFoundError('Contact');
            }
            // Validate email if provided
            if (data.email) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(data.email)) {
                    throw new errors_1.ValidationError('Invalid email format');
                }
            }
            return await this.contactRepository.update(id, data);
        }
        catch (error) {
            logger_1.default.error({ id, data, error }, 'Failed to update contact');
            throw error;
        }
    }
    async deleteContact(id) {
        try {
            await this.contactRepository.delete(id);
            logger_1.default.info({ id }, 'Contact deleted');
        }
        catch (error) {
            logger_1.default.error({ id, error }, 'Failed to delete contact');
            throw error;
        }
    }
    async blockContact(id) {
        try {
            return await this.contactRepository.update(id, { isBlocked: true });
        }
        catch (error) {
            logger_1.default.error({ id, error }, 'Failed to block contact');
            throw error;
        }
    }
    async unblockContact(id) {
        try {
            return await this.contactRepository.update(id, { isBlocked: false });
        }
        catch (error) {
            logger_1.default.error({ id, error }, 'Failed to unblock contact');
            throw error;
        }
    }
}
exports.ContactService = ContactService;
//# sourceMappingURL=contact.service.js.map