import { PrismaClient } from '@prisma/client';
export interface IRepository<T> {
    findById(id: string): Promise<T | null>;
    findAll(skip?: number, take?: number): Promise<T[]>;
    create(data: unknown): Promise<T>;
    update(id: string, data: unknown): Promise<T>;
    delete(id: string): Promise<T>;
}
export declare abstract class BaseRepository<T> implements IRepository<T> {
    protected prisma: PrismaClient;
    protected abstract modelName: keyof PrismaClient;
    constructor(prisma: PrismaClient);
    protected get model(): any;
    findById(id: string): Promise<T | null>;
    findAll(skip?: number, take?: number): Promise<T[]>;
    create(data: unknown): Promise<T>;
    update(id: string, data: unknown): Promise<T>;
    delete(id: string): Promise<T>;
    count(where?: unknown): Promise<number>;
}
//# sourceMappingURL=base.repository.d.ts.map