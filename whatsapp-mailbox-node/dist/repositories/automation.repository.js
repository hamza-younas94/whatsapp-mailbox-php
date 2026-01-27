"use strict";
// src/repositories/automation.repository.ts
// Automation rules data access
Object.defineProperty(exports, "__esModule", { value: true });
exports.AutomationRepository = void 0;
const base_repository_1 = require("./base.repository");
class AutomationRepository extends base_repository_1.BaseRepository {
    constructor(prisma) {
        super(prisma);
        this.modelName = 'automation';
    }
    async findByTrigger(trigger) {
        return this.prisma.automation.findMany({
            where: { trigger, isActive: true },
        });
    }
    async findActive(userId) {
        return this.prisma.automation.findMany({
            where: { userId, isActive: true },
            orderBy: { createdAt: 'desc' },
        });
    }
}
exports.AutomationRepository = AutomationRepository;
//# sourceMappingURL=automation.repository.js.map