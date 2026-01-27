import { PrismaClient, Tag } from '@prisma/client';
import { BaseRepository } from './base.repository';
export interface ITagRepository {
    findByUserId(userId: string): Promise<Tag[]>;
    findByName(userId: string, name: string): Promise<Tag | null>;
    addToContact(contactId: string, tagId: string): Promise<void>;
    removeFromContact(contactId: string, tagId: string): Promise<void>;
    getContactTags(contactId: string): Promise<Tag[]>;
}
export declare class TagRepository extends BaseRepository<Tag> implements ITagRepository {
    protected modelName: "tag";
    constructor(prisma: PrismaClient);
    findByUserId(userId: string): Promise<Tag[]>;
    findByName(userId: string, name: string): Promise<Tag | null>;
    addToContact(contactId: string, tagId: string): Promise<void>;
    removeFromContact(contactId: string, tagId: string): Promise<void>;
    getContactTags(contactId: string): Promise<Tag[]>;
}
//# sourceMappingURL=tag.repository.d.ts.map