"use strict";
// src/routes/crm.ts
// CRM API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const crm_controller_1 = require("../controllers/crm.controller");
const crm_service_1 = require("../services/crm.service");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const service = new crm_service_1.CRMService(prisma);
const controller = new crm_controller_1.CRMController(service);
// Validation schemas
const createDealSchema = zod_1.z.object({
    title: zod_1.z.string().min(1),
    contactId: zod_1.z.string().cuid(),
    value: zod_1.z.number().optional(),
    stage: zod_1.z.string(),
    expectedCloseDate: zod_1.z.string().datetime().optional(),
    description: zod_1.z.string().optional(),
});
const moveDealSchema = zod_1.z.object({
    stage: zod_1.z.string(),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createDealSchema), controller.createDeal);
router.get('/', controller.listDeals);
router.put('/:id', controller.updateDeal);
router.patch('/:id/stage', (0, validation_middleware_1.validateRequest)(moveDealSchema), controller.moveStage);
router.get('/stats', controller.getStats);
exports.default = router;
//# sourceMappingURL=crm.js.map