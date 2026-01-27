import React, { useEffect, useState } from 'react';
import { contactAPI } from '@/api/queries';
import '@/styles/conversation-list.css';

interface Conversation {
  id: string;
  contact: {
    id: string;
    phoneNumber: string;
    name?: string;
  };
  unreadCount: number;
  lastMessage?: string;
  lastMessageAt?: string;
}

interface ConversationListProps {
  onSelectConversation: (contactId: string, conversation: Conversation) => void;
  selectedContactId?: string;
}

export const ConversationList: React.FC<ConversationListProps> = ({
  onSelectConversation,
  selectedContactId,
}) => {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');

  useEffect(() => {
    loadConversations();
  }, []);

  const loadConversations = async () => {
    try {
      setLoading(true);
      const response = await contactAPI.searchContacts(search || undefined, 100, 0);
      
      // Handle both direct array and paginated response
      const contacts = Array.isArray(response) ? response : (response?.data || []);
      
      // Transform contacts into conversation format
      const transformedConversations: Conversation[] = contacts
        .filter((contact: any) => {
          // Filter out null/undefined
          return contact && contact.id;
        })
        .map((contact: any) => {
          // Get the last message from the messages array (already sorted by createdAt desc)
          const lastMessageObj = contact.messages && contact.messages.length > 0 
            ? contact.messages[0] 
            : null;
          
          // Format last message preview
          let lastMessagePreview: string | undefined = 'No messages yet';
          
          if (lastMessageObj) {
            // Handle text messages
            if (lastMessageObj.content && lastMessageObj.content.trim()) {
              // Truncate long messages
              const content = lastMessageObj.content.trim();
              lastMessagePreview = content.length > 50 
                ? content.substring(0, 50) + '...'
                : content;
            } 
            // Handle media messages
            else if (lastMessageObj.messageType && lastMessageObj.messageType !== 'TEXT') {
              const typeLabels: Record<string, string> = {
                'IMAGE': '📷 Image',
                'VIDEO': '🎥 Video',
                'AUDIO': '🎵 Audio',
                'DOCUMENT': '📄 Document',
                'LOCATION': '📍 Location',
                'CONTACT': '👤 Contact',
              };
              lastMessagePreview = typeLabels[lastMessageObj.messageType] || '📎 Media';
            }
            // If message has no content and no type, show direction indicator
            else {
              lastMessagePreview = lastMessageObj.direction === 'INCOMING' 
                ? '📥 Message received' 
                : '📤 Message sent';
            }
          }

          return {
            id: contact.id || `contact-${contact.phoneNumber}`,
            contact: {
              id: contact.id,
              phoneNumber: contact.phoneNumber || '',
              name: contact.name,
            },
            unreadCount: contact._count?.messages || 0,
            lastMessage: lastMessagePreview,
            lastMessageAt: lastMessageObj?.createdAt || contact.lastMessageAt,
          };
        })
        // Sort by lastMessageAt descending (most recent first)
        .sort((a, b) => {
          const dateA = a.lastMessageAt ? new Date(a.lastMessageAt).getTime() : 0;
          const dateB = b.lastMessageAt ? new Date(b.lastMessageAt).getTime() : 0;
          return dateB - dateA;
        });
      
      setConversations(transformedConversations);
    } catch (err) {
      console.error('Failed to load conversations:', err);
      setConversations([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timeout = setTimeout(loadConversations, 300);
    return () => clearTimeout(timeout);
  }, [search]);

  return (
    <div className="conversation-list-container">
      {/* Search header */}
      <div className="list-header">
        <input
          type="text"
          className="search-input"
          placeholder="Search contacts..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {/* Conversations */}
      <div className="conversations-scroll">
        {loading && <div className="loading-state">Loading...</div>}

        {!loading && conversations.length === 0 && (
          <div className="empty-state">No conversations yet</div>
        )}

        {conversations
          .filter((conv) => conv && conv.contact && conv.contact.id) // Additional safety check
          .map((conv) => (
            <div
              key={conv.id}
              className={`conversation-item ${selectedContactId === conv.contact?.id ? 'selected' : ''}`}
              onClick={() => conv.contact?.id && onSelectConversation(conv.contact.id, conv)}
            >
              <div className="conv-avatar">
                {((conv.contact?.name?.charAt(0) || conv.contact?.phoneNumber?.charAt(0)) || '?').toUpperCase()}
              </div>

              <div className="conv-content">
                <div className="conv-header">
                  <span className="conv-name">{conv.contact?.name || conv.contact?.phoneNumber || 'Unknown'}</span>
                  {conv.unreadCount > 0 && (
                    <span className="unread-badge">{conv.unreadCount}</span>
                  )}
                </div>
                <p className="conv-preview">{conv.lastMessage || 'No messages yet'}</p>
              </div>
            </div>
          ))}
      </div>
    </div>
  );
};

export default ConversationList;
