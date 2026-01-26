// src/routes/broadcasts.ts
// Broadcasts API routes

import { Router } from 'express';
import { BroadcastController } from '@controllers/broadcast.controller';
import { BroadcastService } from '@services/broadcast.service';
import { CampaignRepository } from '@repositories/campaign.repository';
import { SegmentRepository } from '@repositories/segment.repository';
import { MessageRepository } from '@repositories/message.repository';
import { WhatsAppService } from '@services/whatsapp.service';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();


// Initialize dependencies
  const prisma = getPrismaClient();
const campaignRepo = new CampaignRepository(prisma);
const segmentRepo = new SegmentRepository(prisma);
const messageRepo = new MessageRepository(prisma);
const whatsappService = new WhatsAppService();
const service = new BroadcastService(campaignRepo, segmentRepo, messageRepo as any);
const controller = new BroadcastController(service);

// Validation schemas
const createBroadcastSchema = z.object({
  body: z.object({
    name: z.string().min(1).max(100),
    message: z.string().min(1),
    segmentId: z.string().cuid().optional(),
    mediaUrl: z.string().url().optional(),
    mediaType: z.enum(['IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT']).optional(),
  }),
});

const scheduleSchema = z.object({
  body: z.object({
    scheduleTime: z.string().datetime(),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.post('/', validateRequest(createBroadcastSchema), controller.create);
router.get('/', controller.list);
router.post('/:id/send', controller.send);
router.post('/:id/schedule', validateRequest(scheduleSchema), controller.schedule);
router.post('/:id/cancel', controller.cancel);

export default router;
