import React, { useRef, useState, useEffect } from 'react';
import '@/styles/message-composer.css';

interface QuickReply {
  id: string;
  title: string;
  content: string;
  shortcut: string | null;
  category: string | null;
}

interface MessageComposerProps {
  onSend: (content: string, mediaUrl?: string) => Promise<void>;
  isLoading?: boolean;
  disabled?: boolean;
}

export const MessageComposer: React.FC<MessageComposerProps> = ({ onSend, isLoading = false, disabled = false }) => {
  const [content, setContent] = useState('');
  const [mediaPreview, setMediaPreview] = useState<{ url: string; type: string } | null>(null);
  const [quickReplies, setQuickReplies] = useState<QuickReply[]>([]);
  const [showQuickReplies, setShowQuickReplies] = useState(false);
  const [filteredQuickReplies, setFilteredQuickReplies] = useState<QuickReply[]>([]);
  const [selectedReplyIndex, setSelectedReplyIndex] = useState(0);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Load quick replies on mount
  useEffect(() => {
    loadQuickReplies();
  }, []);

  const loadQuickReplies = async () => {
    try {
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${window.location.origin}/api/v1/quick-replies?limit=100`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      if (response.ok) {
        const result = await response.json();
        setQuickReplies(result.data || []);
      }
    } catch (error) {
      console.error('Failed to load quick replies:', error);
    }
  };

  // Handle quick reply shortcuts
  useEffect(() => {
    if (!content.includes('/')) {
      setShowQuickReplies(false);
      return;
    }

    const lastWord = content.split(' ').pop() || '';
    if (lastWord.startsWith('/') && lastWord.length > 1) {
      const searchTerm = lastWord.substring(1).toLowerCase();
      const filtered = quickReplies.filter(qr => 
        qr.shortcut?.toLowerCase().includes(searchTerm) ||
        qr.title?.toLowerCase().includes(searchTerm)
      );
      setFilteredQuickReplies(filtered);
      setShowQuickReplies(filtered.length > 0);
      setSelectedReplyIndex(0);
    } else {
      setShowQuickReplies(false);
    }
  }, [content, quickReplies]);

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
    if (showQuickReplies) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setSelectedReplyIndex(prev => 
          prev < filteredQuickReplies.length - 1 ? prev + 1 : prev
        );
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        setSelectedReplyIndex(prev => prev > 0 ? prev - 1 : 0);
        return;
      }
      if (e.key === 'Tab' || e.key === 'Enter') {
        e.preventDefault();
        insertQuickReply(filteredQuickReplies[selectedReplyIndex]);
        return;
      }
      if (e.key === 'Escape') {
        setShowQuickReplies(false);
        return;
      }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const insertQuickReply = (reply: QuickReply) => {
    const words = content.split(' ');
    words.pop(); // Remove the /shortcut
    const newContent = words.length > 0 
      ? words.join(' ') + ' ' + reply.content 
      : reply.content;
    setContent(newContent);
    setShowQuickReplies(false);
    textareaRef.current?.focus();
  };

  return (
    <div className="message-composer">
      {/* Quick Replies Dropdown */}
      {showQuickReplies && filteredQuickReplies.length > 0 && (
        <div className="quick-replies-dropdown">
          {filteredQuickReplies.map((reply, index) => (
            <div
              key={reply.id}
              className={`quick-reply-item ${index === selectedReplyIndex ? 'selected' : ''}`}
              onClick={() => insertQuickReply(reply)}
              onMouseEnter={() => setSelectedReplyIndex(index)}
            >
              <div className="qr-header">
                {reply.shortcut && <span className="qr-shortcut">/{reply.shortcut}</span>}
                <span className="qr-title">{reply.title}</span>
              </div>
              <div className="qr-preview">{reply.content.substring(0, 60)}...</div>
            </div>
          ))}
          <div className="qr-hint">
            <span>↑↓ Navigate</span>
            <span>Tab/Enter Select</span>
            <span>Esc Close</span>
          </div>
        </div>
      )}

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
          ref={textareaRef}
          value={content}
          onChange={(e) => setContent(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Type a message... (Use / for quick replies)"
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
