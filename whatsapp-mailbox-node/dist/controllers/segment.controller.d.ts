import { Request, Response } from 'express';
import { SegmentService } from '../services/segment.service';
export declare class SegmentController {
    private service;
    constructor(service: SegmentService);
    create: (req: Request, res: Response, next: import("express").NextFunction) => void;
    list: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getContacts: (req: Request, res: Response, next: import("express").NextFunction) => void;
    update: (req: Request, res: Response, next: import("express").NextFunction) => void;
    delete: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=segment.controller.d.ts.map