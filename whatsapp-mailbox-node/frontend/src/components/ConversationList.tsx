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
      const data = await contactAPI.searchContacts(search || undefined, 100, 0);
      setConversations(data.data || []);
    } catch (err) {
      console.error('Failed to load conversations:', err);
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

        {conversations.map((conv) => (
          <div
            key={conv.id}
            className={`conversation-item ${selectedContactId === conv.contact.id ? 'selected' : ''}`}
            onClick={() => onSelectConversation(conv.contact.id, conv)}
          >
            <div className="conv-avatar">
              {(conv.contact.name?.charAt(0) || conv.contact.phoneNumber.charAt(0)).toUpperCase()}
            </div>

            <div className="conv-content">
              <div className="conv-header">
                <span className="conv-name">{conv.contact.name || conv.contact.phoneNumber}</span>
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
