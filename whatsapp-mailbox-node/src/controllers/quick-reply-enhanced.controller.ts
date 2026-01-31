// src/controllers/quick-reply-enhanced.controller.ts
// Enhanced Quick Reply HTTP handlers with categories and analytics

import { Request, Response } from 'express';
import { QuickReplyEnhancedService } from '@services/quick-reply-enhanced.service';
import { asyncHandler } from '@middleware/error.middleware';

export class QuickReplyEnhancedController {
  constructor(private service: QuickReplyEnhancedService) {}

  // Get all quick replies with categories
  getAllWithCategories = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const data = await this.service.findAllWithCategories(userId);

    res.status(200).json({
      success: true,
      data,
    });
  });

  // Create quick reply
  create = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const quickReply = await this.service.create(userId, req.body);

    res.status(201).json({
      success: true,
      data: quickReply,
      message: 'Quick reply created successfully',
    });
  });

  // Update quick reply
  update = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const quickReply = await this.service.update(id, req.body);

    res.status(200).json({
      success: true,
      data: quickReply,
      message: 'Quick reply updated successfully',
    });
  });

  // Delete quick reply
  delete = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    await this.service.delete(id);

    res.status(200).json({
      success: true,
      message: 'Quick reply deleted successfully',
    });
  });

  // Track usage
  trackUsage = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const userId = req.user!.id;
    
    await this.service.trackUsage(id, userId);

    res.status(200).json({
      success: true,
      message: 'Usage tracked successfully',
    });
  });

  // Get analytics
  getAnalytics = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const { startDate, endDate } = req.query;

    const analytics = await this.service.getAnalytics(userId, {
      startDate: startDate ? new Date(startDate as string) : undefined,
      endDate: endDate ? new Date(endDate as string) : undefined,
    });

    res.status(200).json({
      success: true,
      data: analytics,
    });
  });

  // Category management
  createCategory = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const category = await this.service.createCategory(userId, req.body);

    res.status(201).json({
      success: true,
      data: category,
      message: 'Category created successfully',
    });
  });

  updateCategory = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const category = await this.service.updateCategory(id, req.body);

    res.status(200).json({
      success: true,
      data: category,
      message: 'Category updated successfully',
    });
  });

  deleteCategory = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    await this.service.deleteCategory(id);

    res.status(200).json({
      success: true,
      message: 'Category deleted successfully',
    });
  });
}
