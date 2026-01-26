// src/routes/tags.ts
// Tags API routes

import { Router } from 'express';
import { TagController } from '@controllers/tag.controller';
import { TagService } from '@services/tag.service';
import { TagRepository } from '@repositories/tag.repository';
import { prisma } from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const repository = new TagRepository(prisma);
const service = new TagService(repository);
const controller = new TagController(service);

// Validation schemas
const createTagSchema = z.object({
  body: z.object({
    name: z.string().min(1).max(50),
    color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).optional(),
  }),
});

const addTagToContactSchema = z.object({
  body: z.object({
    contactId: z.string().cuid(),
    tagId: z.string().cuid(),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.post('/', validateRequest(createTagSchema), controller.create);
router.get('/', controller.list);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);
router.post('/contacts', validateRequest(addTagToContactSchema), controller.addToContact);
router.delete('/contacts/:contactId/:tagId', controller.removeFromContact);

export default router;
