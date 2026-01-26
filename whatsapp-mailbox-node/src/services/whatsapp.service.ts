// src/services/whatsapp.service.ts
// WhatsApp API integration - External service adapter

import axios, { AxiosInstance } from 'axios';
import { getEnv } from '@config/env';
import logger from '@utils/logger';
import { ExternalServiceError } from '@utils/errors';

export interface IWhatsAppService {
  sendMessage(phoneNumber: string, content: string, mediaUrl?: string): Promise<{ messageId: string }>;
  getMediaUrl(mediaId: string): Promise<string>;
}

export class WhatsAppService implements IWhatsAppService {
  private client: AxiosInstance;
  private env = getEnv();

  constructor() {
    this.client = axios.create({
      baseURL: 'https://graph.instagram.com/v18.0',
      headers: {
        Authorization: `Bearer ${this.env.WHATSAPP_ACCESS_TOKEN}`,
        'Content-Type': 'application/json',
      },
    });

    // Add request/response logging
    this.client.interceptors.response.use(
      (response) => {
        logger.debug({ status: response.status }, 'WhatsApp API success');
        return response;
      },
      (error) => {
        logger.error(
          { status: error.response?.status, data: error.response?.data },
          'WhatsApp API error',
        );
        return Promise.reject(error);
      },
    );
  }

  async sendMessage(
    phoneNumber: string,
    content: string,
    mediaUrl?: string,
  ): Promise<{ messageId: string }> {
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

      const response = await this.client.post(
        `/${this.env.WHATSAPP_PHONE_NUMBER_ID}/messages`,
        payload,
      );

      return {
        messageId: response.data.messages?.[0]?.id || response.data.messageId,
      };
    } catch (error) {
      const errorMessage = axios.isAxiosError(error)
        ? error.response?.data?.error?.message || error.message
        : 'Unknown error';
      throw new ExternalServiceError('WhatsApp API', errorMessage);
    }
  }

  async getMediaUrl(mediaId: string): Promise<string> {
    try {
      const response = await this.client.get(`/${mediaId}`);
      return response.data.url;
    } catch (error) {
      const errorMessage = axios.isAxiosError(error)
        ? error.response?.data?.error?.message || error.message
        : 'Unknown error';
      throw new ExternalServiceError('WhatsApp API', errorMessage);
    }
  }
}
