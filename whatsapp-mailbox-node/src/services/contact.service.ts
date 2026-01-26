// src/services/contact.service.ts
// Contact management business logic

import { Contact } from '@prisma/client';
import { ContactRepository } from '@repositories/contact.repository';
import { CreateContactInput, ContactFilters, PaginatedResult } from '@types/index';
import { NotFoundError, ValidationError, ConflictError } from '@utils/errors';
import logger from '@utils/logger';

export interface IContactService {
  createContact(userId: string, input: CreateContactInput): Promise<Contact>;
  getContact(id: string): Promise<Contact>;
  searchContacts(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>>;
  updateContact(id: string, data: Partial<Contact>): Promise<Contact>;
  deleteContact(id: string): Promise<void>;
  blockContact(id: string): Promise<Contact>;
  unblockContact(id: string): Promise<Contact>;
}

export class ContactService implements IContactService {
  constructor(private contactRepository: ContactRepository) {}

  async createContact(userId: string, input: CreateContactInput): Promise<Contact> {
    try {
      // Validate phone number format
      const phonePattern = /^\+?[1-9]\d{1,14}$/; // E.164 format
      if (!phonePattern.test(input.phoneNumber)) {
        throw new ValidationError('Invalid phone number format. Use E.164 format (e.g., +1234567890)');
      }

      // Check if contact already exists
      const existing = await this.contactRepository.findByPhoneNumber(userId, input.phoneNumber);
      if (existing) {
        throw new ConflictError(`Contact with phone ${input.phoneNumber} already exists`);
      }

      const contact = await this.contactRepository.create({
        userId,
        phoneNumber: input.phoneNumber,
        name: input.name,
        email: input.email,
      });

      logger.info({ contactId: contact.id }, 'Contact created');
      return contact;
    } catch (error) {
      logger.error({ input, error }, 'Failed to create contact');
      throw error;
    }
  }

  async getContact(id: string): Promise<Contact> {
    try {
      const contact = await this.contactRepository.findById(id);
      if (!contact) {
        throw new NotFoundError('Contact');
      }
      return contact;
    } catch (error) {
      logger.error({ id, error }, 'Failed to get contact');
      throw error;
    }
  }

  async searchContacts(userId: string, filters: ContactFilters): Promise<PaginatedResult<Contact>> {
    try {
      return await this.contactRepository.search(userId, filters);
    } catch (error) {
      logger.error({ filters, error }, 'Failed to search contacts');
      throw error;
    }
  }

  async updateContact(id: string, data: Partial<Contact>): Promise<Contact> {
    try {
      const contact = await this.contactRepository.findById(id);
      if (!contact) {
        throw new NotFoundError('Contact');
      }

      // Validate email if provided
      if (data.email) {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(data.email)) {
          throw new ValidationError('Invalid email format');
        }
      }

      return await this.contactRepository.update(id, data);
    } catch (error) {
      logger.error({ id, data, error }, 'Failed to update contact');
      throw error;
    }
  }

  async deleteContact(id: string): Promise<void> {
    try {
      await this.contactRepository.delete(id);
      logger.info({ id }, 'Contact deleted');
    } catch (error) {
      logger.error({ id, error }, 'Failed to delete contact');
      throw error;
    }
  }

  async blockContact(id: string): Promise<Contact> {
    try {
      return await this.contactRepository.update(id, { isBlocked: true });
    } catch (error) {
      logger.error({ id, error }, 'Failed to block contact');
      throw error;
    }
  }

  async unblockContact(id: string): Promise<Contact> {
    try {
      return await this.contactRepository.update(id, { isBlocked: false });
    } catch (error) {
      logger.error({ id, error }, 'Failed to unblock contact');
      throw error;
    }
  }
}
