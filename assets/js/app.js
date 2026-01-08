/**
 * WhatsApp Mailbox JavaScript
 */

let currentContactId = null;
let contacts = [];
let messages = [];
let messagePollingInterval = null;

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    loadContacts();
    
    // Search contacts
    document.getElementById('searchContacts').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        filterContacts(query);
    });
    
    // Message form submission
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }
    
    // Poll for new messages every 5 seconds
    setInterval(loadContacts, 5000);
});

/**
 * Load all contacts
 */
async function loadContacts() {
    try {
        const response = await fetch('api.php/contacts');
        
        if (!response.ok) {
            throw new Error('Failed to load contacts');
        }
        
        contacts = await response.json();
        renderContacts(contacts);
        
        // Reload messages for current contact
        if (currentContactId) {
            loadMessages(currentContactId);
        }
        
    } catch (error) {
        console.error('Error loading contacts:', error);
    }
}

/**
 * Render contacts list
 */
function renderContacts(contactsList) {
    const container = document.getElementById('contactsList');
    
    if (contactsList.length === 0) {
        container.innerHTML = '<div class="loading">No contacts yet</div>';
        return;
    }
    
    container.innerHTML = contactsList.map(contact => {
        const initials = getInitials(contact.name);
        const lastMessage = contact.last_message || 'No messages yet';
        const time = contact.last_message_time ? formatTime(contact.last_message_time) : '';
        const unreadBadge = contact.unread_count > 0 
            ? `<span class="unread-badge">${contact.unread_count}</span>` 
            : '';
        const activeClass = currentContactId === contact.id ? 'active' : '';
        
        return `
            <div class="contact-item ${activeClass}" onclick="selectContact(${contact.id}, '${escapeHtml(contact.name)}', '${contact.phone_number}')">
                <div class="contact-avatar">${initials}</div>
                <div class="contact-info">
                    <div class="contact-name">${escapeHtml(contact.name)}</div>
                    <div class="contact-last-message">${escapeHtml(lastMessage)}</div>
                </div>
                <div class="contact-meta">
                    <div class="contact-time">${time}</div>
                    ${unreadBadge}
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Filter contacts by search query
 */
function filterContacts(query) {
    if (!query) {
        renderContacts(contacts);
        return;
    }
    
    const filtered = contacts.filter(contact => 
        contact.name.toLowerCase().includes(query) ||
        contact.phone_number.includes(query)
    );
    
    renderContacts(filtered);
}

/**
 * Select a contact
 */
async function selectContact(contactId, name, phoneNumber) {
    currentContactId = contactId;
    
    // Update UI
    const chatHeader = document.getElementById('chatHeader');
    chatHeader.innerHTML = `
        <div class="contact-avatar">${getInitials(name)}</div>
        <div class="chat-contact-info">
            <h3>${escapeHtml(name)}</h3>
            <div class="chat-contact-phone">${phoneNumber}</div>
        </div>
    `;
    
    // Show message input
    document.getElementById('messageInputContainer').style.display = 'block';
    
    // Load messages
    await loadMessages(contactId);
    
    // Mark as read
    await markAsRead(contactId);
    
    // Reload contacts to update unread count
    loadContacts();
}

/**
 * Load messages for a contact
 */
async function loadMessages(contactId) {
    try {
        const response = await fetch(`api.php/messages?contact_id=${contactId}`);
        
        if (!response.ok) {
            throw new Error('Failed to load messages');
        }
        
        messages = await response.json();
        renderMessages(messages);
        
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

/**
 * Render messages
 */
function renderMessages(messagesList) {
    const container = document.getElementById('messagesContainer');
    
    if (messagesList.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No messages yet. Start a conversation!</p></div>';
        return;
    }
    
    container.innerHTML = messagesList.map(message => {
        const direction = message.direction;
        const time = formatTime(message.timestamp);
        const status = direction === 'outgoing' ? getStatusIcon(message.status) : '';
        
        return `
            <div class="message ${direction}">
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(message.message_body)}</div>
                    <div class="message-time">
                        ${time}
                        ${status}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
}

/**
 * Send a message
 */
async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !currentContactId) {
        return;
    }
    
    // Get contact phone number
    const contact = contacts.find(c => c.id === currentContactId);
    if (!contact) {
        alert('Contact not found');
        return;
    }
    
    // Disable input while sending
    input.disabled = true;
    
    try {
        const response = await fetch('api.php/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                to: contact.phone_number,
                message: message,
                contact_id: currentContactId
            })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            // Clear input
            input.value = '';
            
            // Reload messages
            await loadMessages(currentContactId);
            
            // Reload contacts
            loadContacts();
        } else {
            alert('Failed to send message: ' + (result.error || 'Unknown error'));
        }
        
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Failed to send message');
    } finally {
        input.disabled = false;
        input.focus();
    }
}

/**
 * Mark messages as read
 */
async function markAsRead(contactId) {
    try {
        await fetch('api.php/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                contact_id: contactId
            })
        });
    } catch (error) {
        console.error('Error marking as read:', error);
    }
}

/**
 * Get initials from name
 */
function getInitials(name) {
    if (!name) return '?';
    
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

/**
 * Format timestamp
 */
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    // Less than 24 hours - show time
    if (diff < 86400000) {
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    // Less than 7 days - show day name
    if (diff < 604800000) {
        return date.toLocaleDateString('en-US', { weekday: 'short' });
    }
    
    // Older - show date
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/**
 * Get status icon
 */
function getStatusIcon(status) {
    switch (status) {
        case 'sent':
            return '<span class="message-status">✓</span>';
        case 'delivered':
            return '<span class="message-status">✓✓</span>';
        case 'read':
            return '<span class="message-status" style="color: #4fc3f7;">✓✓</span>';
        case 'failed':
            return '<span class="message-status" style="color: #e74c3c;">✗</span>';
        default:
            return '';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
