// src/services/whatsapp-web.service.ts
// WhatsApp Web integration with QR code support

import { Client, LocalAuth, Message as WAMessage } from 'whatsapp-web.js';
import qrcode from 'qrcode';
import { EventEmitter } from 'events';
import logger from '@utils/logger';
import path from 'path';
import fs from 'fs';

export interface WhatsAppWebSession {
  id: string;
  userId: string;
  client: Client;
  status: 'INITIALIZING' | 'QR_READY' | 'AUTHENTICATED' | 'READY' | 'DISCONNECTED';
  qrCode?: string;
  phoneNumber?: string;
  createdAt: Date;
}

export class WhatsAppWebService extends EventEmitter {
  private sessions: Map<string, WhatsAppWebSession> = new Map();
  private sessionDir: string;

  constructor() {
    super();
    // Allow persistent session directory override so deployments keep sessions
    this.sessionDir = process.env.WWEBJS_AUTH_DIR
      ? path.resolve(process.env.WWEBJS_AUTH_DIR)
      : path.join(process.cwd(), '.wwebjs_auth');
    
    // Ensure session directory exists
    if (!fs.existsSync(this.sessionDir)) {
      fs.mkdirSync(this.sessionDir, { recursive: true });
    }
  }

  /**
   * Initialize a new WhatsApp Web session for a user
   */
  async initializeSession(userId: string, sessionId: string): Promise<WhatsAppWebSession> {
    // Check if session already exists
    if (this.sessions.has(sessionId)) {
      const existing = this.sessions.get(sessionId)!;
      if (existing.status !== 'DISCONNECTED') {
        return existing;
      }
      // Clean up old session
      await this.destroySession(sessionId);
    }

    // Create new client with persistent session
    const client = new Client({
      authStrategy: new LocalAuth({
        clientId: sessionId,
        dataPath: this.sessionDir,
      }),
      puppeteer: {
        headless: true,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-accelerated-2d-canvas',
          '--no-first-run',
          '--no-zygote',
          '--disable-gpu',
        ],
      },
    });

    const session: WhatsAppWebSession = {
      id: sessionId,
      userId,
      client,
      status: 'INITIALIZING',
      createdAt: new Date(),
    };

    this.sessions.set(sessionId, session);

    // Setup event handlers
    this.setupClientEvents(session);

    // Initialize the client
    try {
      logger.info({ userId, sessionId }, 'Starting WhatsApp Web client initialization...');
      await client.initialize();
      logger.info({ userId, sessionId }, 'WhatsApp Web client.initialize() completed');
    } catch (error) {
      logger.error({ userId, sessionId, error }, 'Failed to initialize WhatsApp Web client');
      session.status = 'DISCONNECTED';
      throw error;
    }

    return session;
  }

  /**
   * Setup event handlers for WhatsApp Web client
   */
  private setupClientEvents(session: WhatsAppWebSession): void {
    const { client, id } = session;

    // Loading screen event (for debugging)
    client.on('loading_screen', (percent, message) => {
      logger.info({ sessionId: id, percent, message }, 'WhatsApp Web loading');
    });

    // QR Code event
    client.on('qr', async (qr) => {
      try {
        // Generate QR code as data URL
        const qrDataUrl = await qrcode.toDataURL(qr);
        session.qrCode = qrDataUrl;
        session.status = 'QR_READY';

        this.emit('qr', { sessionId: id, qrCode: qrDataUrl });
        logger.info({ sessionId: id }, 'QR code generated');
      } catch (error) {
        logger.error({ error, sessionId: id }, 'Failed to generate QR code');
      }
    });

    // Authenticated event
    client.on('authenticated', () => {
      session.status = 'AUTHENTICATED';
      session.qrCode = undefined; // Clear QR code after authentication
      this.emit('authenticated', { sessionId: id });
      logger.info({ sessionId: id }, 'WhatsApp Web authenticated');
    });

    // Ready event
    client.on('ready', async () => {
      session.status = 'READY';
      
      // Get phone number
      const info = client.info;
      if (info) {
        session.phoneNumber = info.wid.user;
      }

      this.emit('ready', { sessionId: id, phoneNumber: session.phoneNumber });
      logger.info({ sessionId: id, phoneNumber: session.phoneNumber }, 'WhatsApp Web ready');
    });

    // Message event
    client.on('message', async (message: WAMessage) => {
      // Ignore WhatsApp status broadcast system messages to avoid duplicate IDs
      if (message.from === 'status@broadcast') {
        logger.debug({ sessionId: id }, 'Ignoring status broadcast message');
        return;
      }

      this.emit('message', {
        sessionId: id,
        from: message.from,
        body: message.body,
        hasMedia: message.hasMedia,
        timestamp: message.timestamp,
        waMessageId: message.id?._serialized,
        messageType: message.type,
      });

      logger.debug({ sessionId: id, from: message.from }, 'Message received');
    });

    // Disconnected event
    client.on('disconnected', (reason) => {
      session.status = 'DISCONNECTED';
      this.emit('disconnected', { sessionId: id, reason });
      logger.warn({ sessionId: id, reason }, 'WhatsApp Web disconnected');
    });

    // Auth failure event
    client.on('auth_failure', (error) => {
      session.status = 'DISCONNECTED';
      this.emit('auth_failure', { sessionId: id, error });
      logger.error({ sessionId: id, error }, 'WhatsApp Web authentication failed');
    });
  }

  /**
   * Send a message via WhatsApp Web
   */
  async sendMessage(
    sessionId: string,
    to: string,
    message: string,
  ): Promise<{ success: boolean; messageId?: string }> {
    const session = this.sessions.get(sessionId);

    if (!session) {
      throw new Error('Session not found');
    }

    if (session.status !== 'READY') {
      throw new Error('Session not ready');
    }

    try {
      // Format phone number (add @c.us if not present)
      const chatId = to.includes('@') ? to : `${to}@c.us`;
      
      const sentMessage = await session.client.sendMessage(chatId, message);

      logger.info({ sessionId, to, messageId: sentMessage.id._serialized }, 'Message sent via WhatsApp Web');

      return {
        success: true,
        messageId: sentMessage.id._serialized,
      };
    } catch (error) {
      logger.error({ error, sessionId, to }, 'Failed to send message via WhatsApp Web');
      return { success: false };
    }
  }

  /**
   * Send media message
   */
  async sendMediaMessage(
    sessionId: string,
    to: string,
    mediaUrl: string,
    caption?: string,
  ): Promise<{ success: boolean; messageId?: string }> {
    const session = this.sessions.get(sessionId);

    if (!session) {
      throw new Error('Session not found');
    }

    if (session.status !== 'READY') {
      throw new Error('Session not ready');
    }

    try {
      const chatId = to.includes('@') ? to : `${to}@c.us`;
      
      const { MessageMedia } = await import('whatsapp-web.js');
      const media = await MessageMedia.fromUrl(mediaUrl);
      
      const sentMessage = await session.client.sendMessage(chatId, media, { caption });

      logger.info({ sessionId, to, messageId: sentMessage.id._serialized }, 'Media sent via WhatsApp Web');

      return {
        success: true,
        messageId: sentMessage.id._serialized,
      };
    } catch (error) {
      logger.error({ error, sessionId, to }, 'Failed to send media via WhatsApp Web');
      return { success: false };
    }
  }

  /**
   * Get session status
   */
  getSession(sessionId: string): WhatsAppWebSession | undefined {
    return this.sessions.get(sessionId);
  }

  /**
   * Get all sessions for a user
   */
  getUserSessions(userId: string): WhatsAppWebSession[] {
    return Array.from(this.sessions.values()).filter((s) => s.userId === userId);
  }

  /**
   * Check if session is ready
   */
  isSessionReady(sessionId: string): boolean {
    const session = this.sessions.get(sessionId);
    return session?.status === 'READY';
  }

  /**
   * Logout and destroy session
   */
  async destroySession(sessionId: string): Promise<void> {
    const session = this.sessions.get(sessionId);

    if (!session) {
      return;
    }

    try {
      await session.client.logout();
      await session.client.destroy();
    } catch (error) {
      logger.error({ error, sessionId }, 'Error destroying session');
    }

    this.sessions.delete(sessionId);
    logger.info({ sessionId }, 'Session destroyed');
  }

  /**
   * Get QR code for session (if available)
   */
  getQRCode(sessionId: string): string | undefined {
    const session = this.sessions.get(sessionId);
    return session?.qrCode;
  }

  /**
   * Restart a session
   */
  async restartSession(sessionId: string): Promise<WhatsAppWebSession> {
    const session = this.sessions.get(sessionId);
    
    if (!session) {
      throw new Error('Session not found');
    }

    const userId = session.userId;
    await this.destroySession(sessionId);
    
    return this.initializeSession(userId, sessionId);
  }
}

// Singleton instance
export const whatsappWebService = new WhatsAppWebService();
