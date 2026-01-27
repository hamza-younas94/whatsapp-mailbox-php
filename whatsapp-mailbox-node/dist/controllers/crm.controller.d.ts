import { Request, Response } from 'express';
import { CRMService } from '../services/crm.service';
export declare class CRMController {
    private service;
    constructor(service: CRMService);
    createDeal: (req: Request, res: Response, next: import("express").NextFunction) => void;
    listDeals: (req: Request, res: Response, next: import("express").NextFunction) => void;
    updateDeal: (req: Request, res: Response, next: import("express").NextFunction) => void;
    moveStage: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getStats: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=crm.controller.d.ts.map