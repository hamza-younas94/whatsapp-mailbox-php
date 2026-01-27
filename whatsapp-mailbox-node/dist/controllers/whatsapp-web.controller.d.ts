import { Request, Response } from 'express';
export declare class WhatsAppWebController {
    private getPreferredSession;
    /**
     * Initialize or return the current session for the user
     */
    initializeDefaultSession: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Get status for the user's primary session
     */
    getDefaultStatus: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Disconnect the user's primary session
     */
    disconnectDefaultSession: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Initialize a new WhatsApp Web session
     */
    initSession: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Get QR code for session
     */
    getQRCode: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Get session status
     */
    getStatus: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * List all sessions for current user
     */
    listSessions: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Send message via WhatsApp Web
     */
    sendMessage: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Logout and destroy session
     */
    logout: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Restart session
     */
    restart: (req: Request, res: Response, next: import("express").NextFunction) => void;
    /**
     * Stream QR code updates via SSE
     */
    streamQR: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=whatsapp-web.controller.d.ts.map