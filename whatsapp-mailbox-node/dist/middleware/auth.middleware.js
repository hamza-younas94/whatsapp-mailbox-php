"use strict";
// src/middleware/auth.middleware.ts
// JWT authentication and authorization
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.authMiddleware = authMiddleware;
exports.authenticate = authMiddleware;
exports.requireRole = requireRole;
exports.generateToken = generateToken;
const jsonwebtoken_1 = __importDefault(require("jsonwebtoken"));
const env_1 = require("../config/env");
const errors_1 = require("../utils/errors");
function authMiddleware(req, _res, next) {
    try {
        const token = extractToken(req);
        if (!token) {
            throw new errors_1.UnauthorizedError('Missing or invalid token');
        }
        const payload = jsonwebtoken_1.default.verify(token, (0, env_1.getEnv)().JWT_SECRET);
        req.user = payload;
        next();
    }
    catch (error) {
        if (error instanceof jsonwebtoken_1.default.JsonWebTokenError) {
            throw new errors_1.UnauthorizedError('Invalid token');
        }
        throw error;
    }
}
function requireRole(...roles) {
    return (req, _res, next) => {
        if (!req.user) {
            throw new errors_1.UnauthorizedError();
        }
        if (!roles.includes(req.user.role)) {
            throw new errors_1.ForbiddenError(`Requires one of: ${roles.join(', ')}`);
        }
        next();
    };
}
function extractToken(req) {
    const authHeader = req.headers.authorization;
    if (!authHeader)
        return null;
    const parts = authHeader.split(' ');
    if (parts.length !== 2 || parts[0] !== 'Bearer') {
        return null;
    }
    return parts[1];
}
function generateToken(payload) {
    const env = (0, env_1.getEnv)();
    return jsonwebtoken_1.default.sign(payload, env.JWT_SECRET, {
        expiresIn: env.JWT_EXPIRY || '7d',
    });
}
//# sourceMappingURL=auth.middleware.js.map