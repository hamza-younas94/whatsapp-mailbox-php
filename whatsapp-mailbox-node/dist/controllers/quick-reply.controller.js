"use strict";
// src/controllers/quick-reply.controller.ts
// Quick reply HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.QuickReplyController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class QuickReplyController {
    constructor(service) {
        this.service = service;
        this.create = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const quickReply = await this.service.createQuickReply(userId, req.body);
            res.status(201).json({
                success: true,
                data: quickReply,
            });
        });
        this.list = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const quickReplies = await this.service.getQuickReplies(userId);
            res.status(200).json({
                success: true,
                data: quickReplies,
            });
        });
        this.search = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const { q } = req.query;
            const results = await this.service.searchQuickReplies(userId, q);
            res.status(200).json({
                success: true,
                data: results,
            });
        });
        this.update = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const quickReply = await this.service.updateQuickReply(id, req.body);
            res.status(200).json({
                success: true,
                data: quickReply,
            });
        });
        this.delete = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            await this.service.deleteQuickReply(id);
            res.status(200).json({
                success: true,
                message: 'Quick reply deleted',
            });
        });
    }
}
exports.QuickReplyController = QuickReplyController;
//# sourceMappingURL=quick-reply.controller.js.map