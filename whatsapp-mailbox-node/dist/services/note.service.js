"use strict";
// src/services/note.service.ts
// Contact notes management
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.NoteService = void 0;
const logger_1 = __importDefault(require("../utils/logger"));
const errors_1 = require("../utils/errors");
class NoteService {
    constructor(prisma) {
        this.prisma = prisma;
    }
    async createNote(userId, data) {
        // Verify contact exists and belongs to user
        const contact = await this.prisma.contact.findFirst({
            where: { id: data.contactId, userId },
        });
        if (!contact) {
            throw new errors_1.NotFoundError('Contact not found');
        }
        const note = await this.prisma.note.create({
            data: {
                userId,
                contactId: data.contactId,
                content: data.content,
            },
        });
        logger_1.default.info({ noteId: note.id, contactId: data.contactId }, 'Note created');
        return note;
    }
    async getNotes(contactId) {
        return this.prisma.note.findMany({
            where: { contactId },
            orderBy: { createdAt: 'desc' },
            include: {
                user: {
                    select: {
                        id: true,
                        name: true,
                        email: true,
                    },
                },
            },
        });
    }
    async updateNote(noteId, data) {
        const note = await this.prisma.note.findUnique({ where: { id: noteId } });
        if (!note) {
            throw new errors_1.NotFoundError('Note not found');
        }
        const updated = await this.prisma.note.update({
            where: { id: noteId },
            data: {
                content: data.content,
            },
        });
        logger_1.default.info({ noteId }, 'Note updated');
        return updated;
    }
    async deleteNote(noteId) {
        const note = await this.prisma.note.findUnique({ where: { id: noteId } });
        if (!note) {
            throw new errors_1.NotFoundError('Note not found');
        }
        await this.prisma.note.delete({ where: { id: noteId } });
        logger_1.default.info({ noteId }, 'Note deleted');
    }
}
exports.NoteService = NoteService;
//# sourceMappingURL=note.service.js.map