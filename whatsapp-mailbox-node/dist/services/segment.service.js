"use strict";
// src/services/segment.service.ts
// Contact segmentation logic
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.SegmentService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class SegmentService {
    constructor(repository) {
        this.repository = repository;
    }
    async createSegment(userId, input) {
        try {
            const segment = await this.repository.create({
                userId,
                name: input.name,
                criteria: input.criteria,
            });
            logger_1.default.info({ id: segment.id }, 'Segment created');
            return segment;
        }
        catch (error) {
            logger_1.default.error({ input, error }, 'Failed to create segment');
            throw error;
        }
    }
    async getSegments(userId) {
        return this.repository.findByUserId(userId);
    }
    async updateSegment(id, data) {
        const existing = await this.repository.findById(id);
        if (!existing) {
            throw new errors_1.NotFoundError('Segment');
        }
        return this.repository.update(id, data);
    }
    async deleteSegment(id) {
        await this.repository.delete(id);
        logger_1.default.info({ id }, 'Segment deleted');
    }
    async getSegmentContacts(segmentId) {
        return this.evaluateSegment(segmentId).then(ids => ids.map(id => ({ id })));
    }
    async evaluateSegment(segmentId) {
        return this.repository.evaluateSegment(segmentId);
    }
}
exports.SegmentService = SegmentService;
//# sourceMappingURL=segment.service.js.map