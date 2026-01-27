"use strict";
// Seed database with initial data
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const client_1 = require("@prisma/client");
const bcryptjs_1 = __importDefault(require("bcryptjs"));
const prisma = new client_1.PrismaClient();
async function main() {
    console.log('🌱 Seeding database...');
    // Default admin
    const adminPassword = await bcryptjs_1.default.hash('Couple@098', 10);
    const admin = await prisma.user.upsert({
        where: { email: 'hamza.younas94@gmail.com' },
        update: {},
        create: {
            email: 'hamza.younas94@gmail.com',
            username: 'hamza',
            passwordHash: adminPassword,
            name: 'Hamza Admin',
            role: 'ADMIN',
            isActive: true,
        },
    });
    console.log('✅ Created admin user:', admin.email);
    // Demo user
    const demoPassword = await bcryptjs_1.default.hash('Demo123!', 10);
    const demo = await prisma.user.upsert({
        where: { email: 'demo@mailbox.com' },
        update: {},
        create: {
            email: 'demo@mailbox.com',
            username: 'demo',
            passwordHash: demoPassword,
            name: 'Demo User',
            role: 'USER',
            isActive: true,
        },
    });
    console.log('✅ Created demo user:', demo.email);
}
main()
    .catch((e) => {
    console.error(e);
    process.exit(1);
})
    .finally(async () => {
    await prisma.$disconnect();
});
//# sourceMappingURL=seed.js.map