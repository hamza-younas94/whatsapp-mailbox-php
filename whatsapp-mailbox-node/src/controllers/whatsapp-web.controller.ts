// src/controllers/whatsapp-web.controller.ts
// WhatsApp Web QR code and session management

import { Request, Response } from 'express';
import { whatsappWebService } from '@services/whatsapp-web.service';
import { asyncHandler } from '@middleware/error.middleware';
import { v4 as uuidv4 } from 'uuid';

export class WhatsAppWebController {
  /**
   * Initialize a new WhatsApp Web session
   */
  initSession = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const sessionId = `session_${userId}_${uuidv4()}`;

    const session = await whatsappWebService.initializeSession(userId, sessionId);

    res.status(200).json({
      success: true,
      data: {
        sessionId: session.id,
        status: session.status,
        message: 'Session initialized. Wait for QR code.',
      },
    });
  });

  /**
   * Get QR code for session
   */
  getQRCode = asyncHandler(async (req: Request, res: Response) => {
    const { sessionId } = req.params;
    const session = whatsappWebService.getSession(sessionId);

    if (!session) {
      return res.status(404).json({
        success: false,
        error: 'Session not found',
      });
    }

    if (session.status === 'READY') {
      return res.status(200).json({
        success: true,
        data: {
          status: 'READY',
          phoneNumber: session.phoneNumber,
          message: 'Session is already authenticated',
        },
      });
    }

    if (!session.qrCode) {
      return res.status(200).json({
        success: true,
        data: {
          status: session.status,
          message: 'QR code not yet available. Please wait...',
        },
      });
    }

    res.status(200).json({
      success: true,
      data: {
        qrCode: session.qrCode,
        status: session.status,
      },
    });
  });

  /**
   * Get session status
   */
  getStatus = asyncHandler(async (req: Request, res: Response) => {
    const { sessionId } = req.params;
    const session = whatsappWebService.getSession(sessionId);

    if (!session) {
      return res.status(404).json({
        success: false,
        error: 'Session not found',
      });
    }

    res.status(200).json({
      success: true,
      data: {
        sessionId: session.id,
        status: session.status,
        phoneNumber: session.phoneNumber,
      },
    });
  });

  /**
   * List all sessions for current user
   */
  listSessions = asyncHandler(async (req: Request, res: Response) => {
    const userId = req.user!.id;
    const sessions = whatsappWebService.getUserSessions(userId);

    res.status(200).json({
      success: true,
      data: sessions.map((s) => ({
        sessionId: s.id,
        status: s.status,
        phoneNumber: s.phoneNumber,
      })),
    });
  });

  /**
   * Send message via WhatsApp Web
   */
  sendMessage = asyncHandler(async (req: Request, res: Response) => {
    const { sessionId } = req.params;
    const { to, message, mediaUrl } = req.body;

    const result = mediaUrl
      ? await whatsappWebService.sendMediaMessage(sessionId, to, mediaUrl, message)
      : await whatsappWebService.sendMessage(sessionId, to, message);

    if (!result.success) {
      return res.status(400).json({
        success: false,
        error: 'Failed to send message',
      });
    }

    res.status(200).json({
      success: true,
      data: {
        messageId: result.messageId,
      },
    });
  });

  /**
   * Logout and destroy session
   */
  logout = asyncHandler(async (req: Request, res: Response) => {
    const { sessionId } = req.params;

    await whatsappWebService.destroySession(sessionId);

    res.status(200).json({
      success: true,
      message: 'Session logged out',
    });
  });

  /**
   * Restart session
   */
  restart = asyncHandler(async (req: Request, res: Response) => {
    const { sessionId } = req.params;

    const session = await whatsappWebService.restartSession(sessionId);

    res.status(200).json({
      success: true,
      data: {
        sessionId: session.id,
        status: session.status,
      },
    });
  });

  /**
   * Stream QR code updates via SSE
   */
  streamQR = asyncHandler(async (req: Request, res: Response) => {
    const { sessionId } = req.params;

    // Set headers for SSE
    res.setHeader('Content-Type', 'text/event-stream');
    res.setHeader('Cache-Control', 'no-cache');
    res.setHeader('Connection', 'keep-alive');

    const sendEvent = (event: string, data: any) => {
      res.write(`event: ${event}\n`);
      res.write(`data: ${JSON.stringify(data)}\n\n`);
    };

    // Send initial session status
    const session = whatsappWebService.getSession(sessionId);
    if (session) {
      sendEvent('status', {
        status: session.status,
        phoneNumber: session.phoneNumber,
      });

      if (session.qrCode) {
        sendEvent('qr', { qrCode: session.qrCode });
      }
    }

    // Listen for session events
    const onQR = (data: any) => {
      if (data.sessionId === sessionId) {
        sendEvent('qr', { qrCode: data.qrCode });
      }
    };

    const onReady = (data: any) => {
      if (data.sessionId === sessionId) {
        sendEvent('ready', { phoneNumber: data.phoneNumber });
      }
    };

    const onDisconnected = (data: any) => {
      if (data.sessionId === sessionId) {
        sendEvent('disconnected', { reason: data.reason });
      }
    };

    whatsappWebService.on('qr', onQR);
    whatsappWebService.on('ready', onReady);
    whatsappWebService.on('disconnected', onDisconnected);

    // Cleanup on close
    req.on('close', () => {
      whatsappWebService.off('qr', onQR);
      whatsappWebService.off('ready', onReady);
      whatsappWebService.off('disconnected', onDisconnected);
    });
  });
}
