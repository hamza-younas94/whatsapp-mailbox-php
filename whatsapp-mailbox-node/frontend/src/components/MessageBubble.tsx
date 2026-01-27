import React, { useEffect, useState } from 'react';
import { MessageEvent } from '@/api/socket';
import '@/styles/message-bubble.css';

interface Message {
  id: string;
  content?: string;
  messageType: string;
  direction: 'INCOMING' | 'OUTGOING';
  status: 'PENDING' | 'SENT' | 'DELIVERED' | 'READ' | 'FAILED' | 'RECEIVED';
  mediaUrl?: string;
  mediaType?: string;
  createdAt: string;
}

interface MessageBubbleProps {
  message: Message;
  isOwn: boolean;
}

export const MessageBubble: React.FC<MessageBubbleProps> = ({ message, isOwn }) => {
  const time = new Date(message.createdAt).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  });

  const hasMedia = message.messageType !== 'TEXT' && message.mediaUrl;

  return (
    <div className={`message-bubble ${isOwn ? 'own' : 'other'}`}>
      {/* Media content */}
      {hasMedia && (
        <div className="message-media">
          {message.messageType === 'IMAGE' && (
            <img src={message.mediaUrl} alt="Message" style={{ maxWidth: '200px', borderRadius: '8px' }} />
          )}
          {message.messageType === 'VIDEO' && (
            <video src={message.mediaUrl} controls style={{ maxWidth: '200px', borderRadius: '8px' }} />
          )}
          {message.messageType === 'AUDIO' && (
            <audio src={message.mediaUrl} controls style={{ width: '200px' }} />
          )}
          {message.messageType === 'DOCUMENT' && (
            <a href={message.mediaUrl} target="_blank" rel="noopener noreferrer" className="document-link">
              📎 {message.mediaType || 'Document'}
            </a>
          )}
        </div>
      )}

      {/* Text content */}
      {message.content && <p className="message-text">{message.content}</p>}

      {/* Message meta */}
      <div className="message-meta">
        <span className="time">{time}</span>
        {isOwn && (
          <span className={`status-icon status-${message.status.toLowerCase()}`}>
            {message.status === 'PENDING' && '⏱'}
            {message.status === 'SENT' && '✓'}
            {message.status === 'DELIVERED' && '✓✓'}
            {message.status === 'READ' && '✓✓'}
            {message.status === 'FAILED' && '✗'}
          </span>
        )}
      </div>
    </div>
  );
};

export default MessageBubble;
