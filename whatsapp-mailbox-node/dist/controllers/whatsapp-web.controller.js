"use strict";
// src/controllers/whatsapp-web.controller.ts
// WhatsApp Web QR code and session management
Object.defineProperty(exports, "__esModule", { value: true });
exports.WhatsAppWebController = void 0;
const whatsapp_web_service_1 = require("../services/whatsapp-web.service");
const error_middleware_1 = require("../middleware/error.middleware");
class WhatsAppWebController {
    constructor() {
        /**
         * Initialize or return the current session for the user
         */
        this.initializeDefaultSession = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user?.id || req.user?.userId;
            if (!userId) {
                console.error('ERROR: userId is undefined in initializeDefaultSession', { user: req.user });
                return res.status(401).json({
                    success: false,
                    message: 'Authentication required - userId not found',
                });
            }
            let session = this.getPreferredSession(userId);
            // Use deterministic sessionId per user so sessions persist across PM2 restarts
            const sessionId = `session_${userId}`;
            // Check if session is already being initialized (prevent duplicate calls)
            const existingSession = whatsapp_web_service_1.whatsappWebService.getSession(sessionId);
            if (existingSession) {
                // If session is INITIALIZING, return current status without initializing again
                if (existingSession.status === 'INITIALIZING') {
                    return res.status(200).json({
                        success: true,
                        sessionId: existingSession.id,
                        status: existingSession.status,
                        message: 'Session is already initializing. Please wait...',
                        ...(existingSession.qrCode && { qr: existingSession.qrCode }),
                    });
                }
                // If session exists and is not disconnected, return it immediately
                if (existingSession.status !== 'DISCONNECTED') {
                    return res.status(200).json({
                        success: true,
                        sessionId: existingSession.id,
                        status: existingSession.status,
                        ...(existingSession.status === 'QR_READY' && existingSession.qrCode && { qr: existingSession.qrCode }),
                        ...(existingSession.status === 'READY' && existingSession.phoneNumber && { phoneNumber: existingSession.phoneNumber }),
                    });
                }
            }
            // Check if session is in the initializing set (even if not in sessions map yet)
            if (whatsapp_web_service_1.whatsappWebService.isInitializing(sessionId)) {
                return res.status(200).json({
                    success: true,
                    sessionId,
                    status: 'INITIALIZING',
                    message: 'Session is already initializing. Please wait...',
                });
            }
            // Only initialize if no session exists or session is disconnected
            if (!session || session.status === 'DISCONNECTED') {
                // Initialize new session
                session = await whatsapp_web_service_1.whatsappWebService.initializeSession(userId, sessionId);
            }
            res.status(200).json({
                success: true,
                sessionId: session.id,
                status: session.status,
                ...(session.status === 'QR_READY' && session.qrCode && { qr: session.qrCode }),
                ...(session.status === 'READY' && session.phoneNumber && { phoneNumber: session.phoneNumber }),
            });
        });
        /**
         * Get status for the user's primary session
         */
        this.getDefaultStatus = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user?.id || req.user?.userId;
            if (!userId) {
                console.error('ERROR: userId is undefined in getDefaultStatus', { user: req.user });
                return res.status(401).json({
                    success: false,
                    message: 'Authentication required - userId not found',
                });
            }
            const session = this.getPreferredSession(userId);
            if (!session) {
                return res.status(200).json({
                    success: true,
                    isConnected: false,
                    message: 'No active session. Initialize to start.',
                });
            }
            const payload = {
                success: true,
                sessionId: session.id,
                status: session.status,
                phoneNumber: session.phoneNumber,
            };
            if (session.status === 'READY') {
                return res.status(200).json({
                    ...payload,
                    isConnected: true,
                });
            }
            if (session.status === 'QR_READY' && session.qrCode) {
                return res.status(200).json({
                    ...payload,
                    isConnected: false,
                    qr: session.qrCode,
                });
            }
            return res.status(200).json({
                ...payload,
                isConnected: false,
                message: 'Session initializing. Please wait...',
            });
        });
        /**
         * Disconnect the user's primary session
         */
        this.disconnectDefaultSession = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const session = this.getPreferredSession(userId);
            if (session) {
                await whatsapp_web_service_1.whatsappWebService.destroySession(session.id);
            }
            res.status(200).json({
                success: true,
                message: 'Session disconnected',
            });
        });
        /**
         * Initialize a new WhatsApp Web session
         */
        this.initSession = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            // Use deterministic sessionId per user so sessions persist across PM2 restarts
            const sessionId = `session_${userId}`;
            const session = await whatsapp_web_service_1.whatsappWebService.initializeSession(userId, sessionId);
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
        this.getQRCode = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { sessionId } = req.params;
            const session = whatsapp_web_service_1.whatsappWebService.getSession(sessionId);
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
        this.getStatus = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { sessionId } = req.params;
            const session = whatsapp_web_service_1.whatsappWebService.getSession(sessionId);
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
        this.listSessions = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const userId = req.user.id;
            const sessions = whatsapp_web_service_1.whatsappWebService.getUserSessions(userId);
            res.status(200).json({
                success: true,
                data: sessions.map((s) => ({
                    id: s.id,
                    sessionId: s.id,
                    status: s.status,
                    phoneNumber: s.phoneNumber,
                    createdAt: s.createdAt.toISOString(),
                })),
            });
        });
        /**
         * Send message via WhatsApp Web
         */
        this.sendMessage = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { sessionId } = req.params;
            const { to, message, mediaUrl } = req.body;
            const result = mediaUrl
                ? await whatsapp_web_service_1.whatsappWebService.sendMediaMessage(sessionId, to, mediaUrl, message)
                : await whatsapp_web_service_1.whatsappWebService.sendMessage(sessionId, to, message);
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
        this.logout = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { sessionId } = req.params;
            await whatsapp_web_service_1.whatsappWebService.destroySession(sessionId);
            res.status(200).json({
                success: true,
                message: 'Session logged out',
            });
        });
        /**
         * Restart session
         */
        this.restart = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { sessionId } = req.params;
            const session = await whatsapp_web_service_1.whatsappWebService.restartSession(sessionId);
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
        this.streamQR = (0, error_middleware_1.asyncHandler)(async (req, res) => {
            const { sessionId } = req.params;
            // Set headers for SSE
            res.setHeader('Content-Type', 'text/event-stream');
            res.setHeader('Cache-Control', 'no-cache');
            res.setHeader('Connection', 'keep-alive');
            const sendEvent = (event, data) => {
                res.write(`event: ${event}\n`);
                res.write(`data: ${JSON.stringify(data)}\n\n`);
            };
            // Send initial session status
            const session = whatsapp_web_service_1.whatsappWebService.getSession(sessionId);
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
            const onQR = (data) => {
                if (data.sessionId === sessionId) {
                    sendEvent('qr', { qrCode: data.qrCode });
                }
            };
            const onReady = (data) => {
                if (data.sessionId === sessionId) {
                    sendEvent('ready', { phoneNumber: data.phoneNumber });
                }
            };
            const onDisconnected = (data) => {
                if (data.sessionId === sessionId) {
                    sendEvent('disconnected', { reason: data.reason });
                }
            };
            whatsapp_web_service_1.whatsappWebService.on('qr', onQR);
            whatsapp_web_service_1.whatsappWebService.on('ready', onReady);
            whatsapp_web_service_1.whatsappWebService.on('disconnected', onDisconnected);
            // Cleanup on close
            req.on('close', () => {
                whatsapp_web_service_1.whatsappWebService.off('qr', onQR);
                whatsapp_web_service_1.whatsappWebService.off('ready', onReady);
                whatsapp_web_service_1.whatsappWebService.off('disconnected', onDisconnected);
            });
        });
    }
    getPreferredSession(userId) {
        const sessions = whatsapp_web_service_1.whatsappWebService.getUserSessions(userId);
        if (!sessions.length)
            return undefined;
        const activeSession = sessions.find((s) => s.status !== 'DISCONNECTED');
        return activeSession || sessions[sessions.length - 1];
    }
}
exports.WhatsAppWebController = WhatsAppWebController;
//# sourceMappingURL=whatsapp-web.controller.js.map