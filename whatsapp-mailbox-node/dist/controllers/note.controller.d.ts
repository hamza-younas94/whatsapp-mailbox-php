import { Request, Response } from 'express';
import { NoteService } from '../services/note.service';
export declare class NoteController {
    private service;
    constructor(service: NoteService);
    create: (req: Request, res: Response, next: import("express").NextFunction) => void;
    list: (req: Request, res: Response, next: import("express").NextFunction) => void;
    update: (req: Request, res: Response, next: import("express").NextFunction) => void;
    delete: (req: Request, res: Response, next: import("express").NextFunction) => void;
}
//# sourceMappingURL=note.controller.d.ts.map