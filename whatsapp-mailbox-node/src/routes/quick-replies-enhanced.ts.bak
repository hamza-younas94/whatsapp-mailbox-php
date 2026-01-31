// src/routes/quick-replies-enhanced.ts
// Enhanced Quick Replies API routes with categories and analytics

import { Router } from 'express';
import { QuickReplyEnhancedController } from '@controllers/quick-reply-enhanced.controller';
import { QuickReplyEnhancedService } from '@services/quick-reply-enhanced.service';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const prisma = getPrismaClient();
const service = new QuickReplyEnhancedService(prisma);
const controller = new QuickReplyEnhancedController(service);

// Validation schemas
const createQuickReplySchema = z.object({
  title: z.string().min(1).max(255),
  content: z.string().min(1),
  shortcut: z.string().max(50).optional(),
  categoryId: z.string().optional(),
  variables: z.array(z.string()).optional(),
  mediaUrl: z.string().url().optional(),
  mediaType: z.enum(['IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO']).optional(),
  tags: z.array(z.string()).optional(),
});

const updateQuickReplySchema = z.object({
  title: z.string().min(1).max(255).optional(),
  content: z.string().min(1).optional(),
  shortcut: z.string().max(50).optional(),
  categoryId: z.string().optional(),
  variables: z.array(z.string()).optional(),
  mediaUrl: z.string().url().optional(),
  mediaType: z.enum(['IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO']).optional(),
  tags: z.array(z.string()).optional(),
  isActive: z.boolean().optional(),
});

const createCategorySchema = z.object({
  name: z.string().min(1).max(100),
  description: z.string().optional(),
  color: z.string().max(20).optional(),
});

const updateCategorySchema = z.object({
  name: z.string().min(1).max(100).optional(),
  description: z.string().optional(),
  color: z.string().max(20).optional(),
});

// Apply authentication to all routes
router.use(authenticate);

// Quick Reply routes
router.get('/enhanced', controller.getAllWithCategories);
router.post('/', validateRequest(createQuickReplySchema), controller.create);
router.put('/:id', validateRequest(updateQuickReplySchema), controller.update);
router.delete('/:id', controller.delete);
router.post('/:id/use', controller.trackUsage);
router.get('/analytics', controller.getAnalytics);

// Category routes
router.post('/categories', validateRequest(createCategorySchema), controller.createCategory);
router.put('/categories/:id', validateRequest(updateCategorySchema), controller.updateCategory);
router.delete('/categories/:id', controller.deleteCategory);

export default router;
