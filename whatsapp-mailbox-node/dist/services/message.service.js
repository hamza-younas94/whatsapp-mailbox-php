"use strict";
// src/services/message.service.ts
// Message business logic - Single Responsibility Principle
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.MessageService = void 0;
const client_1 = require("@prisma/client");
const whatsapp_web_js_1 = require("whatsapp-web.js");
const errors_1 = require("../utils/errors");
const logger_1 = __importDefault(require("../utils/logger"));
const whatsapp_web_service_1 = require("./whatsapp-web.service");
class MessageService {
    constructor(messageRepository, whatsAppService, contactRepository, conversationRepository) {
        this.messageRepository = messageRepository;
        this.whatsAppService = whatsAppService;
        this.contactRepository = contactRepository;
        this.conversationRepository = conversationRepository;
    }
    async sendMessage(userId, input) {
        try {
            const withTimeout = async (promise, ms, errorMsg) => {
                let timer;
                try {
                    return await Promise.race([
                        promise,
                        new Promise((_, reject) => {
                            timer = setTimeout(() => reject(new Error(errorMsg)), ms);
                        }),
                    ]);
                }
                finally {
                    clearTimeout(timer);
                }
            };
            const sanitizePhone = (raw) => {
                if (!raw)
                    return '';
                // Remove @domain if present (WhatsApp format)
                const base = raw.split('@')[0];
                // Remove all non-digit characters
                const digits = base.replace(/\D/g, '');
                // Return last 20 digits (max phone number length)
                // This handles numbers starting with 0, +, or country codes
                return digits.slice(-20);
            };
            // Validate userId is present
            if (!userId) {
                throw new errors_1.ValidationError('User ID is required - authentication failed');
            }
            // Validate input
            if (!input.content && !input.mediaUrl) {
                throw new errors_1.ValidationError('Message must have content or media');
            }
            if (input.content && input.content.length > 4096) {
                throw new errors_1.ValidationError('Message content exceeds 4096 characters');
            }
            // Resolve contactId (create if phoneNumber provided)
            let contactId = input.contactId;
            if (!contactId && input.phoneNumber) {
                const sanitizedPhone = sanitizePhone(input.phoneNumber);
                if (!sanitizedPhone || sanitizedPhone.length < 10) {
                    throw new errors_1.ValidationError(`Phone number is invalid. Got: "${input.phoneNumber}", sanitized: "${sanitizedPhone}". Please provide a valid phone number with at least 10 digits.`);
                }
                const contact = await this.contactRepository.findOrCreate(userId, sanitizedPhone, { name: sanitizedPhone });
                contactId = contact.id;
            }
            if (!contactId) {
                throw new errors_1.ValidationError('contactId or phoneNumber is required');
            }
            // Ensure conversation exists
            const conversation = await this.conversationRepository.findOrCreate(userId, contactId);
            // Create message in database (PENDING status)
            const messageTypeToUse = input.messageType || (input.mediaUrl ? client_1.MessageType.DOCUMENT : client_1.MessageType.TEXT);
            const message = await this.messageRepository.create({
                user: { connect: { id: userId } },
                contact: { connect: { id: contactId } },
                conversation: { connect: { id: conversation.id } },
                content: input.content,
                messageType: messageTypeToUse,
                direction: client_1.MessageDirection.OUTGOING,
                status: client_1.MessageStatus.PENDING,
                mediaUrl: input.mediaUrl,
                mediaType: input.mediaType?.toString(),
            });
            // Send via WhatsApp Web
            try {
                // Get user's active WhatsApp Web session
                const sessions = whatsapp_web_service_1.whatsappWebService.getUserSessions(userId);
                logger_1.default.info({
                    userId,
                    totalSessions: sessions.length,
                    sessionStatuses: sessions.map(s => ({ id: s.id, status: s.status }))
                }, 'Checking WhatsApp Web sessions');
                const activeSession = sessions.find((s) => s.status === 'READY' || s.status === 'AUTHENTICATED');
                if (!activeSession) {
                    const statusList = sessions.map(s => s.status).join(', ') || 'none';
                    throw new errors_1.ValidationError(`WhatsApp is not connected (statuses: ${statusList}). Please scan the QR code to connect.`);
                }
                // Check if client is actually ready
                const state = await activeSession.client.getState();
                logger_1.default.info({ state, sessionId: activeSession.id }, 'WhatsApp Web client state');
                if (state !== 'CONNECTED') {
                    throw new errors_1.ValidationError(`WhatsApp client is ${state}. Please reconnect.`);
                }
                // Get phone number - either from input or from contact
                let phoneNumberToUse = input.phoneNumber;
                if (!phoneNumberToUse && contactId) {
                    const contact = await this.contactRepository.findById(contactId);
                    if (!contact) {
                        throw new errors_1.ValidationError('Contact not found');
                    }
                    phoneNumberToUse = contact.phoneNumber;
                }
                if (!phoneNumberToUse) {
                    throw new errors_1.ValidationError('Phone number is required to send message');
                }
                // Format phone number
                const sanitizedPhone = sanitizePhone(phoneNumberToUse);
                if (!sanitizedPhone || sanitizedPhone.length < 10) {
                    throw new errors_1.ValidationError(`Phone number is invalid after sanitization. Got: "${phoneNumberToUse}", sanitized: "${sanitizedPhone}"`);
                }
                const formattedNumber = sanitizedPhone;
                const chatId = `${formattedNumber}@c.us`;
                logger_1.default.info({ chatId, content: input.content.substring(0, 50) }, 'Sending WhatsApp message');
                // Verify number is registered and get numberId
                const numberId = await withTimeout(activeSession.client.getNumberId(formattedNumber), 30000, 'WhatsApp getNumberId timed out');
                if (!numberId) {
                    throw new errors_1.ValidationError('Phone number is not registered on WhatsApp');
                }
                // Log NumberId structure for debugging
                logger_1.default.info({
                    numberIdString: typeof numberId === 'string' ? numberId : numberId._serialized,
                    type: typeof numberId,
                    isObject: typeof numberId === 'object'
                }, 'Number verified, sending message');
                // Normalize target chat id for sendMessage
                const targetChatId = typeof numberId === 'object' ? numberId._serialized : numberId;
                let waMessage;
                if (input.mediaUrl) {
                    const media = await withTimeout(whatsapp_web_js_1.MessageMedia.fromUrl(input.mediaUrl), 30000, 'WhatsApp media fetch timed out');
                    waMessage = await withTimeout(activeSession.client.sendMessage(targetChatId, media, { caption: input.content || '' }), 30000, 'WhatsApp send timed out');
                }
                else {
                    waMessage = await withTimeout(activeSession.client.sendMessage(targetChatId, input.content || ''), 30000, 'WhatsApp send timed out');
                }
                logger_1.default.info({ messageId: waMessage.id.id }, 'WhatsApp message sent successfully');
                // Update message with WhatsApp message ID
                return await this.messageRepository.update(message.id, {
                    waMessageId: waMessage.id.id,
                    status: client_1.MessageStatus.SENT,
                });
            }
            catch (error) {
                // Mark message as failed if WhatsApp Web fails
                await this.messageRepository.update(message.id, {
                    status: client_1.MessageStatus.FAILED,
                });
                // Better error message extraction
                let errorMessage = 'Unknown error';
                if (error instanceof Error) {
                    errorMessage = error.message || error.toString();
                }
                else if (typeof error === 'string') {
                    errorMessage = error;
                }
                else if (error && typeof error === 'object') {
                    errorMessage = JSON.stringify(error);
                }
                logger_1.default.error({
                    error,
                    errorType: typeof error,
                    errorConstructor: error?.constructor?.name,
                    phoneNumber: input.phoneNumber || 'N/A',
                    contactId: input.contactId || 'N/A',
                    errorMessage
                }, 'WhatsApp Web send failed');
                throw new errors_1.ExternalServiceError('WhatsApp Web', errorMessage);
            }
        }
        catch (error) {
            logger_1.default.error({ input, error }, 'Failed to send message');
            throw error;
        }
    }
    async receiveMessage(waMessageId, payload) {
        try {
            // Check if message already exists
            const existing = await this.messageRepository.findByWaMessageId(waMessageId);
            if (existing) {
                return existing;
            }
            // Create new message
            const message = await this.messageRepository.create({
                waMessageId,
                user: { connect: { id: payload.userId } },
                contact: { connect: { id: payload.contactId } },
                conversation: { connect: { id: payload.conversationId } },
                content: payload.content,
                messageType: client_1.MessageType.TEXT,
                direction: client_1.MessageDirection.INCOMING,
                status: client_1.MessageStatus.RECEIVED,
            });
            logger_1.default.info({ messageId: message.id }, 'Message received');
            return message;
        }
        catch (error) {
            logger_1.default.error({ waMessageId, payload, error }, 'Failed to receive message');
            throw error;
        }
    }
    async getMessagesByContact(userId, contactId, limit, offset) {
        try {
            const contact = await this.contactRepository.findById(contactId);
            if (!contact || contact.userId !== userId) {
                throw new errors_1.NotFoundError('Contact');
            }
            const conversation = await this.conversationRepository.findOrCreate(userId, contactId);
            return await this.messageRepository.findByConversation(conversation.id, {
                limit,
                offset,
            });
        }
        catch (error) {
            logger_1.default.error({ contactId, error }, 'Failed to get messages by contact');
            throw error;
        }
    }
    async getMessages(userId, conversationId, limit, offset) {
        try {
            // Verify user owns conversation (authorization)
            // ... additional check needed
            return await this.messageRepository.findByConversation(conversationId, {
                limit,
                offset,
            });
        }
        catch (error) {
            logger_1.default.error({ conversationId, error }, 'Failed to get messages');
            throw error;
        }
    }
    async markAsRead(messageId) {
        try {
            const message = await this.messageRepository.findById(messageId);
            if (!message) {
                throw new errors_1.NotFoundError('Message');
            }
            return await this.messageRepository.update(messageId, {
                status: client_1.MessageStatus.READ,
                readAt: new Date(),
            });
        }
        catch (error) {
            logger_1.default.error({ messageId, error }, 'Failed to mark message as read');
            throw error;
        }
    }
    async deleteMessage(messageId) {
        try {
            const message = await this.messageRepository.findById(messageId);
            if (!message) {
                throw new errors_1.NotFoundError('Message');
            }
            // Can only delete outgoing messages
            if (message.direction !== 'OUTGOING') {
                throw new errors_1.ValidationError('Can only delete outgoing messages');
            }
            await this.messageRepository.delete(messageId);
            logger_1.default.info({ messageId }, 'Message deleted');
        }
        catch (error) {
            logger_1.default.error({ messageId, error }, 'Failed to delete message');
            throw error;
        }
    }
    async listMessages(userId, filters) {
        try {
            return await this.messageRepository.findByUser(userId, filters);
        }
        catch (error) {
            logger_1.default.error({ userId, filters, error }, 'Failed to list messages');
            throw error;
        }
    }
}
exports.MessageService = MessageService;
//# sourceMappingURL=message.service.js.map