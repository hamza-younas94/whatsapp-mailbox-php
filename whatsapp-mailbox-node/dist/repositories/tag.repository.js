"use strict";
// src/repositories/tag.repository.ts
// Tag data access
Object.defineProperty(exports, "__esModule", { value: true });
exports.TagRepository = void 0;
const base_repository_1 = require("./base.repository");
class TagRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'tag';
    }
    async findByUserId(userId) {
        return this.prisma.tag.findMany({
            where: { userId },
            include: { _count: { select: { contacts: true } } },
            orderBy: { name: 'asc' },
        });
    }
    async findByName(userId, name) {
        return this.prisma.tag.findUnique({
            where: { userId_name: { userId, name } },
        });
    }
    async addToContact(contactId, tagId) {
        await this.prisma.tagOnContact.create({
            data: { contactId, tagId },
        });
    }
    async removeFromContact(contactId, tagId) {
        await this.prisma.tagOnContact.delete({
            where: { contactId_tagId: { contactId, tagId } },
        });
    }
    async getContactTags(contactId) {
        const tags = await this.prisma.tagOnContact.findMany({
            where: { contactId },
            include: { tag: true },
        });
        return tags.map((t) => t.tag);
    }
}
exports.TagRepository = TagRepository;
//# sourceMappingURL=tag.repository.js.map