import React, { useState, useEffect, useRef, useCallback } from 'react';
import { messageAPI } from '@/api/queries';
import { subscribeToMessage, subscribeToMessageStatus } from '@/api/socket';
import MessageBubble from '@/components/MessageBubble';
import MessageComposer from '@/components/MessageComposer';
import '@/styles/chat-pane.css';

interface Message {
  id: string;
  contactId: string;
  content?: string;
  direction: 'INCOMING' | 'OUTGOING';
  status: 'PENDING' | 'SENT' | 'DELIVERED' | 'READ' | 'FAILED' | 'RECEIVED';
  createdAt: string;
  mediaUrl?: string;
  mediaType?: 'IMAGE' | 'VIDEO' | 'AUDIO' | 'DOCUMENT';
}

interface ChatPaneProps {
  contactId?: string;
  contactName?: string;
  onUnload?: () => void;
}

const ChatPane: React.FC<ChatPaneProps> = ({ contactId, contactName, onUnload }) => {
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const scrollRef = useRef<HTMLDivElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messageSubscriptionRef = useRef<(() => void) | null>(null);
  const statusSubscriptionRef = useRef<(() => void) | null>(null);

  // Load messages from API
  const loadMessages = useCallback(
    async (limit = 50, offset = 0) => {
      if (!contactId) return;
      
      try {
        setLoading(true);
        const response = await messageAPI.getMessagesByContact(contactId, limit, offset);
        
        if (offset === 0) {
          // Initial load: replace messages
          setMessages(response.data.reverse()); // Reverse to show oldest first
        } else {
          // Pagination: prepend older messages
          setMessages((prev) => [...response.data.reverse(), ...prev]);
        }
        
        // Check if there are more messages to load
        setHasMore(response.data.length === limit);
      } catch (error) {
        console.error('Failed to load messages:', error);
      } finally {
        setLoading(false);
      }
    },
    [contactId]
  );

  // Initial load and subscribe to real-time updates
  useEffect(() => {
    if (!contactId) {
      setMessages([]);
      return;
    }

    // Load initial messages
    loadMessages(50, 0);

    // Subscribe to new messages
    messageSubscriptionRef.current = subscribeToMessage((msg) => {
      if (msg.contactId === contactId) {
        setMessages((prev) => [...prev, msg]);
      }
    });

    // Subscribe to message status updates
    statusSubscriptionRef.current = subscribeToMessageStatus((update) => {
      setMessages((prev) =>
        prev.map((msg) =>
          msg.id === update.messageId ? { ...msg, status: update.status } : msg
        )
      );
    });

    return () => {
      messageSubscriptionRef.current?.();
      statusSubscriptionRef.current?.();
    };
  }, [contactId, loadMessages]);

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages]);

  // Handle scroll to load older messages
  const handleScroll = useCallback(() => {
    if (!scrollRef.current || loading || !hasMore) return;

    const { scrollTop } = scrollRef.current;
    if (scrollTop < 100) {
      // Load more when scrolled near top
      loadMessages(50, messages.length);
    }
  }, [loading, hasMore, messages.length, loadMessages]);

  // Handle send message
  const handleSend = async (content: string, mediaUrl?: string) => {
    if (!contactId || (!content && !mediaUrl)) return;

    try {
      setSending(true);
      const sentMessage = await messageAPI.sendMessage(contactId, content, mediaUrl);
      
      // Add message to UI immediately (optimistic update)
      if (sentMessage) {
        setMessages((prev) => [...prev, {
          id: sentMessage.id,
          contactId: sentMessage.contactId || contactId,
          content: sentMessage.content || content,
          direction: 'OUTGOING',
          status: sentMessage.status || 'PENDING',
          createdAt: sentMessage.createdAt || new Date().toISOString(),
          mediaUrl: sentMessage.mediaUrl || mediaUrl,
          mediaType: sentMessage.mediaType,
        }]);
      }
      
      // Reload messages after a short delay to ensure we have the latest
      setTimeout(() => {
        loadMessages(50, 0);
      }, 500);
    } catch (error) {
      console.error('Failed to send message:', error);
      alert('Failed to send message');
    } finally {
      setSending(false);
    }
  };

  if (!contactId) {
    return (
      <div className="chat-pane empty-chat">
        <div className="empty-state-content">
          <div className="empty-icon">💬</div>
          <p className="empty-text">Select a conversation to start chatting</p>
        </div>
      </div>
    );
  }

  return (
    <div className="chat-pane">
      <div className="chat-header">
        <h2 className="contact-name">{contactName || 'Unknown Contact'}</h2>
        <div className="chat-header-actions">
          <button className="icon-button">📞</button>
          <button className="icon-button">ℹ️</button>
        </div>
      </div>

      <div className="messages-container" ref={scrollRef} onScroll={handleScroll}>
        {loading && <div className="loading-indicator">Loading messages...</div>}
        
        <div className="messages-list">
          {messages.map((msg) => (
            <MessageBubble
              key={msg.id}
              message={msg}
              isOwn={msg.direction === 'OUTGOING'}
            />
          ))}
          <div ref={messagesEndRef} />
        </div>
      </div>

      <MessageComposer onSend={handleSend} isLoading={sending} disabled={sending} />
    </div>
  );
};

export default ChatPane;
