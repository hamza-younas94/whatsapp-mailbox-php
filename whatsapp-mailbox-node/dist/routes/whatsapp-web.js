"use strict";
// src/routes/whatsapp-web.ts
// WhatsApp Web QR code routes
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = require("express");
const whatsapp_web_controller_1 = require("../controllers/whatsapp-web.controller");
const auth_middleware_1 = require("../middleware/auth.middleware");
const validation_middleware_1 = require("../middleware/validation.middleware");
const zod_1 = require("zod");
const router = (0, express_1.Router)();
const controller = new whatsapp_web_controller_1.WhatsAppWebController();
// Validation schemas
const sendMessageSchema = zod_1.z.object({
    to: zod_1.z.string().min(10),
    message: zod_1.z.string().min(1),
    mediaUrl: zod_1.z.string().url().optional(),
});
// Apply authentication to all routes
router.use(auth_middleware_1.authenticate);
// Simplified endpoints for primary session (used by frontend)
router.get('/status', controller.getDefaultStatus);
router.post('/initialize', controller.initializeDefaultSession);
router.post('/disconnect', controller.disconnectDefaultSession);
// Session management
router.post('/init', controller.initSession);
router.get('/sessions', controller.listSessions);
router.get('/sessions/:sessionId/status', controller.getStatus);
router.get('/sessions/:sessionId/qr', controller.getQRCode);
router.get('/sessions/:sessionId/qr/stream', controller.streamQR);
router.post('/sessions/:sessionId/restart', controller.restart);
router.delete('/sessions/:sessionId', controller.logout);
// Messaging
router.post('/sessions/:sessionId/send', (0, validation_middleware_1.validateRequest)(sendMessageSchema), controller.sendMessage);
exports.default = router;
//# sourceMappingURL=whatsapp-web.js.map