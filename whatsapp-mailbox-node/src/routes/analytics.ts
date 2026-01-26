// src/routes/analytics.ts
// Analytics API routes

import { Router } from 'express';
import { AnalyticsController } from '@controllers/analytics.controller';
import { AnalyticsService } from '@services/analytics.service';
import getPrismaClient from '@config/database';
import { authenticate } from '@middleware/auth.middleware';
import { validateRequest } from '@middleware/validation.middleware';
import { z } from 'zod';

const router = Router();

// Initialize dependencies
const service = new AnalyticsService(prisma);
const controller = new AnalyticsController(service);

// Validation schemas
const statsSchema = z.object({
  query: z.object({
    startDate: z.string().datetime().optional(),
    endDate: z.string().datetime().optional(),
  }),
});

const trendsSchema = z.object({
  query: z.object({
    days: z.string().regex(/^\d+$/).optional(),
  }),
});

// Apply authentication to all routes
router.use(authenticate);

// Routes
router.get('/stats', validateRequest(statsSchema), controller.getStats);
router.get('/trends', validateRequest(trendsSchema), controller.getTrends);

export default router;
