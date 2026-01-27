"use strict";
// src/services/crm.service.ts
// CRM deals and pipeline management
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.CRMService = void 0;
const logger_1 = __importDefault(require("../utils/logger"));
const errors_1 = require("../utils/errors");
class CRMService {
    constructor(prisma) {
        this.prisma = prisma;
    }
    async createDeal(userId, data) {
        // Verify contact exists
        const contact = await this.prisma.contact.findFirst({
            where: { id: data.contactId, userId },
        });
        if (!contact) {
            throw new errors_1.NotFoundError('Contact not found');
        }
        const deal = await this.prisma.$executeRaw `
      INSERT INTO deals (id, user_id, contact_id, title, value, stage, expected_close_date, description, status, created_at, updated_at)
      VALUES (
        UUID(),
        ${userId},
        ${data.contactId},
        ${data.title},
        ${data.value || 0},
        ${data.stage},
        ${data.expectedCloseDate || null},
        ${data.description || null},
        'OPEN',
        NOW(),
        NOW()
      )
    `;
        logger_1.default.info({ contactId: data.contactId, title: data.title }, 'Deal created');
        // Fetch the created deal
        const createdDeal = await this.prisma.$queryRaw `
      SELECT * FROM deals WHERE contact_id = ${data.contactId} ORDER BY created_at DESC LIMIT 1
    `;
        return createdDeal;
    }
    async getDeals(userId, filters) {
        let whereClause = `user_id = '${userId}'`;
        if (filters?.status) {
            whereClause += ` AND status = '${filters.status}'`;
        }
        if (filters?.stage) {
            whereClause += ` AND stage = '${filters.stage}'`;
        }
        const deals = await this.prisma.$queryRawUnsafe(`
      SELECT 
        d.*,
        c.name as contact_name,
        c.phone_number as contact_phone
      FROM deals d
      LEFT JOIN contacts c ON d.contact_id = c.id
      WHERE ${whereClause}
      ORDER BY d.created_at DESC
    `);
        return deals;
    }
    async updateDeal(dealId, data) {
        const updates = [];
        if (data.title)
            updates.push(`title = '${data.title}'`);
        if (data.value !== undefined)
            updates.push(`value = ${data.value}`);
        if (data.stage)
            updates.push(`stage = '${data.stage}'`);
        if (data.status)
            updates.push(`status = '${data.status}'`);
        if (data.description)
            updates.push(`description = '${data.description}'`);
        if (data.expectedCloseDate)
            updates.push(`expected_close_date = '${data.expectedCloseDate.toISOString()}'`);
        updates.push(`updated_at = NOW()`);
        await this.prisma.$executeRawUnsafe(`
      UPDATE deals SET ${updates.join(', ')} WHERE id = '${dealId}'
    `);
        const updated = await this.prisma.$queryRaw `SELECT * FROM deals WHERE id = ${dealId}`;
        logger_1.default.info({ dealId }, 'Deal updated');
        return updated;
    }
    async moveDealToStage(dealId, stage) {
        await this.prisma.$executeRaw `
      UPDATE deals SET stage = ${stage}, updated_at = NOW() WHERE id = ${dealId}
    `;
        const deal = await this.prisma.$queryRaw `SELECT * FROM deals WHERE id = ${dealId}`;
        logger_1.default.info({ dealId, stage }, 'Deal moved to stage');
        return deal;
    }
    async getDealStats(userId) {
        const stats = await this.prisma.$queryRaw `
      SELECT 
        COUNT(*) as total_deals,
        SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) as open_deals,
        SUM(CASE WHEN status = 'WON' THEN 1 ELSE 0 END) as won_deals,
        SUM(CASE WHEN status = 'LOST' THEN 1 ELSE 0 END) as lost_deals,
        SUM(CASE WHEN status = 'WON' THEN value ELSE 0 END) as total_revenue
      FROM deals
      WHERE user_id = ${userId}
    `;
        return stats;
    }
}
exports.CRMService = CRMService;
//# sourceMappingURL=crm.service.js.map