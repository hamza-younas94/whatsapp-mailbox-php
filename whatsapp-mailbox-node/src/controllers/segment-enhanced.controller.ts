// src/controllers/segment-enhanced.controller.ts
// Enhanced Segment HTTP handlers

import { Request, Response } from 'express';
import { SegmentEnhancedService } from '@services/segment-enhanced.service';
import { asyncHandler } from '@middleware/error.middleware';

export class SegmentEnhancedController {
  constructor(private service: SegmentEnhancedService) {}

  create = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const segment = await this.service.create(userId, req.body);

    res.status(201).json({
      success: true,
      data: segment,
      message: 'Segment created successfully',
    });
  });

  list = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const segments = await this.service.findAll(userId);

    res.status(200).json({
      success: true,
      data: segments,
    });
  });

  getById = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const segment = await this.service.findById(id);

    res.status(200).json({
      success: true,
      data: segment,
    });
  });

  update = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const segment = await this.service.update(id, req.body);

    res.status(200).json({
      success: true,
      data: segment,
      message: 'Segment updated successfully',
    });
  });

  delete = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    await this.service.delete(id);

    res.status(200).json({
      success: true,
      message: 'Segment deleted successfully',
    });
  });

  preview = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const { criteria } = req.body;

    const result = await this.service.preview(userId, criteria);

    res.status(200).json({
      success: true,
      data: result,
    });
  });

  getContacts = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const { limit } = req.query;

    const contacts = await this.service.getContacts(id, limit ? parseInt(limit as string) : 100);

    res.status(200).json({
      success: true,
      data: contacts,
    });
  });

  refresh = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const result = await this.service.refresh(id);

    res.status(200).json({
      success: true,
      data: result,
      message: 'Segment refreshed successfully',
    });
  });
}
