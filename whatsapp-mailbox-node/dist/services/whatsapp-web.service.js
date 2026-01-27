"use strict";
// src/services/whatsapp-web.service.ts
// WhatsApp Web integration with QR code support
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.whatsappWebService = exports.WhatsAppWebService = void 0;
const whatsapp_web_js_1 = require("whatsapp-web.js");
const qrcode_1 = __importDefault(require("qrcode"));
const events_1 = require("events");
const logger_1 = __importDefault(require("../utils/logger"));
const path_1 = __importDefault(require("path"));
const fs_1 = __importDefault(require("fs"));
class WhatsAppWebService extends events_1.EventEmitter {
    constructor() {
        super();
        this.sessions = new Map();
        this.initializingSessions = new Set(); // Track sessions being initialized
        // Allow persistent session directory override so deployments keep sessions
        this.sessionDir = process.env.WWEBJS_AUTH_DIR
            ? path_1.default.resolve(process.env.WWEBJS_AUTH_DIR)
            : path_1.default.join(process.cwd(), '.wwebjs_auth');
        // Ensure session directory exists
        if (!fs_1.default.existsSync(this.sessionDir)) {
            fs_1.default.mkdirSync(this.sessionDir, { recursive: true });
        }
    }
    /**
     * Initialize a new WhatsApp Web session for a user
     */
    async initializeSession(userId, sessionId) {
        // Check if session is already being initialized (prevent concurrent calls)
        if (this.initializingSessions.has(sessionId)) {
            logger_1.default.warn({ userId, sessionId }, 'Session initialization already in progress, waiting for existing initialization');
            // Wait a bit and check if session was created
            await new Promise(resolve => setTimeout(resolve, 1000));
            const existing = this.sessions.get(sessionId);
            if (existing && existing.status !== 'DISCONNECTED') {
                return existing;
            }
            // If still not ready, throw error to prevent infinite loops
            throw new Error('Session initialization already in progress');
        }
        // Check if session already exists
        if (this.sessions.has(sessionId)) {
            const existing = this.sessions.get(sessionId);
            // Return existing session if it's not disconnected
            // This prevents multiple simultaneous initializations
            if (existing.status !== 'DISCONNECTED') {
                logger_1.default.info({ userId, sessionId, status: existing.status }, 'Session already exists, returning existing session');
                return existing;
            }
            // Clean up old disconnected session
            await this.destroySession(sessionId);
        }
        // Mark session as being initialized
        this.initializingSessions.add(sessionId);
        // Create new client with persistent session
        const client = new whatsapp_web_js_1.Client({
            authStrategy: new whatsapp_web_js_1.LocalAuth({
                clientId: sessionId,
                dataPath: this.sessionDir,
            }),
            // Pin a known-good WhatsApp Web version to avoid markedUnread/sendSeen breakage (issue #5718)
            webVersion: '2.3000.1031490220-alpha',
            webVersionCache: {
                type: 'remote',
                remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/refs/heads/main/html/2.3000.1031490220-alpha.html',
            },
            authTimeoutMs: 300000,
            qrMaxRetries: 6,
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
                    '--single-process',
                ],
            },
        });
        const session = {
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
            logger_1.default.info({ userId, sessionId }, 'Starting WhatsApp Web client initialization...');
            await client.initialize();
            logger_1.default.info({ userId, sessionId }, 'WhatsApp Web client.initialize() completed');
        }
        catch (error) {
            logger_1.default.error({ userId, sessionId, error }, 'Failed to initialize WhatsApp Web client');
            session.status = 'DISCONNECTED';
            // Remove from initializing set on error
            this.initializingSessions.delete(sessionId);
            throw error;
        }
        return session;
    }
    /**
     * Setup event handlers for WhatsApp Web client
     */
    setupClientEvents(session) {
        const { client, id } = session;
        // Loading screen event (for debugging)
        client.on('loading_screen', (percent, message) => {
            logger_1.default.info({ sessionId: id, percent, message }, 'WhatsApp Web loading');
        });
        // QR Code event
        client.on('qr', async (qr) => {
            try {
                // Generate QR code as data URL
                const qrDataUrl = await qrcode_1.default.toDataURL(qr);
                session.qrCode = qrDataUrl;
                session.status = 'QR_READY';
                // Remove from initializing set - initialization is complete (waiting for QR scan)
                this.initializingSessions.delete(id);
                this.emit('qr', { sessionId: id, qrCode: qrDataUrl });
                logger_1.default.info({ sessionId: id }, 'QR code generated');
            }
            catch (error) {
                logger_1.default.error({ error, sessionId: id }, 'Failed to generate QR code');
            }
        });
        // Authenticated event
        client.on('authenticated', () => {
            session.status = 'AUTHENTICATED';
            session.qrCode = undefined; // Clear QR code after authentication
            this.emit('authenticated', { sessionId: id });
            logger_1.default.info({ sessionId: id }, 'WhatsApp Web authenticated');
        });
        // Ready event
        client.on('ready', async () => {
            session.status = 'READY';
            // Remove from initializing set - initialization is complete
            this.initializingSessions.delete(id);
            // Get phone number
            const info = client.info;
            if (info) {
                session.phoneNumber = info.wid.user;
            }
            this.emit('ready', { sessionId: id, phoneNumber: session.phoneNumber });
            logger_1.default.info({ sessionId: id, phoneNumber: session.phoneNumber }, 'WhatsApp Web ready');
        });
        // Message event
        client.on('message', async (message) => {
            // Ignore WhatsApp status broadcast system messages to avoid duplicate IDs
            if (message.from === 'status@broadcast') {
                logger_1.default.debug({ sessionId: id }, 'Ignoring status broadcast message');
                return;
            }
            // Ping-pong test command (alternative to message.reply which may not work in all versions)
            if (message.body.toLowerCase() === 'ping') {
                try {
                    logger_1.default.info({ sessionId: id, from: message.from }, 'Ping received, replying with pong');
                    // Send pong without sendSeen option
                    await client.sendMessage(message.from, 'pong');
                    logger_1.default.info({ sessionId: id, from: message.from }, 'Pong sent successfully');
                }
                catch (error) {
                    const errorMsg = error instanceof Error ? error.message : String(error);
                    logger_1.default.error({ errorMsg, sessionId: id, from: message.from }, 'Failed to send pong reply');
                }
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
            logger_1.default.debug({ sessionId: id, from: message.from }, 'Message received');
        });
        // Disconnected event
        client.on('disconnected', (reason) => {
            session.status = 'DISCONNECTED';
            // Remove from initializing set
            this.initializingSessions.delete(id);
            this.emit('disconnected', { sessionId: id, reason });
            logger_1.default.warn({ sessionId: id, reason }, 'WhatsApp Web disconnected');
        });
        // Auth failure event
        client.on('auth_failure', (error) => {
            session.status = 'DISCONNECTED';
            // Remove from initializing set
            this.initializingSessions.delete(id);
            this.emit('auth_failure', { sessionId: id, error });
            logger_1.default.error({ sessionId: id, error }, 'WhatsApp Web authentication failed');
        });
    }
    /**
     * Send a message via WhatsApp Web
     */
    async sendMessage(sessionId, to, message) {
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
            logger_1.default.info({ sessionId, to, messageId: sentMessage.id._serialized }, 'Message sent via WhatsApp Web');
            return {
                success: true,
                messageId: sentMessage.id._serialized,
            };
        }
        catch (error) {
            logger_1.default.error({ error, sessionId, to }, 'Failed to send message via WhatsApp Web');
            return { success: false };
        }
    }
    /**
     * Send media message
     */
    async sendMediaMessage(sessionId, to, mediaUrl, caption) {
        const session = this.sessions.get(sessionId);
        if (!session) {
            throw new Error('Session not found');
        }
        if (session.status !== 'READY') {
            throw new Error('Session not ready');
        }
        try {
            const chatId = to.includes('@') ? to : `${to}@c.us`;
            const { MessageMedia } = await Promise.resolve().then(() => __importStar(require('whatsapp-web.js')));
            const media = await MessageMedia.fromUrl(mediaUrl);
            const sentMessage = await session.client.sendMessage(chatId, media, { caption });
            logger_1.default.info({ sessionId, to, messageId: sentMessage.id._serialized }, 'Media sent via WhatsApp Web');
            return {
                success: true,
                messageId: sentMessage.id._serialized,
            };
        }
        catch (error) {
            logger_1.default.error({ error, sessionId, to }, 'Failed to send media via WhatsApp Web');
            return { success: false };
        }
    }
    /**
     * Get session status
     */
    getSession(sessionId) {
        return this.sessions.get(sessionId);
    }
    /**
     * Check if a session is currently being initialized
     */
    isInitializing(sessionId) {
        return this.initializingSessions.has(sessionId);
    }
    /**
     * Get all sessions for a user
     */
    getUserSessions(userId) {
        return Array.from(this.sessions.values()).filter((s) => s.userId === userId);
    }
    /**
     * Check if session is ready
     */
    isSessionReady(sessionId) {
        const session = this.sessions.get(sessionId);
        return session?.status === 'READY';
    }
    /**
     * Logout and destroy session
     */
    async destroySession(sessionId) {
        const session = this.sessions.get(sessionId);
        if (!session) {
            return;
        }
        try {
            await session.client.logout();
            await session.client.destroy();
        }
        catch (error) {
            logger_1.default.error({ error, sessionId }, 'Error destroying session');
        }
        this.sessions.delete(sessionId);
        logger_1.default.info({ sessionId }, 'Session destroyed');
    }
    /**
     * Get QR code for session (if available)
     */
    getQRCode(sessionId) {
        const session = this.sessions.get(sessionId);
        return session?.qrCode;
    }
    /**
     * Restart a session
     */
    async restartSession(sessionId) {
        const session = this.sessions.get(sessionId);
        if (!session) {
            throw new Error('Session not found');
        }
        const userId = session.userId;
        await this.destroySession(sessionId);
        return this.initializeSession(userId, sessionId);
    }
}
exports.WhatsAppWebService = WhatsAppWebService;
// Singleton instance
exports.whatsappWebService = new WhatsAppWebService();
//# sourceMappingURL=whatsapp-web.service.js.map