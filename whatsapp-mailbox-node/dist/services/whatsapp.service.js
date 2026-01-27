"use strict";
// src/services/whatsapp.service.ts
// WhatsApp API integration - External service adapter
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.WhatsAppService = void 0;
// @ts-ignore
const axios_1 = __importDefault(require("axios"));
const env_1 = require("../config/env");
const logger_1 = __importDefault(require("../utils/logger"));
const errors_1 = require("../utils/errors");
class WhatsAppService {
    constructor() {
        this.env = (0, env_1.getEnv)();
        this.client = axios_1.default.create({
            baseURL: 'https://graph.instagram.com/v18.0',
            headers: {
                Authorization: `Bearer ${this.env.WHATSAPP_ACCESS_TOKEN}`,
                'Content-Type': 'application/json',
            },
        });
        // Add request/response logging
        this.client.interceptors.response.use((response) => {
            logger_1.default.debug({ status: response.status }, 'WhatsApp API success');
            return response;
        }, (error) => {
            logger_1.default.error({ status: error.response?.status, data: error.response?.data }, 'WhatsApp API error');
            return Promise.reject(error);
        });
    }
    async sendMessage(phoneNumber, content, mediaUrl) {
        try {
            const payload = mediaUrl
                ? {
                    messaging_product: 'whatsapp',
                    recipient_type: 'individual',
                    to: phoneNumber,
                    type: 'image',
                    image: { link: mediaUrl },
                }
                : {
                    messaging_product: 'whatsapp',
                    recipient_type: 'individual',
                    to: phoneNumber,
                    type: 'text',
                    text: { preview_url: true, body: content },
                };
            const response = await this.client.post(`/${this.env.WHATSAPP_PHONE_NUMBER_ID}/messages`, payload);
            return {
                messageId: response.data.messages?.[0]?.id || response.data.messageId,
            };
        }
        catch (error) {
            const errorMessage = axios_1.default.isAxiosError(error)
                ? error.response?.data?.error?.message || error.message
                : 'Unknown error';
            throw new errors_1.ExternalServiceError('WhatsApp API', errorMessage);
        }
    }
    async getMediaUrl(mediaId) {
        try {
            const response = await this.client.get(`/${mediaId}`);
            return response.data.url;
        }
        catch (error) {
            const errorMessage = axios_1.default.isAxiosError(error)
                ? error.response?.data?.error?.message || error.message
                : 'Unknown error';
            throw new errors_1.ExternalServiceError('WhatsApp API', errorMessage);
        }
    }
}
exports.WhatsAppService = WhatsAppService;
//# sourceMappingURL=whatsapp.service.js.map