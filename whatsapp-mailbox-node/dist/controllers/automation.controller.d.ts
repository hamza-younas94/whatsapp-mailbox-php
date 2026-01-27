import { Request, Response } from 'express';
import { AutomationService } from '../services/automation.service';
export declare class AutomationController {
    private service;
    constructor(service: AutomationService);
    create: (req: Request, res: Response, next: import("express").NextFunction) => void;
    list: (req: Request, res: Response, next: import("express").NextFunction) => void;
    update: (req: Request, res: Response, next: import("express").NextFunction) => void;
    delete: (req: Request, res: Response, next: import("express").NextFunction) => void;
    toggle: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=automation.controller.d.ts.map