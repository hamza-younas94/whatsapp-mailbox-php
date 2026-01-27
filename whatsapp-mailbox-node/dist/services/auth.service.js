"use strict";
// src/services/auth.service.ts
// Authentication service
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.AuthService = void 0;
const bcryptjs_1 = __importDefault(require("bcryptjs"));
const jsonwebtoken_1 = __importDefault(require("jsonwebtoken"));
const env_1 = require("../config/env");
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class AuthService {
    constructor(prisma) {
        this.prisma = prisma;
    }
    async register(data) {
        const env = (0, env_1.getEnv)();
        // Check if user exists
        const existingUser = await this.prisma.user.findFirst({
            where: {
                OR: [{ email: data.email }, { username: data.username }],
            },
        });
        if (existingUser) {
            throw new errors_1.ValidationError('User with this email or username already exists');
        }
        // Hash password
        const passwordHash = await bcryptjs_1.default.hash(data.password, 10);
        // Create user
        const user = await this.prisma.user.create({
            data: {
                email: data.email,
                username: data.username,
                passwordHash,
                name: data.name,
                role: 'USER',
                isActive: true,
            },
        });
        logger_1.default.info({ userId: user.id, email: user.email }, 'User registered');
        // Generate tokens with full user info
        const token = this.generateToken(user.id, user.email, user.role, '24h');
        const refreshToken = this.generateToken(user.id, user.email, user.role, '7d');
        // Remove password from response
        const { passwordHash: _, ...userWithoutPassword } = user;
        return {
            user: userWithoutPassword,
            token,
            refreshToken,
        };
    }
    async login(data) {
        // Find user
        const user = await this.prisma.user.findUnique({
            where: { email: data.email },
        });
        if (!user) {
            throw new errors_1.UnauthorizedError('Invalid credentials');
        }
        // Check if user is active
        if (!user.isActive) {
            throw new errors_1.UnauthorizedError('Account is disabled');
        }
        // Verify password
        const isValidPassword = await bcryptjs_1.default.compare(data.password, user.passwordHash);
        if (!isValidPassword) {
            throw new errors_1.UnauthorizedError('Invalid credentials');
        }
        // Update last login
        await this.prisma.user.update({
            where: { id: user.id },
            data: { lastLoginAt: new Date() },
        });
        logger_1.default.info({ userId: user.id, email: user.email }, 'User logged in');
        // Generate tokens with full user info
        const token = this.generateToken(user.id, user.email, user.role, '24h');
        const refreshToken = this.generateToken(user.id, user.email, user.role, '7d');
        // Remove password from response
        const { passwordHash: _, ...userWithoutPassword } = user;
        return {
            user: userWithoutPassword,
            token,
            refreshToken,
        };
    }
    async refreshToken(refreshToken) {
        try {
            const env = (0, env_1.getEnv)();
            const decoded = jsonwebtoken_1.default.verify(refreshToken, env.JWT_SECRET);
            const user = await this.prisma.user.findUnique({
                where: { id: decoded.id || decoded.userId },
            });
            if (!user || !user.isActive) {
                throw new errors_1.UnauthorizedError('Invalid refresh token');
            }
            const newToken = this.generateToken(user.id, user.email, user.role, '24h');
            return { token: newToken };
        }
        catch (error) {
            throw new errors_1.UnauthorizedError('Invalid refresh token');
        }
    }
    async verifyToken(token) {
        try {
            const env = (0, env_1.getEnv)();
            const decoded = jsonwebtoken_1.default.verify(token, env.JWT_SECRET);
            const user = await this.prisma.user.findUnique({
                where: { id: decoded.id || decoded.userId },
            });
            if (!user || !user.isActive) {
                throw new errors_1.UnauthorizedError('Invalid token');
            }
            return user;
        }
        catch (error) {
            throw new errors_1.UnauthorizedError('Invalid token');
        }
    }
    generateToken(userId, email, role, expiresIn) {
        const env = (0, env_1.getEnv)();
        return jsonwebtoken_1.default.sign({ userId, id: userId, email, role }, env.JWT_SECRET, { expiresIn });
    }
}
exports.AuthService = AuthService;
//# sourceMappingURL=auth.service.js.map