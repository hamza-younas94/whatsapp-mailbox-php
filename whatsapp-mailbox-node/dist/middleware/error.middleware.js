"use strict";
// src/middleware/error.middleware.ts
// Global error handling middleware
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.setupErrorMiddleware = setupErrorMiddleware;
exports.asyncHandler = asyncHandler;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
function setupErrorMiddleware(app) {
    // 404 handler
    app.use((_req, res) => {
        res.status(404).json({
            error: {
                code: 'NOT_FOUND',
                message: 'Resource not found',
            },
        });
    });
    // Global error handler (must be last)
    app.use((error, _req, res, _next) => {
        logger_1.default.error({ error: error.message, stack: error.stack }, 'Unhandled error');
        if (error instanceof errors_1.AppError) {
            return res.status(error.statusCode).json({
                error: {
                    code: error.code,
                    message: error.message,
                },
            });
        }
        // Default error response
        res.status(500).json({
            error: {
                code: 'INTERNAL_SERVER_ERROR',
                message: 'An unexpected error occurred',
            },
        });
    });
}
function asyncHandler(fn) {
    return (req, res, next) => {
        Promise.resolve(fn(req, res, next)).catch(next);
    };
}
//# sourceMappingURL=error.middleware.js.map