import { PrismaClient } from '@prisma/client';
export declare function getPrismaClient(): PrismaClient;
export declare function connectDatabase(): Promise<void>;
export declare function disconnectDatabase(): Promise<void>;
export default getPrismaClient;
//# sourceMappingURL=database.d.ts.map