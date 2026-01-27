import { Request, Response, NextFunction } from 'express';
import { ZodSchema } from 'zod';
export declare function validate(schema: ZodSchema): (req: Request, _res: Response, next: NextFunction) => void;
export declare function validateQuery(schema: ZodSchema): (req: Request, _res: Response, next: NextFunction) => void;
export { validate as validateRequest };
//# sourceMappingURL=validation.middleware.d.ts.map