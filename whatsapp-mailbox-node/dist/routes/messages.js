"use strict";
// src/routes/messages.ts
// Message API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.createMessageRoutes = createMessageRoutes;
const express_1 = require("express");
const zod_1 = require("zod");
const message_controller_1 = require("../controllers/message.controller");
const message_service_1 = require("../services/message.service");
const message_repository_1 = require("../repositories/message.repository");
const contact_repository_1 = require("../repositories/contact.repository");
const conversation_repository_1 = require("../repositories/conversation.repository");
const whatsapp_service_1 = require("../services/whatsapp.service");
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const database_1 = __importDefault(require("../config/database"));
function createMessageRoutes() {
    const router = (0, express_1.Router)();
    const prisma = (0, database_1.default)();
    const messageRepository = new message_repository_1.MessageRepository(prisma);
    const contactRepository = new contact_repository_1.ContactRepository(prisma);
    const conversationRepository = new conversation_repository_1.ConversationRepository(prisma);
    const whatsAppService = new whatsapp_service_1.WhatsAppService();
    const messageService = new message_service_1.MessageService(messageRepository, whatsAppService, contactRepository, conversationRepository);
    const controller = new message_controller_1.MessageController(messageService);
    // Validation schemas
    const sendMessageSchema = zod_1.z.object({
        contactId: zod_1.z.string().min(1).optional(), // CUID format, not UUID
        phoneNumber: zod_1.z.string().min(10).max(20).optional(), // More flexible: accept any phone format, normalize in service
        content: zod_1.z.string().max(4096).optional(),
        mediaUrl: zod_1.z.string().url().optional(),
    }).refine((data) => data.contactId || data.phoneNumber, {
        message: 'Either contactId or phoneNumber is required',
    }).refine((data) => data.content || data.mediaUrl, {
        message: 'Either content or mediaUrl is required',
    });
    const getMessagesSchema = zod_1.z.object({
        limit: zod_1.z.coerce.number().min(1).max(100).optional(),
        offset: zod_1.z.coerce.number().min(0).optional(),
    });
    const listMessagesSchema = zod_1.z.object({
        page: zod_1.z.coerce.number().min(1).optional(),
        limit: zod_1.z.coerce.number().min(1).max(100).optional(),
        search: zod_1.z.string().optional(),
        direction: zod_1.z.enum(['inbound', 'outbound']).optional(),
        status: zod_1.z.enum(['sent', 'delivered', 'read', 'failed', 'pending']).optional(),
    });
    // Routes
    router.get('/', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validateQuery)(listMessagesSchema), controller.listMessages);
    router.post('/', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validate)(sendMessageSchema), controller.sendMessage);
    router.get('/conversation/:conversationId', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validateQuery)(getMessagesSchema), controller.getMessages);
    router.get('/contact/:contactId', auth_middleware_1.authMiddleware, (0, validation_middleware_1.validateQuery)(getMessagesSchema), controller.getMessagesByContact);
    router.put('/:messageId/read', auth_middleware_1.authMiddleware, controller.markAsRead);
    router.delete('/:messageId', auth_middleware_1.authMiddleware, controller.deleteMessage);
    // Webhook endpoint (no auth required)
    router.post('/webhook', controller.webhookReceive);
    return router;
}
//# sourceMappingURL=messages.js.map