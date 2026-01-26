// src/controllers/contact.controller.ts
// Contact HTTP request handlers

import { Request, Response } from 'express';
import { ContactService } from '@services/contact.service';
import { asyncHandler } from '@middleware/error.middleware';
import { CreateContactInput, ContactFilters } from '@types/index';

export class ContactController {
  constructor(private contactService: ContactService) {}

  createContact = asyncHandler(async (req: Request, res: Response) => {
    const { phoneNumber, name, email } = req.body;
    const userId = req.user!.id;

    const input: CreateContactInput = {
      phoneNumber,
      name,
      email,
    };

    const contact = await this.contactService.createContact(userId, input);

    res.status(201).json({
      success: true,
      data: contact,
    });
  });

  getContact = asyncHandler(async (req: Request, res: Response) => {
    const { contactId } = req.params;

    const contact = await this.contactService.getContact(contactId);

    res.status(200).json({
      success: true,
      data: contact,
    });
  });

  searchContacts = asyncHandler(async (req: Request, res: Response) => {
    const { search, tags, isBlocked, limit = 20, offset = 0 } = req.query;
    const userId = req.user!.id;

    const filters: ContactFilters = {
      search: search as string,
      tags: tags ? (Array.isArray(tags) ? tags : [tags as string]) : undefined,
      isBlocked: isBlocked === 'true',
      limit: parseInt(limit as string),
      offset: parseInt(offset as string),
    };

    const result = await this.contactService.searchContacts(userId, filters);

    res.status(200).json({
      success: true,
      data: result,
    });
  });

  updateContact = asyncHandler(async (req: Request, res: Response) => {
    const { contactId } = req.params;
    const { name, email } = req.body;

    const contact = await this.contactService.updateContact(contactId, {
      name,
      email,
    } as any);

    res.status(200).json({
      success: true,
      data: contact,
    });
  });

  deleteContact = asyncHandler(async (req: Request, res: Response) => {
    const { contactId } = req.params;

    await this.contactService.deleteContact(contactId);

    res.status(200).json({
      success: true,
      message: 'Contact deleted',
    });
  });

  blockContact = asyncHandler(async (req: Request, res: Response) => {
    const { contactId } = req.params;

    const contact = await this.contactService.blockContact(contactId);

    res.status(200).json({
      success: true,
      data: contact,
    });
  });
}
