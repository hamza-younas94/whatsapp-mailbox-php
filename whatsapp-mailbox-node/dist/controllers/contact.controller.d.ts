import { Request, Response } from 'express';
import { ContactService } from '../services/contact.service';
export declare class ContactController {
    private contactService;
    constructor(contactService: ContactService);
    createContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
    getContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
    searchContacts: (req: Request, res: Response, next: import("express").NextFunction) => void;
    updateContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
    deleteContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
    blockContact: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=contact.controller.d.ts.map