// src/routes/automations.ts
// Automations API routes

import { Router } from 'express';
import { AutomationController } from '@controllers/automation.controller';
import { AutomationService } from '@services/automation.service';
import { AutomationRepository } from '@repositories/automation.repository';
import { WhatsAppService } from '@services/whatsapp.service';
import { TagService } from '@services/tag.service';
import { TagRepository } from '@repositories/tag.repository';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();


// Initialize dependencies
  const prisma = getPrismaClient();
const automationRepo = new AutomationRepository(prisma);
const tagRepo = new TagRepository(prisma);
const whatsappService = new WhatsAppService();
const tagService = new TagService(tagRepo);
const service = new AutomationService(automationRepo, {} as any, tagService);
const controller = new AutomationController(service);

// Validation schemas
const createAutomationSchema = z.object({
  body: z.object({
    name: z.string().min(1).max(100),
    triggerType: z.enum(['MESSAGE_RECEIVED', 'KEYWORD', 'TAG_ADDED', 'SCHEDULE']),
    triggerValue: z.string().optional(),
    conditions: z.record(z.any()).optional(),
    actions: z.array(
      z.object({
        type: z.enum(['SEND_MESSAGE', 'ADD_TAG', 'REMOVE_TAG', 'WAIT', 'WEBHOOK']),
        value: z.string().optional(),
        delay: z.number().optional(),
      }),
    ),
    isActive: z.boolean().default(true),
  }),
});

const toggleSchema = z.object({
  body: z.object({
    isActive: z.boolean(),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.post('/', validateRequest(createAutomationSchema), controller.create);
router.get('/', controller.list);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
router.patch('/:id/toggle', validateRequest(toggleSchema), controller.toggle);

export default router;
