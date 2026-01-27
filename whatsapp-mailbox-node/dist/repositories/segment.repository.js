"use strict";
// src/repositories/segment.repository.ts
// Segment data access
Object.defineProperty(exports, "__esModule", { value: true });
exports.SegmentRepository = void 0;
const base_repository_1 = require("./base.repository");
class SegmentRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'segment';
    }
    async findByUserId(userId) {
        return this.prisma.segment.findMany({
            where: { userId },
            orderBy: { createdAt: 'desc' },
        });
    }
    async evaluateSegment(segmentId) {
        const segment = await this.prisma.segment.findUnique({
            where: { id: segmentId },
        });
        if (!segment)
            return [];
        const criteria = segment.criteria;
        // Build dynamic where clause from criteria
        const where = {
            userId: segment.userId,
            ...(criteria.tags && { tags: { some: { tag: { name: { in: criteria.tags } } } } }),
            ...(criteria.isBlocked !== undefined && { isBlocked: criteria.isBlocked }),
        };
        const contacts = await this.prisma.contact.findMany({
            where,
            select: { id: true },
        });
        return contacts.map((c) => c.id);
    }
}
exports.SegmentRepository = SegmentRepository;
//# sourceMappingURL=segment.repository.js.map