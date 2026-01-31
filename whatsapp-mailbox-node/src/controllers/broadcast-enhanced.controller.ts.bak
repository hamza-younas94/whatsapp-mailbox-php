// src/controllers/broadcast-enhanced.controller.ts
// Enhanced Broadcast HTTP handlers

import { Request, Response } from 'express';
import { BroadcastEnhancedService } from '@services/broadcast-enhanced.service';
import { asyncHandler } from '@middleware/error.middleware';

export class BroadcastEnhancedController {
  constructor(private service: BroadcastEnhancedService) {}

  create = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const broadcast = await this.service.create(userId, req.body);

    res.status(201).json({
      success: true,
      data: broadcast,
      message: 'Broadcast created successfully',
    });
  });

  list = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const { status, limit } = req.query;

    const broadcasts = await this.service.findAll(userId, {
      status: status as any,
      limit: limit ? parseInt(limit as string) : undefined,
    });

    res.status(200).json({
      success: true,
      data: broadcasts,
    });
  });

  getById = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const broadcast = await this.service.findById(id);

    res.status(200).json({
      success: true,
      data: broadcast,
    });
  });

  update = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const broadcast = await this.service.update(id, req.body);

    res.status(200).json({
      success: true,
      data: broadcast,
      message: 'Broadcast updated successfully',
    });
  });

  delete = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    await this.service.delete(id);

    res.status(200).json({
      success: true,
      message: 'Broadcast deleted successfully',
    });
  });

  send = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const result = await this.service.send(id);

    res.status(200).json({
      success: true,
      data: result,
    });
  });

  cancel = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    await this.service.cancel(id);

    res.status(200).json({
      success: true,
      message: 'Broadcast cancelled successfully',
    });
  });

  getAnalytics = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const analytics = await this.service.getAnalytics(id);

    res.status(200).json({
      success: true,
      data: analytics,
    });
  });
}
