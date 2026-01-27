import { Tag } from '@prisma/client';
import { TagRepository } from '../repositories/tag.repository';
export interface CreateTagInput {
    name: string;
    color?: string;
}
export interface ITagService {
    createTag(userId: string, input: CreateTagInput): Promise<Tag>;
    getTags(userId: string): Promise<Tag[]>;
    updateTag(id: string, data: Partial<Tag>): Promise<Tag>;
    deleteTag(id: string): Promise<void>;
    addTagToContact(contactId: string, tagId: string): Promise<void>;
    removeTagFromContact(contactId: string, tagId: string): Promise<void>;
    getContactTags(contactId: string): Promise<Tag[]>;
}
export declare class TagService implements ITagService {
    private repository;
    constructor(repository: TagRepository);
    createTag(userId: string, input: CreateTagInput): Promise<Tag>;
    getTags(userId: string): Promise<Tag[]>;
    updateTag(id: string, data: Partial<Tag>): Promise<Tag>;
    deleteTag(id: string): Promise<void>;
    addTagToContact(contactId: string, tagId: string): Promise<void>;
    removeTagFromContact(contactId: string, tagId: string): Promise<void>;
    getContactTags(contactId: string): Promise<Tag[]>;
}
//# sourceMappingURL=tag.service.d.ts.map