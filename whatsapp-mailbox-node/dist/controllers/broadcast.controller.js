"use strict";
// src/controllers/broadcast.controller.ts
// Broadcast campaign HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.BroadcastController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class BroadcastController {
    constructor(service) {
        this.service = service;
        this.create = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const broadcast = await this.service.createBroadcast(userId, req.body);
            res.status(201).json({
                success: true,
                data: broadcast,
            });
        });
        this.list = (0, error_middleware_1.asyncHandler)(async (_req, res) => {
            const broadcasts = await this.service.getBroadcasts();
            res.status(200).json({
                success: true,
                data: broadcasts,
            });
        });
        this.send = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            await this.service.sendBroadcast(id);
            res.status(200).json({
                success: true,
                message: 'Broadcast sent',
            });
        });
        this.schedule = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const { scheduleTime } = req.body;
            const broadcast = await this.service.scheduleBroadcast(id, new Date(scheduleTime));
            res.status(200).json({
                success: true,
                data: broadcast,
            });
        });
        this.cancel = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const broadcast = await this.service.cancelBroadcast(id);
            res.status(200).json({
                success: true,
                data: broadcast,
            });
        });
    }
}
exports.BroadcastController = BroadcastController;
//# sourceMappingURL=broadcast.controller.js.map