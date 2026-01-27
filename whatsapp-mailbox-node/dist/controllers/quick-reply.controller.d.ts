import { Request, Response } from 'express';
import { QuickReplyService } from '../services/quick-reply.service';
export declare class QuickReplyController {
    private service;
    constructor(service: QuickReplyService);
    create: (req: Request, res: Response, next: import("express").NextFunction) => void;
    list: (req: Request, res: Response, next: import("express").NextFunction) => void;
    search: (req: Request, res: Response, next: import("express").NextFunction) => void;
    update: (req: Request, res: Response, next: import("express").NextFunction) => void;
    delete: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=quick-reply.controller.d.ts.map