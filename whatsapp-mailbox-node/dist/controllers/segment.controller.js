"use strict";
// src/controllers/segment.controller.ts
// Segment HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.SegmentController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class SegmentController {
    constructor(service) {
        this.service = service;
        this.create = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const segment = await this.service.createSegment(userId, req.body);
            res.status(201).json({
                success: true,
                data: segment,
            });
        });
        this.list = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const segments = await this.service.getSegments(userId);
            res.status(200).json({
                success: true,
                data: segments,
            });
        });
        this.getContacts = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const contacts = await this.service.getSegmentContacts(id);
            res.status(200).json({
                success: true,
                data: contacts,
            });
        });
        this.update = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const segment = await this.service.updateSegment(id, req.body);
            res.status(200).json({
                success: true,
                data: segment,
            });
        });
        this.delete = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            await this.service.deleteSegment(id);
            res.status(200).json({
                success: true,
                message: 'Segment deleted',
            });
        });
    }
}
exports.SegmentController = SegmentController;
//# sourceMappingURL=segment.controller.js.map