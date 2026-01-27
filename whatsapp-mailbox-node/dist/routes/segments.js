"use strict";
// src/routes/segments.ts
// Segments API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const segment_controller_1 = require("../controllers/segment.controller");
const segment_service_1 = require("../services/segment.service");
const segment_repository_1 = require("../repositories/segment.repository");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const repository = new segment_repository_1.SegmentRepository(prisma);
const service = new segment_service_1.SegmentService(repository);
const controller = new segment_controller_1.SegmentController(service);
// Validation schemas
const createSegmentSchema = zod_1.z.object({
    name: zod_1.z.string().min(1),
    conditions: zod_1.z.record(zod_1.z.any()),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createSegmentSchema), controller.create);
router.get('/', controller.list);
router.get('/:id/contacts', controller.getContacts);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
exports.default = router;
//# sourceMappingURL=segments.js.map