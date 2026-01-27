import { Express, Request, Response, NextFunction } from 'express';
export declare function setupErrorMiddleware(app: Express): void;
export declare function asyncHandler(fn: Function): (req: Request, res: Response, next: NextFunction) => void;
//# sourceMappingURL=error.middleware.d.ts.map