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
  messageType?: string;
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
  contactType?: string | null;
  profilePic?: string | null;
  phoneNumber?: string;
  onUnload?: () => void;
}

const ChatPane: React.FC<ChatPaneProps> = ({ contactId, contactName, chatId, contactType, profilePic, phoneNumber, onUnload }) => {
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const [showContactInfo, setShowContactInfo] = useState(false);
  const [contactTags, setContactTags] = useState<Array<{ id: string; name: string }>>([]);
  const [newTag, setNewTag] = useState('');
  const scrollRef = useRef<HTMLDivElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messageSubscriptionRef = useRef<(() => void) | null>(null);
  const statusSubscriptionRef = useRef<(() => void) | null>(null);

  // Get contact type for display
  const contactTypeResolved = getContactTypeFromId(chatId, undefined, contactType);
  const typeInfo = getContactTypeInfo(contactTypeResolved);
  const badgeClass = getContactTypeBadgeClass(contactTypeResolved);

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
          
          // Mark unread messages as read
          const unreadMessages = response.data.filter(
            (msg: any) => msg.direction === 'INCOMING' && msg.status !== 'READ'
          );
          unreadMessages.forEach((msg: any) => {
            messageAPI.markAsRead(msg.id).catch((err) => 
              console.error('Failed to mark message as read:', err)
            );
          });
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
        setMessages((prev) => {
          if (prev.some((m) => m.id === msg.id)) return prev;
          return [...prev, {
            id: msg.id,
            contactId: msg.contactId,
            conversationId: msg.conversationId,
            content: msg.content,
            messageType: msg.messageType,
            direction: msg.direction || 'INCOMING',
            status: msg.status || 'RECEIVED',
            createdAt: msg.createdAt,
            mediaUrl: msg.mediaUrl || undefined,
            mediaType: msg.mediaType as any,
          }];
        });
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
    
    return () => {
      messageSubscriptionRef.current?.();
      statusSubscriptionRef.current?.();
      unsubscribeReactions?.();
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

  // Handle phone call
  const handleCall = () => {
    if (phoneNumber) {
      // Open phone dialer with the number
      window.open(`tel:${phoneNumber}`, '_self');
    } else {
      alert('Phone number not available for this contact');
    }
  };

  // Load contact tags
  useEffect(() => {
    if (contactId && showContactInfo) {
      loadContactTags();
    }
  }, [contactId, showContactInfo]);

  const loadContactTags = async () => {
    try {
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${window.location.origin}/api/v1/contacts/${contactId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (response.ok) {
        const result = await response.json();
        const contact = result.data || result;
        const tags = contact.tags || [];
        setContactTags(
          tags
            .map((t: any) => t.tag || t)
            .filter((t: any) => t && t.id && t.name)
            .map((t: any) => ({ id: t.id, name: t.name }))
        );
      }
    } catch (error) {
      console.error('Failed to load contact tags:', error);
    }
  };

  const handleAddTag = async () => {
    const tagName = newTag.trim();
    if (!tagName || !contactId) return;

    try {
      const token = localStorage.getItem('authToken');

      // Fetch existing tags to avoid duplicates
      const listResponse = await fetch(`${window.location.origin}/api/v1/tags`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });

      let tagId: string | undefined;
      if (listResponse.ok) {
        const listResult = await listResponse.json();
        const tags = listResult.data || [];
        const existing = tags.find((t: any) => (t.name || '').toLowerCase() === tagName.toLowerCase());
        if (existing) {
          tagId = existing.id;
        }
      }

      // Create tag if it doesn't exist
      if (!tagId) {
        const createResponse = await fetch(`${window.location.origin}/api/v1/tags`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ name: tagName })
        });
        if (createResponse.ok) {
          const created = await createResponse.json();
          tagId = created.data?.id;
        }
      }

      if (!tagId) return;

      // Link tag to contact
      const linkResponse = await fetch(`${window.location.origin}/api/v1/tags/contacts`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ contactId, tagId })
      });

      if (linkResponse.ok) {
        setContactTags((prev) => [...prev, { id: tagId as string, name: tagName }]);
        setNewTag('');
      }
    } catch (error) {
      console.error('Failed to add tag:', error);
    }
  };

  const handleRemoveTag = async (tagId: string) => {
    if (!contactId) return;

    try {
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${window.location.origin}/api/v1/tags/contacts/${contactId}/${tagId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });

      if (response.ok) {
        setContactTags((prev) => prev.filter((t) => t.id !== tagId));
      }
    } catch (error) {
      console.error('Failed to remove tag:', error);
    }
  };

  const handleOpenCRM = () => {
    // Open CRM page with this contact
    window.open(`/crm_dashboard.php?contact=${contactId}`, '_blank');
  };

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
        <div className="chat-header-info">
          <div className="chat-header-avatar">
            {profilePic ? (
              <img src={profilePic} alt={contactName || 'Contact'} className="header-avatar-img" />
            ) : (
              <div className="header-avatar-placeholder">{contactName?.[0] || '?'}</div>
            )}
          </div>
          <div className="chat-header-text">
            <h3 className="contact-name">{contactName || 'Unknown'}</h3>
            {phoneNumber && !phoneNumber.includes('@g.us') && <p className="contact-phone">{phoneNumber}</p>}
          </div>
        </div>
        <div className="chat-header-actions">
          <button 
            className="icon-button" 
            onClick={handleCall}
            title="Call"
          >
            📞
          </button>
          <button 
            className="icon-button"
            onClick={() => setShowContactInfo(!showContactInfo)}
            title="Contact Info"
          >
            ℹ️
          </button>
        </div>
      </div>

      {/* Contact Info Panel */}
      {showContactInfo && (
        <div className="contact-info-panel">
          <div className="contact-info-header">
            <h3>Contact Information</h3>
            <button 
              className="close-info-btn" 
              onClick={() => setShowContactInfo(false)}
            >
              ✕
            </button>
          </div>
          <div className="contact-info-content">
            {profilePic && (
              <div className="info-avatar">
                <img src={profilePic} alt={contactName || 'Contact'} />
              </div>
            )}
            <div className="info-item">
              <label>Name:</label>
              <span>{contactName || 'Unknown'}</span>
            </div>
            {phoneNumber && (
              <div className="info-item">
                <label>Phone:</label>
                <span>{phoneNumber}</span>
              </div>
            )}
            <div className="info-item">
              <label>Type:</label>
              <span className={badgeClass}>
                {typeInfo.icon} {typeInfo.label}
              </span>
            </div>

            {/* Tags Section */}
            <div className="info-item">
              <label>Tags:</label>
              <div className="tags-container">
                {contactTags.map((tag) => (
                  <span key={tag.id} className="tag-badge">
                    {tag.name}
                    <button 
                      className="tag-remove-btn"
                      onClick={() => handleRemoveTag(tag.id)}
                    >
                      ✕
                    </button>
                  </span>
                ))}
              </div>
              <div className="tag-input-group">
                <input 
                  type="text"
                  placeholder="Add tag..."
                  value={newTag}
                  onChange={(e) => setNewTag(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleAddTag()}
                  className="tag-input"
                />
                <button onClick={handleAddTag} className="tag-add-btn">
                  Add
                </button>
              </div>
            </div>

            {/* CRM Integration */}
            <div className="info-actions">
              <button onClick={handleOpenCRM} className="crm-btn">
                📊 Open in CRM
              </button>
              <button 
                onClick={() => window.open(`/automation.html?contact=${contactId}`, '_blank')}
                className="automation-btn"
              >
                ⚡ Automations
              </button>
            </div>

            {chatId && (
              <div className="info-item">
                <label>Chat ID:</label>
                <span className="text-sm">{chatId}</span>
              </div>
            )}
          </div>
        </div>
      )}

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
