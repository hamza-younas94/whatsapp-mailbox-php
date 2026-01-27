import { Request, Response, NextFunction } from 'express';
import { UserRole } from '@prisma/client';
export interface JwtPayload {
    userId: string;
    id: string;
    email: string;
    role: UserRole;
}
declare global {
    namespace Express {
        interface Request {
            user?: JwtPayload;
        }
    }
}
export declare function authMiddleware(req: Request, _res: Response, next: NextFunction): void;
export declare function requireRole(...roles: UserRole[]): (req: Request, _res: Response, next: NextFunction) => void;
export declare function generateToken(payload: JwtPayload): string;
export { authMiddleware as authenticate };
//# sourceMappingURL=auth.middleware.d.ts.map