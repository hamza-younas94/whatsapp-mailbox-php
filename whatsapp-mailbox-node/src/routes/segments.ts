// src/routes/segments.ts
// Segments API routes

import { Router } from 'express';
import { SegmentController } from '@controllers/segment.controller';
import { SegmentService } from '@services/segment.service';
import { SegmentRepository } from '@repositories/segment.repository';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const repository = new SegmentRepository(prisma);
const service = new SegmentService(repository);
const controller = new SegmentController(service);

// Validation schemas
const createSegmentSchema = z.object({
  body: z.object({
    name: z.string().min(1),
    conditions: z.record(z.any()),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.post('/', validateRequest(createSegmentSchema), controller.create);
router.get('/', controller.list);
router.get('/:id/contacts', controller.getContacts);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);

export default router;
