"use strict";
// src/controllers/tag.controller.ts
// Tag HTTP handlers
Object.defineProperty(exports, "__esModule", { value: true });
exports.TagController = void 0;
const error_middleware_1 = require("../middleware/error.middleware");
class TagController {
    constructor(service) {
        this.service = service;
        this.create = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const tag = await this.service.createTag(userId, req.body);
            res.status(201).json({
                success: true,
                data: tag,
            });
        });
        this.list = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const tags = await this.service.getTags(userId);
            res.status(200).json({
                success: true,
                data: tags,
            });
        });
        this.update = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            const tag = await this.service.updateTag(id, req.body);
            res.status(200).json({
                success: true,
                data: tag,
            });
        });
        this.delete = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { id } = req.params;
            await this.service.deleteTag(id);
            res.status(200).json({
                success: true,
                message: 'Tag deleted',
            });
        });
        this.addToContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId, tagId } = req.body;
            await this.service.addTagToContact(contactId, tagId);
            res.status(200).json({
                success: true,
                message: 'Tag added to contact',
            });
        });
        this.removeFromContact = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { contactId, tagId } = req.params;
            await this.service.removeTagFromContact(contactId, tagId);
            res.status(200).json({
                success: true,
                message: 'Tag removed from contact',
            });
        });
    }
}
exports.TagController = TagController;
//# sourceMappingURL=tag.controller.js.map