// src/server.ts
// Express application setup

// Load environment variables first
import dotenv from 'dotenv';
dotenv.config();

import express, { Express } from 'express';
import cors from 'cors';
import helmet from 'helmet';
import path from 'path';
import { getEnv } from '@config/env';
import { connectDatabase, disconnectDatabase } from '@config/database';
import { setupErrorMiddleware } from '@middleware/error.middleware';
import { createMessageRoutes } from '@routes/messages';
import { createContactRoutes } from '@routes/contacts';
import authRoutes from '@routes/auth';
import quickReplyRoutes from '@routes/quick-replies';
import tagRoutes from '@routes/tags';
import segmentRoutes from '@routes/segments';
import broadcastRoutes from '@routes/broadcasts';
import automationRoutes from '@routes/automations';
import analyticsRoutes from '@routes/analytics';
import crmRoutes from '@routes/crm';
import noteRoutes from '@routes/notes';
import whatsappWebRoutes from '@routes/whatsapp-web';
import { whatsappWebService } from '@services/whatsapp-web.service';
import { MessageType } from '@prisma/client';
import { MessageRepository } from '@repositories/message.repository';
import { ContactRepository } from '@repositories/contact.repository';
import { ConversationRepository } from '@repositories/conversation.repository';
import { getPrismaClient } from '@config/database';
import logger from '@utils/logger';

export function createApp(): Express {
  const app = express();
  const env = getEnv();

  // Security middleware
  app.use(helmet({
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
  app.use(cors({
    origin: env.CORS_ORIGIN,
    credentials: true,
  }));

  // Body parsing
  app.use(express.json({ limit: '10mb' }));
  app.use(express.urlencoded({ limit: '10mb', extended: true }));

  // Serve static files (QR test page)
  app.use(express.static(path.join(__dirname, '../public')));

  // Logging middleware
  app.use((req, _res, next) => {
    logger.info({ method: req.method, path: req.path }, `${req.method} ${req.path}`);
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
  app.use('/api/v1/auth', authRoutes);
  app.use('/api/v1/messages', createMessageRoutes());
  app.use('/api/v1/contacts', createContactRoutes());
  app.use('/api/v1/quick-replies', quickReplyRoutes);
  app.use('/api/v1/tags', tagRoutes);
  app.use('/api/v1/segments', segmentRoutes);
  app.use('/api/v1/broadcasts', broadcastRoutes);
  app.use('/api/v1/automations', automationRoutes);
  app.use('/api/v1/automation', automationRoutes);
  app.use('/api/v1/analytics', analyticsRoutes);
  app.use('/api/v1/crm', crmRoutes);
  app.use('/api/v1/notes', noteRoutes);
  app.use('/api/v1/whatsapp-web', whatsappWebRoutes);

  // Serve index.html for all non-API routes (SPA fallback)
  app.get('*', (req, res) => {
    if (!req.path.startsWith('/api/')) {
      res.sendFile(path.join(__dirname, '../public/index.html'));
    } else {
      res.status(404).json({ error: { code: 'NOT_FOUND', message: 'Resource not found' } });
    }
  });

  // Error handling (must be last)
  setupErrorMiddleware(app);

  // Setup WhatsApp message listener to capture incoming messages
  setupIncomingMessageListener();

  return app;
}

/**
 * Listen for incoming WhatsApp messages and save them to database
 */
function setupIncomingMessageListener(): void {
  whatsappWebService.on('message', async (event: any) => {
    try {
      const { sessionId, from, body, hasMedia, timestamp, waMessageId, messageType } = event;
      
      logger.info({ sessionId, from, body: body?.substring(0, 50), hasMedia, timestamp, messageType }, 'RAW incoming message event');
      
      // Skip messages with no content and no media (read receipts, delivery confirmations, etc.)
      if (!body && !hasMedia) {
        logger.debug({ sessionId, from, messageType }, 'Skipping empty message with no media');
        return;
      }

      // Get the session to find the userId
      const session = whatsappWebService.getSession(sessionId);
      if (!session) {
        logger.warn({ sessionId }, 'Incoming message but no session found');
        return;
      }

      const userId = session.userId;
      const sanitizedPhone = sanitizePhone(from);
      if (!sanitizedPhone) {
        logger.warn({ sessionId, from }, 'Skipping message with no usable phone number');
        return;
      }

      logger.info({ userId, phoneNumber: sanitizedPhone, body: body?.substring(0, 50), messageType }, 'Processing incoming WhatsApp message');

      // Create repositories with prisma client
      const db = getPrismaClient();
      const contactRepo = new ContactRepository(db);
      const conversationRepo = new ConversationRepository(db);
      const messageRepo = new MessageRepository(db);

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
        messageType: normalizedType as any,
        direction: 'INCOMING',
        status: 'RECEIVED',
        waMessageId: safeWaMessageId,
      } as any);

      logger.info({ userId, phoneNumber: sanitizedPhone, contactId: contact.id }, 'Saved incoming message to database');
    } catch (error) {
      logger.error({ error, event }, 'Failed to save incoming WhatsApp message');
    }
  });

  logger.info('WhatsApp incoming message listener initialized');
}


function sanitizePhone(from: string): string {
  const base = from.split('@')[0];
  const digits = base.replace(/\D/g, '');
  return digits.slice(-20);
}

function normalizeMessageType(rawType?: string, hasMedia?: boolean): MessageType {
  switch (rawType) {
    case 'image':
      return MessageType.IMAGE;
    case 'video':
      return MessageType.VIDEO;
    case 'audio':
    case 'ptt':
      return MessageType.AUDIO;
    case 'document':
      return MessageType.DOCUMENT;
    case 'location':
      return MessageType.LOCATION;
    case 'contact_card':
      return MessageType.CONTACT;
    case 'sticker':
      return MessageType.IMAGE;
    default:
      return hasMedia ? MessageType.DOCUMENT : MessageType.TEXT;
  }
}

export async function startServer(): Promise<void> {
  try {
    const env = getEnv();
    const app = createApp();

    // Connect database
    await connectDatabase();

    // Start listening
    const server = app.listen(env.PORT, '0.0.0.0', () => {
      logger.info({ port: env.PORT, env: env.NODE_ENV }, 'Server started');
    });

    // Graceful shutdown
    const gracefulShutdown = async (signal: string) => {
      logger.info({ signal }, 'Received shutdown signal');

      server.close(async () => {
        await disconnectDatabase();
        logger.info('Server shut down gracefully');
        process.exit(0);
      });

      // Force shutdown after 30 seconds
      setTimeout(() => {
        logger.error('Forced shutdown after timeout');
        process.exit(1);
      }, 30000);
    };

    process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
    process.on('SIGINT', () => gracefulShutdown('SIGINT'));
  } catch (error) {
    logger.error(error, 'Failed to start server');
    process.exit(1);
  }
}

// Start server if run directly
if (require.main === module) {
  startServer();
}
