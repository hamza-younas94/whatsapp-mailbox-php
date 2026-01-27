"use strict";
// src/routes/auth.ts
// Authentication API routes
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const auth_controller_1 = require("../controllers/auth.controller");
const auth_service_1 = require("../services/auth.service");
const database_1 = __importDefault(require("../config/database"));
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
// Initialize dependencies
const prisma = (0, database_1.default)();
const service = new auth_service_1.AuthService(prisma);
const controller = new auth_controller_1.AuthController(service);
// Validation schemas
const registerSchema = zod_1.z.object({
    email: zod_1.z.string().email(),
    username: zod_1.z.string().min(3).max(50),
    password: zod_1.z.string().min(8),
    name: zod_1.z.string().optional(),
});
const loginSchema = zod_1.z.object({
    email: zod_1.z.string().email(),
    password: zod_1.z.string(),
});
const refreshSchema = zod_1.z.object({
    refreshToken: zod_1.z.string(),
});
// Routes
router.post('/register', (0, validation_middleware_1.validateRequest)(registerSchema), controller.register);
router.post('/login', (0, validation_middleware_1.validateRequest)(loginSchema), controller.login);
router.post('/refresh', (0, validation_middleware_1.validateRequest)(refreshSchema), controller.refresh);
router.get('/me', auth_middleware_1.authenticate, controller.me);
exports.default = router;
//# sourceMappingURL=auth.js.map