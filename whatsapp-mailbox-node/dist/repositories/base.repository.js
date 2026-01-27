"use strict";
// src/repositories/base.repository.ts
// Abstract base repository implementing Repository pattern
// Principle: Abstraction, Dependency Inversion (SOLID)
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.BaseRepository = void 0;
const logger_1 = __importDefault(require("../utils/logger"));
class BaseRepository {
    constructor(prisma) {
        this.prisma = prisma;
    }
    get model() {
        return this.prisma[this.modelName];
    }
    async findById(id) {
        try {
            return await this.model.findUnique({
                where: { id },
            });
        }
        catch (error) {
            logger_1.default.error({ id, error }, `Failed to find ${String(this.modelName)}`);
            throw error;
        }
    }
    async findAll(skip = 0, take = 10) {
        try {
            return await this.model.findMany({
                skip,
                take: Math.min(take, 100), // Max 100 items per query
            });
        }
        catch (error) {
            logger_1.default.error({ error }, `Failed to find all ${String(this.modelName)}`);
            throw error;
        }
    }
    async create(data) {
        try {
            return await this.model.create({
                data,
            });
        }
        catch (error) {
            logger_1.default.error({ data, error }, `Failed to create ${String(this.modelName)}`);
            throw error;
        }
    }
    async update(id, data) {
        try {
            return await this.model.update({
                where: { id },
                data,
            });
        }
        catch (error) {
            logger_1.default.error({ id, data, error }, `Failed to update ${String(this.modelName)}`);
            throw error;
        }
    }
    async delete(id) {
        try {
            return await this.model.delete({
                where: { id },
            });
        }
        catch (error) {
            logger_1.default.error({ id, error }, `Failed to delete ${String(this.modelName)}`);
            throw error;
        }
    }
    async count(where) {
        try {
            return await this.model.count({ where });
        }
        catch (error) {
            logger_1.default.error({ error }, `Failed to count ${String(this.modelName)}`);
            throw error;
        }
    }
}
exports.BaseRepository = BaseRepository;
//# sourceMappingURL=base.repository.js.map