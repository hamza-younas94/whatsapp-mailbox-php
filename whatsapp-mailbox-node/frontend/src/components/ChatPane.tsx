import React, { useState, useEffect, useRef, useCallback } from 'react';
import { messageAPI } from '@/api/queries';
import { subscribeToMessage, subscribeToMessageStatus, subscribeToReactionUpdated, getSocket } from '@/api/socket';
import { getContactTypeFromId, getContactTypeInfo, getContactTypeBadgeClass } from '@/utils/contact-type';
import MessageBubble from '@/components/MessageBubble';
import MessageComposer from '@/components/MessageComposer';
import '@/styles/chat-pane.css';
import '@/styles/contact-type-badge.css';

interface Message {
  id: string;
  contactId: string;
  conversationId?: string;
  content?: string;
  direction: 'INCOMING' | 'OUTGOING';
  status: 'PENDING' | 'SENT' | 'DELIVERED' | 'READ' | 'FAILED' | 'RECEIVED';
  createdAt: string;
  mediaUrl?: string;
  mediaType?: 'IMAGE' | 'VIDEO' | 'AUDIO' | 'DOCUMENT';
  reaction?: string | null;
  metadata?: any;
}

interface ChatPaneProps {
  contactId?: string;
  contactName?: string;
  chatId?: string;
  onUnload?: () => void;
}

const ChatPane: React.FC<ChatPaneProps> = ({ contactId, contactName, chatId, onUnload }) => {
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const scrollRef = useRef<HTMLDivElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messageSubscriptionRef = useRef<(() => void) | null>(null);
  const statusSubscriptionRef = useRef<(() => void) | null>(null);

  // Get contact type for display
  const contactType = getContactTypeFromId(chatId);
  const typeInfo = getContactTypeInfo(contactType);
  const badgeClass = getContactTypeBadgeClass(contactType);

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

    // Subscribe to new messages (incoming)
    messageSubscriptionRef.current = subscribeToMessage((msg) => {
      if (msg.contactId === contactId) {
        setMessages((prev) => [...prev, {
          id: msg.id,
          contactId: msg.contactId,
          content: msg.content,
          direction: 'INCOMING',
          status: 'RECEIVED',
          createdAt: msg.createdAt,
        }]);
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

    // Subscribe to reaction updates
    const unsubscribeReactions = subscribeToReactionUpdated((event) => {
      setMessages((prev) =>
        prev.map((msg) =>
          msg.id === event.messageId
            ? {
                ...msg,
                reaction: event.reaction || null,
                metadata: {
                  ...(typeof msg.metadata === 'object' ? msg.metadata : {}),
                  reaction: event.reaction || null,
                },
              }
            : msg
        )
      );
    });
    
    // Also subscribe to message:sent event for outgoing messages
    const socket = getSocket();
    const handleMessageSent = (msg: any) => {
      if (msg.contactId === contactId || msg.conversationId) {
        // Reload messages to get the latest
        setTimeout(() => loadMessages(50, 0), 300);
      }
    };
    socket.on('message:sent', handleMessageSent);
    
    return () => {
      messageSubscriptionRef.current?.();
      statusSubscriptionRef.current?.();
      unsubscribeReactions?.();
      socket.off('message:sent', handleMessageSent);
    };

    // Cleanup is handled in the subscription setup above
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
      
      // Add optimistic message immediately
      const tempId = `temp-${Date.now()}`;
      const optimisticMessage: Message = {
        id: tempId,
        contactId,
        content,
        direction: 'OUTGOING',
        status: 'PENDING',
        createdAt: new Date().toISOString(),
        mediaUrl,
      };
      setMessages((prev) => [...prev, optimisticMessage]);
      
      // Send message
      const sentMessage = await messageAPI.sendMessage(contactId, content, mediaUrl);
      
      // Replace optimistic message with real message
      if (sentMessage) {
        setMessages((prev) => 
          prev.map(msg => 
            msg.id === tempId 
              ? {
                  id: sentMessage.id,
                  contactId: sentMessage.contactId || contactId,
                  content: sentMessage.content || content,
                  direction: 'OUTGOING',
                  status: sentMessage.status || 'SENT',
                  createdAt: sentMessage.createdAt || new Date().toISOString(),
                  mediaUrl: sentMessage.mediaUrl || mediaUrl,
                  mediaType: sentMessage.mediaType,
                }
              : msg
          )
        );
      }
      
      // Reload messages after a short delay to ensure we have the latest from server
      setTimeout(() => {
        loadMessages(50, 0);
      }, 1000);
    } catch (error) {
      console.error('Failed to send message:', error);
      // Remove optimistic message on error
      setMessages((prev) => prev.filter(msg => msg.id !== `temp-${Date.now()}`));
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
        <div className="chat-header-title">
          <h2 className="contact-name">{contactName || 'Unknown Contact'}</h2>
          <span className={badgeClass} title={typeInfo.label}>
            {typeInfo.icon} {typeInfo.label}
          </span>
        </div>
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
