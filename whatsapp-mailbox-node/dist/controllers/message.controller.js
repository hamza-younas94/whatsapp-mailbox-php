"use strict";
// src/controllers/message.controller.ts
// Message HTTP request handlers
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.MessageController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
const logger_1 = __importDefault(require("../utils/logger"));
const auth_helpers_1 = require("../utils/auth-helpers");
class MessageController {
    constructor(messageService) {
        this.messageService = messageService;
        this.listMessages = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = (0, auth_helpers_1.requireUserId)(req);
            const { page = 1, limit = 20, search, direction, status } = req.query;
            const filters = {
                query: search,
                direction: direction,
                status: status,
                limit: parseInt(limit),
                offset: (parseInt(page) - 1) * parseInt(limit),
            };
            const result = await this.messageService.listMessages(userId, filters);
            res.status(200).json({
                success: true,
                data: result.data,
                total: result.total,
                page: result.page,
                limit: result.limit,
            });
        });
        this.sendMessage = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId, phoneNumber, content, mediaUrl } = req.body;
            const userId = (0, auth_helpers_1.requireUserId)(req);
            const input = {
                phoneNumber,
                contactId,
                content,
                mediaUrl,
            };
            const message = await this.messageService.sendMessage(userId, input);
            res.status(201).json({
                success: true,
                data: message,
            });
        });
        this.getMessages = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { conversationId } = req.params;
            const { limit = 50, offset = 0 } = req.query;
            const userId = req.user.id;
            const result = await this.messageService.getMessages(userId, conversationId, parseInt(limit), parseInt(offset));
            res.status(200).json({
                success: true,
                data: result,
            });
        });
        this.getMessagesByContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId } = req.params;
            const { limit = 50, offset = 0 } = req.query;
            const userId = (0, auth_helpers_1.requireUserId)(req);
            const result = await this.messageService.getMessagesByContact(userId, contactId, parseInt(limit), parseInt(offset));
            res.status(200).json({
                success: true,
                data: result,
            });
        });
        this.markAsRead = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { messageId } = req.params;
            const message = await this.messageService.markAsRead(messageId);
            res.status(200).json({
                success: true,
                data: message,
            });
        });
        this.deleteMessage = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { messageId } = req.params;
            await this.messageService.deleteMessage(messageId);
            res.status(200).json({
                success: true,
                message: 'Message deleted',
            });
        });
        this.webhookReceive = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            // WhatsApp webhook handler
            const payload = req.body;
            logger_1.default.info({ payload }, 'Received webhook');
            // Verify webhook signature
            // ... verification logic
            // Process message
            if (payload.entry?.[0]?.changes?.[0]?.value?.messages?.[0]) {
                const message = payload.entry[0].changes[0].value.messages[0];
                // Handle message...
            }
            res.status(200).json({ success: true });
        });
    }
}
exports.MessageController = MessageController;
//# sourceMappingURL=message.controller.js.map