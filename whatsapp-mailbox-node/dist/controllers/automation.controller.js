"use strict";
// src/controllers/automation.controller.ts
// Automation HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.AutomationController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class AutomationController {
    constructor(service) {
        this.service = service;
        this.create = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const automation = await this.service.createAutomation(userId, req.body);
            res.status(201).json({
                success: true,
                data: automation,
            });
        });
        this.list = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const automations = await this.service.getAutomations(userId);
            res.status(200).json({
                success: true,
                data: automations,
            });
        });
        this.update = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const automation = await this.service.updateAutomation(id, req.body);
            res.status(200).json({
                success: true,
                data: automation,
            });
        });
        this.delete = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            await this.service.deleteAutomation(id);
            res.status(200).json({
                success: true,
                message: 'Automation deleted',
            });
        });
        this.toggle = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const { isActive } = req.body;
            const automation = await this.service.toggleAutomation(id, isActive);
            res.status(200).json({
                success: true,
                data: automation,
            });
        });
    }
}
exports.AutomationController = AutomationController;
//# sourceMappingURL=automation.controller.js.map