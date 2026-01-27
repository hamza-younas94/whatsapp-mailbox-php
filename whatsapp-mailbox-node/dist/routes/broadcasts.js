"use strict";
// src/routes/broadcasts.ts
// Broadcasts API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const broadcast_controller_1 = require("../controllers/broadcast.controller");
const broadcast_service_1 = require("../services/broadcast.service");
const campaign_repository_1 = require("../repositories/campaign.repository");
const segment_repository_1 = require("../repositories/segment.repository");
const message_repository_1 = require("../repositories/message.repository");
const whatsapp_service_1 = require("../services/whatsapp.service");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const campaignRepo = new campaign_repository_1.CampaignRepository(prisma);
const segmentRepo = new segment_repository_1.SegmentRepository(prisma);
const messageRepo = new message_repository_1.MessageRepository(prisma);
const whatsappService = new whatsapp_service_1.WhatsAppService();
const service = new broadcast_service_1.BroadcastService(campaignRepo, segmentRepo, messageRepo);
const controller = new broadcast_controller_1.BroadcastController(service);
// Validation schemas
const createBroadcastSchema = zod_1.z.object({
    name: zod_1.z.string().min(1).max(100),
    message: zod_1.z.string().min(1),
    segmentId: zod_1.z.string().cuid().optional(),
    mediaUrl: zod_1.z.string().url().optional(),
    mediaType: zod_1.z.enum(['IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT']).optional(),
});
const scheduleSchema = zod_1.z.object({
    scheduleTime: zod_1.z.string().datetime(),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createBroadcastSchema), controller.create);
router.get('/', controller.list);
router.post('/:id/send', controller.send);
router.post('/:id/schedule', (0, validation_middleware_1.validateRequest)(scheduleSchema), controller.schedule);
router.post('/:id/cancel', controller.cancel);
exports.default = router;
//# sourceMappingURL=broadcasts.js.map