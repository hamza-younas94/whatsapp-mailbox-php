"use strict";
// src/controllers/analytics.controller.ts
// Analytics HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.AnalyticsController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class AnalyticsController {
    constructor(service) {
        this.service = service;
        this.getStats = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const { startDate, endDate } = req.query;
            const stats = await this.service.getStats(userId, startDate ? new Date(startDate) : undefined, endDate ? new Date(endDate) : undefined);
            res.status(200).json({
                success: true,
                data: stats,
            });
        });
        this.getTrends = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const { days = 7 } = req.query;
            const trends = await this.service.getMessageTrends(userId, parseInt(days));
            res.status(200).json({
                success: true,
                data: trends,
            });
        });
        this.getCampaigns = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            res.status(200).json({
                success: true,
                data: [],
            });
        });
        this.getTopContacts = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            res.status(200).json({
                success: true,
                data: [],
            });
        });
        this.exportReport = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            res.setHeader('Content-Type', 'text/csv');
            res.setHeader('Content-Disposition', 'attachment; filename=report.csv');
            res.status(200).send('Date,Sent,Received\n');
        });
    }
}
exports.AnalyticsController = AnalyticsController;
//# sourceMappingURL=analytics.controller.js.map