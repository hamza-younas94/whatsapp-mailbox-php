// src/routes/crm.ts
// CRM API routes

import { Router } from 'express';
import { CRMController } from '@controllers/crm.controller';
import { CRMService } from '@services/crm.service';
import { prisma } from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const service = new CRMService(prisma);
const controller = new CRMController(service);

// Validation schemas
const createDealSchema = z.object({
  body: z.object({
    title: z.string().min(1),
    contactId: z.string().cuid(),
    value: z.number().optional(),
    stage: z.string(),
    expectedCloseDate: z.string().datetime().optional(),
    description: z.string().optional(),
  }),
});

const moveDealSchema = z.object({
  body: z.object({
    stage: z.string(),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.post('/', validateRequest(createDealSchema), controller.createDeal);
router.get('/', controller.listDeals);
router.put('/:id', controller.updateDeal);
router.patch('/:id/stage', validateRequest(moveDealSchema), controller.moveStage);
router.get('/stats', controller.getStats);

export default router;
