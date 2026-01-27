import { Request, Response } from 'express';
import { BroadcastService } from '../services/broadcast.service';
export declare class BroadcastController {
    private service;
    constructor(service: BroadcastService);
    create: (req: Request, res: Response, next: import("express").NextFunction) => void;
    list: (req: Request, res: Response, next: import("express").NextFunction) => void;
    send: (req: Request, res: Response, next: import("express").NextFunction) => void;
    schedule: (req: Request, res: Response, next: import("express").NextFunction) => void;
    cancel: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=broadcast.controller.d.ts.map