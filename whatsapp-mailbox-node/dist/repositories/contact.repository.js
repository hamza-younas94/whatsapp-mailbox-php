"use strict";
// src/repositories/contact.repository.ts
// Contact data access layer
Object.defineProperty(exports, "__esModule", { value: true });
exports.ContactRepository = void 0;
const base_repository_1 = require("./base.repository");
class ContactRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'contact';
    }
    async findByPhoneNumber(userId, phoneNumber) {
        return this.prisma.contact.findUnique({
            where: { userId_phoneNumber: { userId, phoneNumber } },
            include: { tags: { include: { tag: true } } },
        });
    }
    async search(userId, filters) {
        const limit = Math.min(filters.limit || 20, 100);
        const offset = filters.offset || 0;
        const searchTerm = filters.search || filters.query;
        const where = {
            userId,
            isBlocked: filters.isBlocked ?? false,
            ...(searchTerm && {
                OR: [
                    { name: { contains: searchTerm } },
                    { phoneNumber: { contains: searchTerm } },
                    { email: { contains: searchTerm } },
                ],
            }),
            ...(filters.tags?.length && {
                tags: { some: { tag: { name: { in: filters.tags } } } },
            }),
        };
        const [contacts, total] = await Promise.all([
            this.prisma.contact.findMany({
                where,
                skip: offset,
                take: limit,
                include: {
                    tags: { include: { tag: true } },
                    _count: { select: { messages: true } },
                    messages: {
                        take: 1,
                        orderBy: { createdAt: 'desc' },
                        select: {
                            id: true,
                            content: true,
                            messageType: true,
                            direction: true,
                            createdAt: true,
                        },
                    },
                },
                orderBy: [
                    { lastMessageAt: 'desc' },
                    { createdAt: 'desc' }, // Fallback if lastMessageAt is null
                ],
            }),
            this.prisma.contact.count({ where }),
        ]);
        return {
            data: contacts,
            total,
            page: Math.floor(offset / limit) + 1,
            limit,
        };
    }
    async findOrCreate(userId, phoneNumber, data) {
        if (!userId) {
            throw new Error('userId is required for findOrCreate');
        }
        const updateData = {};
        const createData = { userId, phoneNumber };
        // Only include non-undefined fields
        if (data?.name) {
            updateData.name = data.name;
            createData.name = data.name;
        }
        if (data?.email) {
            updateData.email = data.email;
            createData.email = data.email;
        }
        return this.prisma.contact.upsert({
            where: { userId_phoneNumber: { userId, phoneNumber } },
            update: updateData,
            create: createData,
        });
    }
}
exports.ContactRepository = ContactRepository;
//# sourceMappingURL=contact.repository.js.map