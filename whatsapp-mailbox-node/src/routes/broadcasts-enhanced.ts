// src/routes/broadcasts-enhanced.ts
// Enhanced Broadcast API routes

import { Router } from 'express';
import { BroadcastEnhancedController } from '@controllers/broadcast-enhanced.controller';
import { BroadcastEnhancedService } from '@services/broadcast-enhanced.service';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const prisma = getPrismaClient();
const service = new BroadcastEnhancedService(prisma);
const controller = new BroadcastEnhancedController(service);

// Validation schemas
const createBroadcastSchema = z.object({
  name: z.string().min(1).max(255),
  messageContent: z.string().min(1),
  messageType: z.enum(['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO', 'LOCATION', 'CONTACT', 'STICKER']),
  mediaUrl: z.string().url().optional(),
  scheduledFor: z.string().datetime().optional(),
  priority: z.enum(['LOW', 'MEDIUM', 'HIGH', 'URGENT']).optional(),
  recipientType: z.enum(['ALL', 'SEGMENT', 'TAG', 'MANUAL']),
  segmentIds: z.array(z.string()).optional(),
  tagIds: z.array(z.string()).optional(),
  contactIds: z.array(z.string()).optional(),
});

const updateBroadcastSchema = z.object({
  name: z.string().min(1).max(255).optional(),
  messageContent: z.string().min(1).optional(),
  messageType: z.enum(['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO', 'LOCATION', 'CONTACT', 'STICKER']).optional(),
  mediaUrl: z.string().url().optional(),
  scheduledFor: z.string().datetime().optional(),
  priority: z.enum(['LOW', 'MEDIUM', 'HIGH', 'URGENT']).optional(),
  status: z.enum(['DRAFT', 'SCHEDULED', 'SENDING', 'SENT', 'CANCELLED', 'FAILED']).optional(),
});

// Apply authentication to all routes
router.use(authenticate);

// Broadcast routes
router.post('/', validateRequest(createBroadcastSchema), controller.create);
router.get('/', controller.list);
router.get('/:id', controller.getById);
router.put('/:id', validateRequest(updateBroadcastSchema), controller.update);
router.delete('/:id', controller.delete);
router.post('/:id/send', controller.send);
router.post('/:id/cancel', controller.cancel);
router.get('/:id/analytics', controller.getAnalytics);

export default router;
