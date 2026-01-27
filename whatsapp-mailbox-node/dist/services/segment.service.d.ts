import { Segment } from '@prisma/client';
import { SegmentRepository } from '../repositories/segment.repository';
export interface CreateSegmentInput {
    name: string;
    criteria: Record<string, any>;
}
export interface ISegmentService {
    createSegment(userId: string, input: CreateSegmentInput): Promise<Segment>;
    getSegments(userId: string): Promise<Segment[]>;
    updateSegment(id: string, data: Partial<Segment>): Promise<Segment>;
    deleteSegment(id: string): Promise<void>;
    evaluateSegment(segmentId: string): Promise<string[]>;
}
export declare class SegmentService implements ISegmentService {
    private repository;
    constructor(repository: SegmentRepository);
    createSegment(userId: string, input: CreateSegmentInput): Promise<Segment>;
    getSegments(userId: string): Promise<Segment[]>;
    updateSegment(id: string, data: Partial<Segment>): Promise<Segment>;
    deleteSegment(id: string): Promise<void>;
    getSegmentContacts(segmentId: string): Promise<any[]>;
    evaluateSegment(segmentId: string): Promise<string[]>;
}
//# sourceMappingURL=segment.service.d.ts.map