// src/controllers/message.controller.ts
// Message HTTP request handlers

import { Request, Response } from 'express';
import { MessageService } from '@services/message.service';
import { asyncHandler } from '@middleware/error.middleware';
import { CreateMessageInput } from '@types/index';
import logger from '@utils/logger';

export class MessageController {
  constructor(private messageService: MessageService) {}

  sendMessage = asyncHandler(async (req: Request, res: Response) => {
    const { contactId, content, mediaUrl } = req.body;
    const userId = req.user!.id;

    const input: CreateMessageInput = {
      contactId,
      content,
      mediaUrl,
    };

    const message = await this.messageService.sendMessage(userId, input);

    res.status(201).json({
      success: true,
      data: message,
    });
  });

  getMessages = asyncHandler(async (req: Request, res: Response) => {
    const { conversationId } = req.params;
    const { limit = 50, offset = 0 } = req.query;
    const userId = req.user!.id;

    const result = await this.messageService.getMessages(
      userId,
      conversationId,
      parseInt(limit as string),
      parseInt(offset as string),
    );

    res.status(200).json({
      success: true,
      data: result,
    });
  });

  markAsRead = asyncHandler(async (req: Request, res: Response) => {
    const { messageId } = req.params;

    const message = await this.messageService.markAsRead(messageId);

    res.status(200).json({
      success: true,
      data: message,
    });
  });

  deleteMessage = asyncHandler(async (req: Request, res: Response) => {
    const { messageId } = req.params;

    await this.messageService.deleteMessage(messageId);

    res.status(200).json({
      success: true,
      message: 'Message deleted',
    });
  });

  webhookReceive = asyncHandler(async (req: Request, res: Response) => {
    // WhatsApp webhook handler
    const payload = req.body;

    logger.info({ payload }, 'Received webhook');

    // Verify webhook signature
    // ... verification logic

    // Process message
    if (payload.entry?.[0]?.changes?.[0]?.value?.messages?.[0]) {
      const message = payload.entry[0].changes[0].value.messages[0];
      // Handle message...
    }

    res.status(200).json({ success: true });
  });
}
