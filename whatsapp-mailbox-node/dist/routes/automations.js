"use strict";
// src/routes/automations.ts
// Automations API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const automation_controller_1 = require("../controllers/automation.controller");
const automation_service_1 = require("../services/automation.service");
const automation_repository_1 = require("../repositories/automation.repository");
const whatsapp_service_1 = require("../services/whatsapp.service");
const tag_service_1 = require("../services/tag.service");
const tag_repository_1 = require("../repositories/tag.repository");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const automationRepo = new automation_repository_1.AutomationRepository(prisma);
const tagRepo = new tag_repository_1.TagRepository(prisma);
const whatsappService = new whatsapp_service_1.WhatsAppService();
const tagService = new tag_service_1.TagService(tagRepo);
const service = new automation_service_1.AutomationService(automationRepo, {}, tagService);
const controller = new automation_controller_1.AutomationController(service);
// Validation schemas
const createAutomationSchema = zod_1.z.object({
    name: zod_1.z.string().min(1).max(100),
    triggerType: zod_1.z.enum(['MESSAGE_RECEIVED', 'KEYWORD', 'TAG_ADDED', 'SCHEDULE']),
    triggerValue: zod_1.z.string().optional(),
    conditions: zod_1.z.record(zod_1.z.any()).optional(),
    actions: zod_1.z.array(zod_1.z.object({
        type: zod_1.z.enum(['SEND_MESSAGE', 'ADD_TAG', 'REMOVE_TAG', 'WAIT', 'WEBHOOK']),
        value: zod_1.z.string().optional(),
        delay: zod_1.z.number().optional(),
    })),
    isActive: zod_1.z.boolean().default(true),
});
const toggleSchema = zod_1.z.object({
    isActive: zod_1.z.boolean(),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createAutomationSchema), controller.create);
router.get('/', controller.list);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
router.patch('/:id/toggle', (0, validation_middleware_1.validateRequest)(toggleSchema), controller.toggle);
exports.default = router;
//# sourceMappingURL=automations.js.map