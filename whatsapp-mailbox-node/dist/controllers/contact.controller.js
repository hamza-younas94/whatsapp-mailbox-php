"use strict";
// src/controllers/contact.controller.ts
// Contact HTTP request handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.ContactController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
const auth_helpers_1 = require("../utils/auth-helpers");
class ContactController {
    constructor(contactService) {
        this.contactService = contactService;
        this.createContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { phoneNumber, name, email, tags } = req.body;
            const userId = (0, auth_helpers_1.requireUserId)(req);
            const input = {
                phoneNumber,
                name,
                email,
                tags,
            };
            const contact = await this.contactService.createContact(userId, input);
            res.status(201).json({
                success: true,
                data: contact,
            });
        });
        this.getContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId } = req.params;
            const contact = await this.contactService.getContact(contactId);
            res.status(200).json({
                success: true,
                data: contact,
            });
        });
        this.searchContacts = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { search, tags, isBlocked, limit = 20, offset = 0 } = req.query;
            const userId = (0, auth_helpers_1.requireUserId)(req);
            const filters = {
                query: search,
                tags: tags ? (Array.isArray(tags) ? tags : [tags]) : undefined,
                isBlocked: isBlocked === 'true',
            };
            const result = await this.contactService.searchContacts(userId, filters);
            res.status(200).json({
                success: true,
                data: result,
            });
        });
        this.updateContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId } = req.params;
            const { name, email } = req.body;
            const contact = await this.contactService.updateContact(contactId, {
                name,
                email,
            });
            res.status(200).json({
                success: true,
                data: contact,
            });
        });
        this.deleteContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId } = req.params;
            await this.contactService.deleteContact(contactId);
            res.status(200).json({
                success: true,
                message: 'Contact deleted',
            });
        });
        this.blockContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId } = req.params;
            const contact = await this.contactService.blockContact(contactId);
            res.status(200).json({
                success: true,
                data: contact,
            });
        });
    }
}
exports.ContactController = ContactController;
//# sourceMappingURL=contact.controller.js.map