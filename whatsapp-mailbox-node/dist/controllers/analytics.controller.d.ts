import { Request, Response } from 'express';
import { AnalyticsService } from '../services/analytics.service';
export declare class AnalyticsController {
    private service;
    constructor(service: AnalyticsService);
    getStats: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getTrends: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getCampaigns: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getTopContacts: (req: Request, res: Response, next: import("express").NextFunction) => void;
    exportReport: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=analytics.controller.d.ts.map