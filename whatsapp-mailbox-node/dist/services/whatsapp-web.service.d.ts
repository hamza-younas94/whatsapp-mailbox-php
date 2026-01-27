import { Client } from 'whatsapp-web.js';
import { EventEmitter } from 'events';
export interface WhatsAppWebSession {
    id: string;
    userId: string;
    client: Client;
    status: 'INITIALIZING' | 'QR_READY' | 'AUTHENTICATED' | 'READY' | 'DISCONNECTED';
    qrCode?: string;
    phoneNumber?: string;
    createdAt: Date;
}
export declare class WhatsAppWebService extends EventEmitter {
    private sessions;
    private initializingSessions;
    private sessionDir;
    constructor();
    /**
     * Initialize a new WhatsApp Web session for a user
     */
    initializeSession(userId: string, sessionId: string): Promise<WhatsAppWebSession>;
    /**
     * Setup event handlers for WhatsApp Web client
     */
    private setupClientEvents;
    /**
     * Send a message via WhatsApp Web
     */
    sendMessage(sessionId: string, to: string, message: string): Promise<{
        success: boolean;
        messageId?: string;
    }>;
    /**
     * Send media message
     */
    sendMediaMessage(sessionId: string, to: string, mediaUrl: string, caption?: string): Promise<{
        success: boolean;
        messageId?: string;
    }>;
    /**
     * Get session status
     */
    getSession(sessionId: string): WhatsAppWebSession | undefined;
    /**
     * Check if a session is currently being initialized
     */
    isInitializing(sessionId: string): boolean;
    /**
     * Get all sessions for a user
     */
    getUserSessions(userId: string): WhatsAppWebSession[];
    /**
     * Check if session is ready
     */
    isSessionReady(sessionId: string): boolean;
    /**
     * Logout and destroy session
     */
    destroySession(sessionId: string): Promise<void>;
    /**
     * Get QR code for session (if available)
     */
    getQRCode(sessionId: string): string | undefined;
    /**
     * Restart a session
     */
    restartSession(sessionId: string): Promise<WhatsAppWebSession>;
}
export declare const whatsappWebService: WhatsAppWebService;
//# sourceMappingURL=whatsapp-web.service.d.ts.map