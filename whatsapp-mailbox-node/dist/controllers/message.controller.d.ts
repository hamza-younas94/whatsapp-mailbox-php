import { Request, Response } from 'express';
import { MessageService } from '../services/message.service';
export declare class MessageController {
    private messageService;
    constructor(messageService: MessageService);
    listMessages: (req: Request, res: Response, next: import("express").NextFunction) => void;
    sendMessage: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getMessages: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getMessagesByContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
    markAsRead: (req: Request, res: Response, next: import("express").NextFunction) => void;
    deleteMessage: (req: Request, res: Response, next: import("express").NextFunction) => void;
    webhookReceive: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=message.controller.d.ts.map