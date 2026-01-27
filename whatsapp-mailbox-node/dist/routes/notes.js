"use strict";
// src/routes/notes.ts
// Notes API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const note_controller_1 = require("../controllers/note.controller");
const note_service_1 = require("../services/note.service");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const service = new note_service_1.NoteService(prisma);
const controller = new note_controller_1.NoteController(service);
// Validation schemas
const createNoteSchema = zod_1.z.object({
    contactId: zod_1.z.string().cuid(),
    content: zod_1.z.string().min(1),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Routes
router.post('/', (0, validation_middleware_1.validateRequest)(createNoteSchema), controller.create);
router.get('/contact/:contactId', controller.list);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
exports.default = router;
//# sourceMappingURL=notes.js.map