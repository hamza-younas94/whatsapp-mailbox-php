import React, { useEffect, useState } from 'react';
import { MessageEvent } from '@/api/socket';
import { messageAPI } from '@/api/queries';
import '@/styles/message-bubble-enhanced.css';

interface Message {
  id: string;
  content?: string;
  messageType: string;
  direction: 'INCOMING' | 'OUTGOING';
  status: 'PENDING' | 'SENT' | 'DELIVERED' | 'READ' | 'FAILED' | 'RECEIVED';
  mediaUrl?: string;
  mediaType?: string;
  createdAt: string;
  reaction?: string;
  metadata?: any;
}

interface MessageBubbleProps {
  message: Message;
  isOwn: boolean;
}

const REACTION_EMOJIS = ['❤️', '👍', '😂', '😮', '😢', '🙏'];

export const MessageBubble: React.FC<MessageBubbleProps> = ({ message, isOwn }) => {
  const [showReactions, setShowReactions] = useState(false);
  const [selectedReaction, setSelectedReaction] = useState<string | undefined>(
    message.reaction || message.metadata?.reaction
  );
  const [isLoading, setIsLoading] = useState(false);

  const time = new Date(message.createdAt).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  });

  const hasMedia = message.messageType !== 'TEXT' && message.mediaUrl;

  const handleReaction = async (emoji: string) => {
    try {
      setIsLoading(true);
      
      // Toggle reaction off if same emoji clicked again
      const newReaction = selectedReaction === emoji ? undefined : emoji;
      
      // Call API to send reaction
      await messageAPI.sendReaction(message.id, newReaction || '');
      
      // Update local state
      setSelectedReaction(newReaction);
      setShowReactions(false);
    } catch (error) {
      console.error('Failed to send reaction:', error);
      // Reset state on error
      setSelectedReaction(selectedReaction);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className={`message-bubble-wrapper ${isOwn ? 'own' : 'other'}`}>
      <div 
        className={`message-bubble ${isOwn ? 'own' : 'other'}`}
        onMouseEnter={() => setShowReactions(true)}
        onMouseLeave={() => setShowReactions(false)}
      >
        {/* Media content */}
        {hasMedia && (
          <div className="message-media">
            {message.messageType === 'IMAGE' && (
              <img 
                src={message.mediaUrl} 
                alt="Message" 
                className="media-image"
                onClick={() => window.open(message.mediaUrl, '_blank')}
                onError={(e) => {
                  console.error('Image failed to load:', message.mediaUrl);
                  (e.target as HTMLImageElement).style.display = 'none';
                }}
              />
            )}
            {message.messageType === 'VIDEO' && (
              <video 
                src={message.mediaUrl} 
                controls 
                className="media-video"
                onError={(e) => {
                  console.error('Video failed to load:', message.mediaUrl);
                  (e.target as HTMLVideoElement).style.display = 'none';
                }}
              />
            )}
            {message.messageType === 'AUDIO' && (
              <div className="media-audio-wrapper">
                <div className="audio-icon">🎵</div>
                <audio 
                  src={message.mediaUrl} 
                  controls 
                  className="media-audio"
                  onError={(e) => {
                    console.error('Audio failed to load:', message.mediaUrl);
                    (e.target as HTMLAudioElement).style.display = 'none';
                  }}
                />
              </div>
            )}
            {message.messageType === 'DOCUMENT' && (
              <a href={message.mediaUrl} target="_blank" rel="noopener noreferrer" className="document-link">
                <span className="doc-icon">📎</span>
                <span className="doc-text">{message.mediaType || 'Document'}</span>
                <span className="doc-download">↓</span>
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
              {message.status === 'PENDING' && <span className="status-pending">⏱</span>}
              {message.status === 'SENT' && <span className="status-sent">✓</span>}
              {message.status === 'DELIVERED' && <span className="status-delivered">✓✓</span>}
              {message.status === 'READ' && <span className="status-read">✓✓</span>}
              {message.status === 'FAILED' && <span className="status-failed">✗</span>}
            </span>
          )}
        </div>

        {/* Selected Reaction */}
        {selectedReaction && (
          <div className="message-reaction">
            {selectedReaction}
          </div>
        )}

        {/* Reaction Picker */}
        {showReactions && (
          <div className={`reaction-picker ${isOwn ? 'own' : 'other'}`}>
            {REACTION_EMOJIS.map(emoji => (
              <button
                key={emoji}
                className={`reaction-btn ${selectedReaction === emoji ? 'selected' : ''}`}
                onClick={() => handleReaction(emoji)}
                title={`React with ${emoji}`}
              >
                {emoji}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default MessageBubble;
