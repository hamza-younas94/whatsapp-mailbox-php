"use strict";
// src/routes/contacts.ts
// Contact API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.createContactRoutes = createContactRoutes;
const express_1 = require("express");
const zod_1 = require("zod");
const contact_controller_1 = require("../controllers/contact.controller");
const contact_service_1 = require("../services/contact.service");
const contact_repository_1 = require("../repositories/contact.repository");
const tag_repository_1 = require("../repositories/tag.repository");
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const database_1 = __importDefault(require("../config/database"));
function createContactRoutes() {
    const router = (0, express_1.Router)();
    const prisma = (0, database_1.default)();
    const contactRepository = new contact_repository_1.ContactRepository(prisma);
    const tagRepository = new tag_repository_1.TagRepository(prisma);
    const contactService = new contact_service_1.ContactService(contactRepository, tagRepository);
    const controller = new contact_controller_1.ContactController(contactService);
    // Validation schemas
    const createContactSchema = zod_1.z.object({
        phoneNumber: zod_1.z.string().regex(/^\+?[1-9]\d{1,14}$/),
        name: zod_1.z.string().optional(),
        email: zod_1.z.string().email().optional(),
        tags: zod_1.z.array(zod_1.z.string()).optional(),
    });
    const updateContactSchema = zod_1.z.object({
        name: zod_1.z.string().optional(),
        email: zod_1.z.string().email().optional(),
    });
    const searchContactsSchema = zod_1.z.object({
        search: zod_1.z.string().optional(),
        tags: zod_1.z.union([zod_1.z.string(), zod_1.z.array(zod_1.z.string())]).optional(),
        isBlocked: zod_1.z.enum(['true', 'false']).optional(),
        limit: zod_1.z.coerce.number().min(1).max(100).optional(),
        offset: zod_1.z.coerce.number().min(0).optional(),
    });
    // Routes
    router.get('/', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validateQuery)(searchContactsSchema), controller.searchContacts);
    router.post('/', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validate)(createContactSchema), controller.createContact);
    router.get('/search', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validateQuery)(searchContactsSchema), controller.searchContacts);
    router.get('/:contactId', auth_middleware_1.authMiddleware, controller.getContact);
    router.put('/:contactId', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validate)(updateContactSchema), controller.updateContact);
    router.delete('/:contactId', auth_middleware_1.authMiddleware, controller.deleteContact);
    router.post('/:contactId/block', auth_middleware_1.authMiddleware, controller.blockContact);
    return router;
}
//# sourceMappingURL=contacts.js.map