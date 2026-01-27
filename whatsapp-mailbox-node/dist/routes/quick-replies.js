"use strict";
// src/routes/quick-replies.ts
// Quick replies API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const quick_reply_controller_1 = require("../controllers/quick-reply.controller");
const quick_reply_service_1 = require("../services/quick-reply.service");
const quick_reply_repository_1 = require("../repositories/quick-reply.repository");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const repository = new quick_reply_repository_1.QuickReplyRepository(prisma);
const service = new quick_reply_service_1.QuickReplyService(repository);
const controller = new quick_reply_controller_1.QuickReplyController(service);
// Validation schemas
const createQuickReplySchema = zod_1.z.object({
    shortcut: zod_1.z.string().min(1).max(50),
    message: zod_1.z.string().min(1),
    mediaUrl: zod_1.z.string().url().optional(),
    mediaType: zod_1.z.enum(['IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT']).optional(),
    category: zod_1.z.string().max(50).optional(),
    isActive: zod_1.z.boolean().optional(),
});
const searchSchema = zod_1.z.object({
    q: zod_1.z.string().min(1),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createQuickReplySchema), controller.create);
router.get('/', controller.list);
router.get('/search', (0, validation_middleware_1.validateRequest)(searchSchema), controller.search);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
exports.default = router;
//# sourceMappingURL=quick-replies.js.map