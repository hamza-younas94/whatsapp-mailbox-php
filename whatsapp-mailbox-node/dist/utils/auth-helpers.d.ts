import { Request } from 'express';
/**
 * Get userId from authenticated request
 * Supports both old tokens (userId only) and new tokens (id + userId)
 */
export declare function getUserId(req: Request): string | undefined;
/**
 * Get userId and throw if not found
 */
export declare function requireUserId(req: Request): string;
//# sourceMappingURL=auth-helpers.d.ts.map