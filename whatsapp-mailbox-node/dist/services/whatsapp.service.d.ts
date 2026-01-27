export interface IWhatsAppService {
    sendMessage(phoneNumber: string, content: string, mediaUrl?: string): Promise<{
        messageId: string;
    }>;
    getMediaUrl(mediaId: string): Promise<string>;
}
export declare class WhatsAppService implements IWhatsAppService {
    private client;
    private env;
    constructor();
    sendMessage(phoneNumber: string, content: string, mediaUrl?: string): Promise<{
        messageId: string;
    }>;
    getMediaUrl(mediaId: string): Promise<string>;
}
//# sourceMappingURL=whatsapp.service.d.ts.map