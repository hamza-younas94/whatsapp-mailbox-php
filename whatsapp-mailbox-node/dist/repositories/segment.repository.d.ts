import { PrismaClient, Segment } from '@prisma/client';
import { BaseRepository } from './base.repository';
export interface ISegmentRepository {
    findByUserId(userId: string): Promise<Segment[]>;
    evaluateSegment(segmentId: string): Promise<string[]>;
}
export declare class SegmentRepository extends BaseRepository<Segment> implements ISegmentRepository {
    protected modelName: "segment";
    constructor(prisma: PrismaClient);
    findByUserId(userId: string): Promise<Segment[]>;
    evaluateSegment(segmentId: string): Promise<string[]>;
}
//# sourceMappingURL=segment.repository.d.ts.map