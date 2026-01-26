// src/controllers/automation.controller.ts
// Automation HTTP handlers

import { Request, Response } from 'express';
import { AutomationService } from '@services/automation.service';
import { asyncHandler } from '@middleware/error.middleware';

export class AutomationController {
  constructor(private service: AutomationService) {}

  create = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const automation = await this.service.createAutomation(userId, req.body);

    res.status(201).json({
      success: true,
      data: automation,
    });
  });

  list = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const automations = await this.service.getAutomations(userId);

    res.status(200).json({
      success: true,
      data: automations,
    });
  });

  update = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const automation = await this.service.updateAutomation(id, req.body);

    res.status(200).json({
      success: true,
      data: automation,
    });
  });

  delete = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    await this.service.deleteAutomation(id);

    res.status(200).json({
      success: true,
      message: 'Automation deleted',
    });
  });

  toggle = asyncHandler(async (req: Request, res: Response) => {
    const { id } = req.params;
    const { isActive } = req.body;

    const automation = await this.service.toggleAutomation(id, isActive);

    res.status(200).json({
      success: true,
      data: automation,
    });
  });
}
