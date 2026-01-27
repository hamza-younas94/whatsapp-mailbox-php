"use strict";
// src/routes/analytics.ts
// Analytics API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const analytics_controller_1 = require("../controllers/analytics.controller");
const analytics_service_1 = require("../services/analytics.service");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const service = new analytics_service_1.AnalyticsService(prisma);
const controller = new analytics_controller_1.AnalyticsController(service);
// Validation schemas
const statsSchema = zod_1.z.object({
    startDate: zod_1.z.string().datetime().optional(),
    endDate: zod_1.z.string().datetime().optional(),
});
const trendsSchema = zod_1.z.object({
    days: zod_1.z.string().regex(/^\d+$/).optional(),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.get('/stats', controller.getStats);
router.get('/overview', controller.getStats);
router.get('/trends', (0, validation_middleware_1.validateQuery)(trendsSchema), controller.getTrends);
router.get('/campaigns', controller.getCampaigns);
router.get('/top-contacts', controller.getTopContacts);
router.get('/export', controller.exportReport);
exports.default = router;
//# sourceMappingURL=analytics.js.map