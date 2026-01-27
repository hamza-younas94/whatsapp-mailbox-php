import { PrismaClient, Note } from '@prisma/client';
export interface CreateNoteData {
    contactId: string;
    content: string;
}
export interface UpdateNoteData {
    content?: string;
}
export interface INoteService {
    createNote(userId: string, data: CreateNoteData): Promise<Note>;
    getNotes(contactId: string): Promise<Note[]>;
    updateNote(noteId: string, data: UpdateNoteData): Promise<Note>;
    deleteNote(noteId: string): Promise<void>;
}
export declare class NoteService implements INoteService {
    private prisma;
    constructor(prisma: PrismaClient);
    createNote(userId: string, data: CreateNoteData): Promise<Note>;
    getNotes(contactId: string): Promise<Note[]>;
    updateNote(noteId: string, data: UpdateNoteData): Promise<Note>;
    deleteNote(noteId: string): Promise<void>;
}
//# sourceMappingURL=note.service.d.ts.map