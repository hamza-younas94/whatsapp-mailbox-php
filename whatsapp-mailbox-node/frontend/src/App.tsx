import React, { useState, useEffect } from 'react';
import { contactAPI } from '@/api/queries';
import SessionStatus from '@/components/SessionStatus';
import ConversationList from '@/components/ConversationList';
import ChatPane from '@/components/ChatPane';
import '@/styles/app-layout.css';

interface Conversation {
  contactId: string;
  contactName: string;
  lastMessage?: string;
  unreadCount: number;
  avatar?: string;
}

const App: React.FC = () => {
  const [selectedContactId, setSelectedContactId] = useState<string | undefined>();
  const [selectedContact, setSelectedContact] = useState<Conversation | undefined>();
  const [isMobile, setIsMobile] = useState(false);
  const [showList, setShowList] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [isCheckingAuth, setIsCheckingAuth] = useState(true);

  // Check authentication on mount
  useEffect(() => {
    const token = localStorage.getItem('authToken');
    const urlToken = new URLSearchParams(window.location.search).get('token');
    
    if (urlToken) {
      // Token provided in URL query param (from login page redirect)
      localStorage.setItem('authToken', urlToken);
      setIsAuthenticated(true);
      // Clean URL by removing token param
      window.history.replaceState({}, document.title, window.location.pathname);
    } else if (token) {
      // Token already in localStorage
      setIsAuthenticated(true);
    } else {
      // No token found - redirect to login
      setIsAuthenticated(false);
      // Redirect to login page
      const loginUrl = '/login.html';
      console.log('No auth token found, redirecting to:', loginUrl);
      window.location.href = loginUrl;
    }
    
    setIsCheckingAuth(false);
  }, []);

  // Check if mobile
  useEffect(() => {
    const handleResize = () => {
      const mobile = window.innerWidth < 768;
      setIsMobile(mobile);
      setShowList(mobile ? !selectedContactId : true);
    };

    window.addEventListener('resize', handleResize);
    handleResize();

    return () => window.removeEventListener('resize', handleResize);
  }, [selectedContactId]);

  const handleSelectConversation = (contactId: string, conversation: Conversation) => {
    setSelectedContactId(contactId);
    setSelectedContact(conversation);
    
    // On mobile, hide list when chat is selected
    if (isMobile) {
      setShowList(false);
    }
  };

  const handleBackToList = () => {
    setShowList(true);
  };

  return (
    <div className="app-container">
      <SessionStatus />

      <div className="mailbox-main">
        {/* Conversation List - visible on desktop or when showList is true on mobile */}
        {(showList || !isMobile) && (
          <div className={`list-panel ${!showList && isMobile ? 'hidden' : ''}`}>
            <ConversationList
              onSelectConversation={handleSelectConversation}
              selectedContactId={selectedContactId}
            />
          </div>
        )}

        {/* Chat Pane - visible on desktop or when not showList on mobile */}
        {(!showList || !isMobile) && (
          <div className={`chat-panel ${showList && isMobile ? 'hidden' : ''}`}>
            {selectedContactId && selectedContact && (
              <div className="chat-header-mobile">
                {isMobile && (
                  <button className="back-button" onClick={handleBackToList}>
                    ← Back
                  </button>
                )}
              </div>
            )}
            <ChatPane
              contactId={selectedContactId}
              contactName={selectedContact?.contactName}
            />
          </div>
        )}

        {/* Empty State on Desktop */}
        {!selectedContactId && !isMobile && (
          <div className="empty-chat-desktop">
            <div className="empty-content">
              <div className="empty-icon">💬</div>
              <h2>WhatsApp Mailbox</h2>
              <p>Select a conversation to start chatting</p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default App;
