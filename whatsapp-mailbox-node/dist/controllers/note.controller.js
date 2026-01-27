"use strict";
// src/controllers/note.controller.ts
// Note HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.NoteController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class NoteController {
    constructor(service) {
        this.service = service;
        this.create = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const note = await this.service.createNote(userId, req.body);
            res.status(201).json({
                success: true,
                data: note,
            });
        });
        this.list = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId } = req.params;
            const notes = await this.service.getNotes(contactId);
            res.status(200).json({
                success: true,
                data: notes,
            });
        });
        this.update = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const note = await this.service.updateNote(id, req.body);
            res.status(200).json({
                success: true,
                data: note,
            });
        });
        this.delete = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            await this.service.deleteNote(id);
            res.status(200).json({
                success: true,
                message: 'Note deleted',
            });
        });
    }
}
exports.NoteController = NoteController;
//# sourceMappingURL=note.controller.js.map