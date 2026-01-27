"use strict";
// src/server.ts
// Express application setup
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.createApp = createApp;
exports.startServer = startServer;
const express_1 = __importDefault(require("express"));
const cors_1 = __importDefault(require("cors"));
const helmet_1 = __importDefault(require("helmet"));
const path_1 = __importDefault(require("path"));
const env_1 = require("./config/env");
const database_1 = require("./config/database");
const error_middleware_1 = require("./middleware/error.middleware");
const messages_1 = require("./routes/messages");
const contacts_1 = require("./routes/contacts");
const auth_1 = __importDefault(require("./routes/auth"));
const quick_replies_1 = __importDefault(require("./routes/quick-replies"));
const tags_1 = __importDefault(require("./routes/tags"));
const segments_1 = __importDefault(require("./routes/segments"));
const broadcasts_1 = __importDefault(require("./routes/broadcasts"));
const automations_1 = __importDefault(require("./routes/automations"));
const analytics_1 = __importDefault(require("./routes/analytics"));
const crm_1 = __importDefault(require("./routes/crm"));
const notes_1 = __importDefault(require("./routes/notes"));
const whatsapp_web_1 = __importDefault(require("./routes/whatsapp-web"));
const whatsapp_web_service_1 = require("./services/whatsapp-web.service");
const client_1 = require("@prisma/client");
const message_repository_1 = require("./repositories/message.repository");
const contact_repository_1 = require("./repositories/contact.repository");
const conversation_repository_1 = require("./repositories/conversation.repository");
const database_2 = require("./config/database");
const logger_1 = __importDefault(require("./utils/logger"));
function createApp() {
    const app = (0, express_1.default)();
    const env = (0, env_1.getEnv)();
    // Security middleware
    app.use((0, helmet_1.default)({
        contentSecurityPolicy: {
            useDefaults: false,
            directives: {
                defaultSrc: ["'self'", 'https:', 'data:'],
                scriptSrc: [
                    "'self'",
                    "'unsafe-inline'",
                    "'unsafe-eval'",
                    'https://cdn.tailwindcss.com',
                    'https://cdnjs.cloudflare.com',
                    'https://static.cloudflareinsights.com',
                ],
                styleSrc: ["'self'", "'unsafe-inline'", 'https://cdnjs.cloudflare.com', 'https://fonts.googleapis.com'],
                fontSrc: ["'self'", 'data:', 'https://cdnjs.cloudflare.com', 'https://fonts.gstatic.com'],
                imgSrc: ["'self'", 'data:', 'https:'],
                connectSrc: ["'self'", 'http://whatshub.nexofydigital.com:3000', 'https://whatshub.nexofydigital.com:3000'],
                objectSrc: ["'none'"],
                frameSrc: ["'none'"],
                upgradeInsecureRequests: [],
            },
        },
        crossOriginEmbedderPolicy: false,
    }));
    app.use((0, cors_1.default)({
        origin: env.CORS_ORIGIN,
        credentials: true,
    }));
    // Body parsing
    app.use(express_1.default.json({ limit: '10mb' }));
    app.use(express_1.default.urlencoded({ limit: '10mb', extended: true }));
    // Serve static files (QR test page)
    app.use(express_1.default.static(path_1.default.join(__dirname, '../public')));
    // Logging middleware
    app.use((req, _res, next) => {
        logger_1.default.info({ method: req.method, path: req.path }, `${req.method} ${req.path}`);
        next();
    });
    // Health check
    app.get('/health', (req, res) => {
        res.status(200).json({
            status: 'ok',
            timestamp: new Date().toISOString(),
            environment: env.NODE_ENV,
        });
    });
    // API routes
    app.use('/api/v1/auth', auth_1.default);
    app.use('/api/v1/messages', (0, messages_1.createMessageRoutes)());
    app.use('/api/v1/contacts', (0, contacts_1.createContactRoutes)());
    app.use('/api/v1/quick-replies', quick_replies_1.default);
    app.use('/api/v1/tags', tags_1.default);
    app.use('/api/v1/segments', segments_1.default);
    app.use('/api/v1/broadcasts', broadcasts_1.default);
    app.use('/api/v1/automations', automations_1.default);
    app.use('/api/v1/automation', automations_1.default);
    app.use('/api/v1/analytics', analytics_1.default);
    app.use('/api/v1/crm', crm_1.default);
    app.use('/api/v1/notes', notes_1.default);
    app.use('/api/v1/whatsapp-web', whatsapp_web_1.default);
    // Serve index.html for all non-API routes (SPA fallback)
    app.get('*', (req, res) => {
        if (!req.path.startsWith('/api/')) {
            res.sendFile(path_1.default.join(__dirname, '../public/index.html'));
        }
        else {
            res.status(404).json({ error: { code: 'NOT_FOUND', message: 'Resource not found' } });
        }
    });
    // Error handling (must be last)
    (0, error_middleware_1.setupErrorMiddleware)(app);
    // Setup WhatsApp message listener to capture incoming messages
    setupIncomingMessageListener();
    return app;
}
/**
 * Listen for incoming WhatsApp messages and save them to database
 */
function setupIncomingMessageListener() {
    whatsapp_web_service_1.whatsappWebService.on('message', async (event) => {
        try {
            const { sessionId, from, body, hasMedia, timestamp, waMessageId, messageType } = event;
            logger_1.default.info({ sessionId, from, body: body?.substring(0, 50), hasMedia, timestamp, messageType }, 'RAW incoming message event');
            // Skip messages with no content and no media (read receipts, delivery confirmations, etc.)
            if (!body && !hasMedia) {
                logger_1.default.debug({ sessionId, from, messageType }, 'Skipping empty message with no media');
                return;
            }
            // Get the session to find the userId
            const session = whatsapp_web_service_1.whatsappWebService.getSession(sessionId);
            if (!session) {
                logger_1.default.warn({ sessionId }, 'Incoming message but no session found');
                return;
            }
            const userId = session.userId;
            const sanitizedPhone = sanitizePhone(from);
            if (!sanitizedPhone) {
                logger_1.default.warn({ sessionId, from }, 'Skipping message with no usable phone number');
                return;
            }
            logger_1.default.info({ userId, phoneNumber: sanitizedPhone, body: body?.substring(0, 50), messageType }, 'Processing incoming WhatsApp message');
            // Create repositories with prisma client
            const db = (0, database_2.getPrismaClient)();
            const contactRepo = new contact_repository_1.ContactRepository(db);
            const conversationRepo = new conversation_repository_1.ConversationRepository(db);
            const messageRepo = new message_repository_1.MessageRepository(db);
            // Get or create contact
            const contact = await contactRepo.findOrCreate(userId, sanitizedPhone, { name: sanitizedPhone });
            // Get or create conversation
            const conversation = await conversationRepo.findOrCreate(userId, contact.id);
            // Derive a Prisma-safe message type from WhatsApp message metadata
            const normalizedType = normalizeMessageType(messageType, hasMedia);
            // Ensure we never violate the unique constraint on waMessageId
            const safeWaMessageId = waMessageId || `${from}-${timestamp}-${Date.now()}`;
            // Save message to database
            await messageRepo.create({
                user: { connect: { id: userId } },
                contact: { connect: { id: contact.id } },
                conversation: { connect: { id: conversation.id } },
                content: body,
                messageType: normalizedType,
                direction: 'INCOMING',
                status: 'RECEIVED',
                waMessageId: safeWaMessageId,
            });
            logger_1.default.info({ userId, phoneNumber: sanitizedPhone, contactId: contact.id }, 'Saved incoming message to database');
        }
        catch (error) {
            logger_1.default.error({ error, event }, 'Failed to save incoming WhatsApp message');
        }
    });
    logger_1.default.info('WhatsApp incoming message listener initialized');
}
function sanitizePhone(from) {
    const base = from.split('@')[0];
    const digits = base.replace(/\D/g, '');
    return digits.slice(-20);
}
function normalizeMessageType(rawType, hasMedia) {
    switch (rawType) {
        case 'image':
            return client_1.MessageType.IMAGE;
        case 'video':
            return client_1.MessageType.VIDEO;
        case 'audio':
        case 'ptt':
            return client_1.MessageType.AUDIO;
        case 'document':
            return client_1.MessageType.DOCUMENT;
        case 'location':
            return client_1.MessageType.LOCATION;
        case 'contact_card':
            return client_1.MessageType.CONTACT;
        case 'sticker':
            return client_1.MessageType.IMAGE;
        default:
            return hasMedia ? client_1.MessageType.DOCUMENT : client_1.MessageType.TEXT;
    }
}
async function startServer() {
    try {
        const env = (0, env_1.getEnv)();
        const app = createApp();
        // Connect database
        await (0, database_1.connectDatabase)();
        // Start listening
        const server = app.listen(env.PORT, '0.0.0.0', () => {
            logger_1.default.info({ port: env.PORT, env: env.NODE_ENV }, 'Server started');
        });
        // Graceful shutdown
        const gracefulShutdown = async (signal) => {
            logger_1.default.info({ signal }, 'Received shutdown signal');
            server.close(async () => {
                await (0, database_1.disconnectDatabase)();
                logger_1.default.info('Server shut down gracefully');
                process.exit(0);
            });
            // Force shutdown after 30 seconds
            setTimeout(() => {
                logger_1.default.error('Forced shutdown after timeout');
                process.exit(1);
            }, 30000);
        };
        process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
        process.on('SIGINT', () => gracefulShutdown('SIGINT'));
    }
    catch (error) {
        logger_1.default.error(error, 'Failed to start server');
        process.exit(1);
    }
}
// Start server if run directly
if (require.main === module) {
    startServer();
}
//# sourceMappingURL=server.js.map