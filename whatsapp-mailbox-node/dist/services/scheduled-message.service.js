"use strict";
// src/services/scheduled-message.service.ts
// Scheduled message management with queue
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.ScheduledMessageService = void 0;
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
class ScheduledMessageService {
    constructor(messageRepository, whatsAppService, prisma) {
        this.messageRepository = messageRepository;
        this.whatsAppService = whatsAppService;
        this.prisma = prisma;
    }
    async scheduleMessage(userId, input) {
        if (input.scheduledAt <= new Date()) {
            throw new Error('Scheduled time must be in the future');
        }
        const message = await this.messageRepository.create({
            userId,
            contactId: input.contactId,
            conversationId: input.contactId, // Simplified
            content: input.content,
            messageType: 'TEXT',
            direction: 'OUTGOING',
            status: 'PENDING',
            scheduledAt: input.scheduledAt,
            mediaUrl: input.mediaUrl,
        });
        logger_1.default.info({ id: message.id, scheduledAt: input.scheduledAt }, 'Message scheduled');
        return message;
    }
    async getScheduledMessages(userId) {
        return this.prisma.message.findMany({
            where: {
                userId,
                status: 'PENDING',
                scheduledAt: { not: null },
            },
            orderBy: { scheduledAt: 'asc' },
        });
    }
    async cancelScheduledMessage(messageId) {
        const message = await this.messageRepository.findById(messageId);
        if (!message) {
            throw new errors_1.NotFoundError('Message');
        }
        if (message.status !== 'PENDING') {
            throw new Error('Can only cancel pending messages');
        }
        await this.messageRepository.delete(messageId);
        logger_1.default.info({ messageId }, 'Scheduled message cancelled');
    }
    async processDueMessages() {
        const now = new Date();
        // Find messages that are due
        const dueMessages = await this.prisma.message.findMany({
            where: {
                status: 'PENDING',
                scheduledAt: { lte: now },
            },
            take: 100, // Process 100 at a time
        });
        for (const message of dueMessages) {
            try {
                // Send via WhatsApp
                const result = await this.whatsAppService.sendMessage(message.contactId, message.content || '', message.mediaUrl || undefined);
                // Update message status
                await this.messageRepository.update(message.id, {
                    status: 'SENT',
                    waMessageId: result.messageId,
                    sendAt: new Date(),
                });
                logger_1.default.info({ messageId: message.id }, 'Scheduled message sent');
            }
            catch (error) {
                logger_1.default.error({ messageId: message.id, error }, 'Failed to send scheduled message');
                await this.messageRepository.update(message.id, { status: 'FAILED' });
            }
        }
    }
}
exports.ScheduledMessageService = ScheduledMessageService;
//# sourceMappingURL=scheduled-message.service.js.map