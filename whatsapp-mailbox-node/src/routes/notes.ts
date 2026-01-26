// src/routes/notes.ts
// Notes API routes

import { Router } from 'express';
import { NoteController } from '@controllers/note.controller';
import { NoteService } from '@services/note.service';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const service = new NoteService(prisma);
const controller = new NoteController(service);

// Validation schemas
const createNoteSchema = z.object({
  body: z.object({
    contactId: z.string().cuid(),
    content: z.string().min(1),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.post('/', validateRequest(createNoteSchema), controller.create);
router.get('/contact/:contactId', controller.list);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);

export default router;
