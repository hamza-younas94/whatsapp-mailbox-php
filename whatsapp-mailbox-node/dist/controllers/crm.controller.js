"use strict";
// src/controllers/crm.controller.ts
// CRM HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.CRMController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class CRMController {
    constructor(service) {
        this.service = service;
        this.createDeal = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const deal = await this.service.createDeal(userId, req.body);
            res.status(201).json({
                success: true,
                data: deal,
            });
        });
        this.listDeals = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const { status, stage } = req.query;
            const deals = await this.service.getDeals(userId, { status, stage });
            res.status(200).json({
                success: true,
                data: deals,
            });
        });
        this.updateDeal = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const deal = await this.service.updateDeal(id, req.body);
            res.status(200).json({
                success: true,
                data: deal,
            });
        });
        this.moveStage = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const { stage } = req.body;
            const deal = await this.service.moveDealToStage(id, stage);
            res.status(200).json({
                success: true,
                data: deal,
            });
        });
        this.getStats = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const stats = await this.service.getDealStats(userId);
            res.status(200).json({
                success: true,
                data: stats,
            });
        });
    }
}
exports.CRMController = CRMController;
//# sourceMappingURL=crm.controller.js.map