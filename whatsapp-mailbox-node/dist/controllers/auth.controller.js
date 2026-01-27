"use strict";
// src/controllers/auth.controller.ts
// Authentication HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.AuthController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class AuthController {
    constructor(service) {
        this.service = service;
        this.register = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const result = await this.service.register(req.body);
            res.status(201).json({
                success: true,
                data: result,
            });
        });
        this.login = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const result = await this.service.login(req.body);
            res.status(200).json({
                success: true,
                data: result,
            });
        });
        this.refresh = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { refreshToken } = req.body;
            const result = await this.service.refreshToken(refreshToken);
            res.status(200).json({
                success: true,
                data: result,
            });
        });
        this.me = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            res.status(200).json({
                success: true,
                data: req.user,
            });
        });
    }
}
exports.AuthController = AuthController;
//# sourceMappingURL=auth.controller.js.map