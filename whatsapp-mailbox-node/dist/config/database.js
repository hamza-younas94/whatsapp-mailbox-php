"use strict";
// src/config/database.ts
// Database configuration and Prisma client
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.getPrismaClient = getPrismaClient;
exports.connectDatabase = connectDatabase;
exports.disconnectDatabase = disconnectDatabase;
const client_1 = require("@prisma/client");
const logger_1 = __importDefault(require("../utils/logger"));
let prisma;
function getPrismaClient() {
    if (!prisma) {
        prisma = new client_1.PrismaClient({
            log: [
                { emit: 'event', level: 'query' },
                { emit: 'event', level: 'error' },
                { emit: 'event', level: 'warn' },
            ],
        });
        // Log queries in development
        prisma.$on('query', (e) => {
            if (process.env.NODE_ENV === 'development') {
                logger_1.default.debug({ query: e.query, params: e.params }, 'DB Query');
            }
        });
        prisma.$on('error', (e) => {
            logger_1.default.error({ error: e.message }, 'DB Error');
        });
        prisma.$on('warn', (e) => {
            logger_1.default.warn({ warning: e.message }, 'DB Warning');
        });
    }
    return prisma;
}
async function connectDatabase() {
    try {
        const client = getPrismaClient();
        await client.$connect();
        logger_1.default.info('Database connected');
    }
    catch (error) {
        logger_1.default.error(error, 'Database connection failed');
        process.exit(1);
    }
}
async function disconnectDatabase() {
    if (prisma) {
        await prisma.$disconnect();
        logger_1.default.info('Database disconnected');
    }
}
exports.default = getPrismaClient;
//# sourceMappingURL=database.js.map