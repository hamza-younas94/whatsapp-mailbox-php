// src/routes/messages.ts
// Message API routes

import { Router, Request, Response } from 'express';
import { z } from 'zod';
import { MessageController } from '@controllers/message.controller';
import { MessageService } from '@services/message.service';
import { MessageRepository } from '@repositories/message.repository';
import { WhatsAppService } from '@services/whatsapp.service';
import { authMiddleware } from '@middleware/auth.middleware';
import { validate, validateQuery } from '@middleware/validation.middleware';
import getPrismaClient from '@config/database';

export function createMessageRoutes(): Router {
  const router = Router();

  const messageRepository = new MessageRepository(prisma);
  const whatsAppService = new WhatsAppService();
  const messageService = new MessageService(messageRepository, whatsAppService);
  const controller = new MessageController(messageService);

  // Validation schemas
  const sendMessageSchema = z.object({
    contactId: z.string().uuid(),
    content: z.string().max(4096).optional(),
    mediaUrl: z.string().url().optional(),
  }).refine((data) => data.content || data.mediaUrl, {
    message: 'Either content or mediaUrl is required',
  });

  const getMessagesSchema = z.object({
    limit: z.coerce.number().min(1).max(100).optional(),
    offset: z.coerce.number().min(0).optional(),
  });

  // Routes
  router.post(
    '/',
    authMiddleware,
    validate(sendMessageSchema),
    controller.sendMessage,
  );

  router.get(
    '/conversation/:conversationId',
    authMiddleware,
    validateQuery(getMessagesSchema),
    controller.getMessages,
  );

  router.put(
    '/:messageId/read',
    authMiddleware,
    controller.markAsRead,
  );

  router.delete(
    '/:messageId',
    authMiddleware,
    controller.deleteMessage,
  );

  // Webhook endpoint (no auth required)
  router.post('/webhook', controller.webhookReceive);

  return router;
}
