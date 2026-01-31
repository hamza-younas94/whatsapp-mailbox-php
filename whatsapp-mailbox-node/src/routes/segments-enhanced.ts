// src/routes/segments-enhanced.ts
// Enhanced Segment API routes

import { Router } from 'express';
import { SegmentEnhancedController } from '@controllers/segment-enhanced.controller';
import { SegmentEnhancedService } from '@services/segment-enhanced.service';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const prisma = getPrismaClient();
const service = new SegmentEnhancedService(prisma);
const controller = new SegmentEnhancedController(service);

// Validation schemas
const conditionSchema = z.object({
  field: z.string(),
  operator: z.enum(['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'in', 'not_in']),
  value: z.any(),
});

const criteriaSchema = z.object({
  logic: z.enum(['AND', 'OR']),
  conditions: z.array(conditionSchema),
});

const createSegmentSchema = z.object({
  name: z.string().min(1).max(255),
  description: z.string().optional(),
  criteria: criteriaSchema,
});

const updateSegmentSchema = z.object({
  name: z.string().min(1).max(255).optional(),
  description: z.string().optional(),
  criteria: criteriaSchema.optional(),
});

const previewSchema = z.object({
  criteria: criteriaSchema,
});

// Apply authentication to all routes
router.use(authenticate);

// Segment routes
router.post('/', validateRequest(createSegmentSchema), controller.create);
router.get('/', controller.list);
router.post('/preview', validateRequest(previewSchema), controller.preview);
router.get('/:id', controller.getById);
router.put('/:id', validateRequest(updateSegmentSchema), controller.update);
router.delete('/:id', controller.delete);
router.get('/:id/contacts', controller.getContacts);
router.post('/:id/refresh', controller.refresh);

export default router;
