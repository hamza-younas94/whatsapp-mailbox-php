"use strict";
// src/routes/tags.ts
// Tags API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const tag_controller_1 = require("../controllers/tag.controller");
const tag_service_1 = require("../services/tag.service");
const tag_repository_1 = require("../repositories/tag.repository");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const repository = new tag_repository_1.TagRepository(prisma);
const service = new tag_service_1.TagService(repository);
const controller = new tag_controller_1.TagController(service);
// Validation schemas
const createTagSchema = zod_1.z.object({
    name: zod_1.z.string().min(1).max(50),
    color: zod_1.z.string().regex(/^#[0-9A-Fa-f]{6}$/).optional(),
});
const addTagToContactSchema = zod_1.z.object({
    contactId: zod_1.z.string().cuid(),
    tagId: zod_1.z.string().cuid(),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createTagSchema), controller.create);
router.get('/', controller.list);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
router.post('/contacts', (0, validation_middleware_1.validateRequest)(addTagToContactSchema), controller.addToContact);
router.delete('/contacts/:contactId/:tagId', controller.removeFromContact);
exports.default = router;
//# sourceMappingURL=tags.js.map