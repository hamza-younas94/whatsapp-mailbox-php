import { PrismaClient, User } from '@prisma/client';
export interface RegisterData {
    email: string;
    username: string;
    password: string;
    name?: string;
}
export interface LoginData {
    email: string;
    password: string;
}
export interface AuthResponse {
    user: Omit<User, 'passwordHash'>;
    token: string;
    refreshToken: string;
}
export interface IAuthService {
    register(data: RegisterData): Promise<AuthResponse>;
    login(data: LoginData): Promise<AuthResponse>;
    refreshToken(refreshToken: string): Promise<{
        token: string;
    }>;
    verifyToken(token: string): Promise<User>;
}
export declare class AuthService implements IAuthService {
    private prisma;
    constructor(prisma: PrismaClient);
    register(data: RegisterData): Promise<AuthResponse>;
    login(data: LoginData): Promise<AuthResponse>;
    refreshToken(refreshToken: string): Promise<{
        token: string;
    }>;
    verifyToken(token: string): Promise<User>;
    private generateToken;
}
//# sourceMappingURL=auth.service.d.ts.map