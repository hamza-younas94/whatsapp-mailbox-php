import { io, Socket } from 'socket.io-client';

let socket: Socket | null = null;

export function getSocket(): Socket {
  if (!socket) {
    socket = io(window.location.origin, {
      auth: {
        token: localStorage.getItem('authToken'),
      },
    });

    // Event listeners
    socket.on('connect', () => {
      console.log('Socket connected');
    });

    socket.on('disconnect', () => {
      console.log('Socket disconnected');
    });

    socket.on('error', (error) => {
      console.error('Socket error:', error);
    });
  }

  return socket;
}

export function disconnectSocket(): void {
  if (socket) {
    socket.disconnect();
    socket = null;
  }
}

export enum MessageEvent {
  MessageReceived = 'message:received',
  MessageSent = 'message:sent',
  MessageStatusChanged = 'message:status',
  TypingIndicator = 'chat:typing',
  SessionStatus = 'session:status',
  ConversationUpdated = 'conversation:updated',
}

export interface IMessageReceivedEvent {
  id: string;
  contactId: string;
  conversationId: string;
  content: string;
  createdAt: string;
  messageType: string;
}

export interface IMessageSentEvent {
  id: string;
  waMessageId: string;
  status: 'SENT' | 'DELIVERED' | 'READ';
}

export interface ITypingEvent {
  contactId: string;
  isTyping: boolean;
}

export function subscribeToMessage(callback: (msg: IMessageReceivedEvent) => void) {
  const socket = getSocket();
  socket.on(MessageEvent.MessageReceived, callback);
  return () => socket.off(MessageEvent.MessageReceived, callback);
}

export function subscribeToTyping(callback: (event: ITypingEvent) => void) {
  const socket = getSocket();
  socket.on(MessageEvent.TypingIndicator, callback);
  return () => socket.off(MessageEvent.TypingIndicator, callback);
}

export function subscribeToMessageStatus(callback: (event: IMessageSentEvent) => void) {
  const socket = getSocket();
  socket.on(MessageEvent.MessageStatusChanged, callback);
  return () => socket.off(MessageEvent.MessageStatusChanged, callback);
}

export function subscribeToSessionStatus(callback: (status: any) => void) {
  const socket = getSocket();
  socket.on(MessageEvent.SessionStatus, callback);
  return () => socket.off(MessageEvent.SessionStatus, callback);
}
