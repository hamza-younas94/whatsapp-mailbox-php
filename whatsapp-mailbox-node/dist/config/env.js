"use strict";
// src/config/env.ts
// Environment variable validation using Zod
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.loadEnv = loadEnv;
exports.getEnv = getEnv;
const zod_1 = require("zod");
const logger_1 = __importDefault(require("../utils/logger"));
const envSchema = zod_1.z.object({
    NODE_ENV: zod_1.z.enum(['development', 'production', 'test']).default('development'),
    PORT: zod_1.z.coerce.number().default(3000),
    APP_URL: zod_1.z.string().url(),
    DATABASE_URL: zod_1.z.string().url(),
    // JWT
    JWT_SECRET: zod_1.z.string().min(32, 'JWT_SECRET must be at least 32 characters'),
    JWT_EXPIRY: zod_1.z.string().default('7d'),
    // WhatsApp API
    WHATSAPP_ACCESS_TOKEN: zod_1.z.string(),
    WHATSAPP_PHONE_NUMBER_ID: zod_1.z.string(),
    WEBHOOK_VERIFY_TOKEN: zod_1.z.string(),
    // Redis
    REDIS_URL: zod_1.z.string().url().default('redis://localhost:6379'),
    REDIS_DB: zod_1.z.coerce.number().default(0),
    // Logging
    LOG_LEVEL: zod_1.z.enum(['debug', 'info', 'warn', 'error']).default('info'),
    LOG_FORMAT: zod_1.z.enum(['json', 'pretty']).default('json'),
    // CORS
    CORS_ORIGIN: zod_1.z.string().default('*'),
    // Rate Limiting
    RATE_LIMIT_WINDOW_MS: zod_1.z.coerce.number().default(900000),
    RATE_LIMIT_MAX_REQUESTS: zod_1.z.coerce.number().default(100),
});
let env;
function loadEnv() {
    try {
        env = envSchema.parse(process.env);
        logger_1.default.info('Environment variables validated');
        return env;
    }
    catch (error) {
        if (error instanceof zod_1.z.ZodError) {
            const messages = error.errors.map((e) => `${e.path.join('.')}: ${e.message}`);
            logger_1.default.error(messages, 'Environment validation failed');
            process.exit(1);
        }
        throw error;
    }
}
function getEnv() {
    if (!env) {
        return loadEnv();
    }
    return env;
}
exports.default = getEnv;
//# sourceMappingURL=env.js.map