import { Request, Response } from 'express';
import { TagService } from '../services/tag.service';
export declare class TagController {
    private service;
    constructor(service: TagService);
    create: (req: Request, res: Response, next: import("express").NextFunction) => void;
    list: (req: Request, res: Response, next: import("express").NextFunction) => void;
    update: (req: Request, res: Response, next: import("express").NextFunction) => void;
    delete: (req: Request, res: Response, next: import("express").NextFunction) => void;
    addToContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
    removeFromContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=tag.controller.d.ts.map