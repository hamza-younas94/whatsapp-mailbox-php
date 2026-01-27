import React, { useRef, useState } from 'react';
import '@/styles/message-composer.css';

interface MessageComposerProps {
  onSend: (content: string, mediaUrl?: string) => Promise<void>;
  isLoading?: boolean;
  disabled?: boolean;
}

export const MessageComposer: React.FC<MessageComposerProps> = ({ onSend, isLoading = false, disabled = false }) => {
  const [content, setContent] = useState('');
  const [mediaPreview, setMediaPreview] = useState<{ url: string; type: string } | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleSend = async () => {
    if (!content.trim() && !mediaPreview) return;

    try {
      await onSend(content.trim(), mediaPreview?.url);
      setContent('');
      setMediaPreview(null);
    } catch (err) {
      console.error('Failed to send message:', err);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate file size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
      alert('File too large. Max 10MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      const url = event.target?.result as string;
      setMediaPreview({
        url,
        type: file.type,
      });
    };
    reader.readAsDataURL(file);
  };

  const handleClearMedia = () => {
    setMediaPreview(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  return (
    <div className="message-composer">
      {/* Media preview */}
      {mediaPreview && (
        <div className="media-preview">
          {mediaPreview.type.startsWith('image/') && (
            <img src={mediaPreview.url} alt="Preview" />
          )}
          {mediaPreview.type.startsWith('video/') && (
            <video src={mediaPreview.url} controls />
          )}
          <button
            className="clear-btn"
            onClick={handleClearMedia}
            title="Remove media"
          >
            ✕
          </button>
        </div>
      )}

      {/* Input area */}
      <div className="composer-input-row">
        <button
          className="attach-btn"
          onClick={() => fileInputRef.current?.click()}
          disabled={disabled || isLoading}
          title="Attach media"
        >
          📎
        </button>

        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Type a message..."
          disabled={disabled || isLoading}
          className="input-field"
          rows={1}
        />

        <button
          className="send-btn"
          onClick={handleSend}
          disabled={disabled || isLoading || (!content.trim() && !mediaPreview)}
          title="Send message"
        >
          {isLoading ? '⏳' : '➤'}
        </button>

        <input
          ref={fileInputRef}
          type="file"
          accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
          onChange={handleFileSelect}
          style={{ display: 'none' }}
        />
      </div>
    </div>
  );
};

export default MessageComposer;
