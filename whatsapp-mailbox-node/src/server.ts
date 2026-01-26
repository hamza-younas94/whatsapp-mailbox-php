// src/server.ts
// Express application setup

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
import logger from '@utils/logger';

export function createApp(): Express {
  const app = express();
  const env = getEnv();

  // Security middleware
  app.use(helmet());
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

  return app;
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
