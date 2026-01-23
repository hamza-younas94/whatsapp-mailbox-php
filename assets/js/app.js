/**
 * WhatsApp Mailbox JavaScript
 */

let currentContactId = null;
let contacts = [];
let messages = [];
let messagePollingInterval = null;
let lastMessageId = null;
let newMessageIndicator = null;
const NEW_MESSAGE_SCROLL_THRESHOLD = 120;

// Polling debounce flags
let contactsLoadingInProgress = false;
let lastContactsLoadTime = 0;
const CONTACTS_LOAD_DEBOUNCE = 8000; // 8 seconds minimum between loads

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast toast-${type} show`;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

/**
 * Format date/time in Pakistan timezone
 */
function formatPakistanTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-PK', { 
        timeZone: 'Asia/Karachi',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    loadContacts();
    createNewMessageIndicator();
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.addEventListener('scroll', handleMessageScroll);
    }
    
    // Request notification permission
    requestNotificationPermission();
    
    // Check for contact parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const contactId = urlParams.get('contact');
    if (contactId) {
        // Wait a bit for contacts to load, then select the contact
        setTimeout(() => {
            const contact = contacts.find(c => c.id == contactId);
            if (contact) {
                selectContact(contact.id, contact.name, contact.phone_number);
            }
        }, 500);
    }
    
    // Search contacts
    document.getElementById('searchContacts').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        filterContacts(query);
    });
    
    // Load CRM advanced features - Always load to ensure tabs are available
    if (typeof window.openCrmModalAdvanced === 'undefined') {
        // Load crm-advanced.js if not already loaded
        const script = document.createElement('script');
        script.src = 'assets/js/crm-advanced.js?v=' + Date.now();
        script.onload = function() {
            console.log('CRM Advanced modal loaded with tabs');
        };
        document.head.appendChild(script);
    }
    
    // Message form submission
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (validateMessageInput()) {
                sendMessage();
            }
        });
        
        // Real-time validation
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                validateMessageInputRealTime();
                updateCharacterCounter();
            });
            
            messageInput.addEventListener('blur', function() {
                validateMessageInputRealTime();
            });
        }
    }
    
    // Poll for new messages every 8 seconds with debounce
    setInterval(() => {
        const now = Date.now();
        
        // Only load contacts every 8+ seconds, not every interval
        if (now - lastContactsLoadTime >= CONTACTS_LOAD_DEBOUNCE && !contactsLoadingInProgress) {
            contactsLoadingInProgress = true;
            lastContactsLoadTime = now;
            
            loadContacts().finally(() => {
                contactsLoadingInProgress = false;
            });
        }
        
        checkForNewMessages();
        pollNewMessages();
    }, 8000);
    
    // Load message limit on page load
    updateMessageLimitDisplay();
});

/**
 * Update message limit display
 */
async function updateMessageLimitDisplay() {
    try {
        const response = await fetch('api.php/message-limit');
        const data = await response.json();
        
        const limitBadge = document.getElementById('messageLimitBadge');
        if (limitBadge) {
            limitBadge.innerHTML = `
                <span class="limit-text">${data.remaining} / ${data.limit} messages</span>
                ${data.remaining <= 10 ? '<span class="limit-warning">⚠️</span>' : ''}
            `;
            
            if (data.remaining === 0) {
                limitBadge.classList.add('limit-exceeded');
                document.getElementById('messageInput').disabled = true;
                document.getElementById('messageInput').placeholder = '❌ Message limit reached - Upgrade to continue';
            } else if (data.remaining <= 10) {
                limitBadge.classList.add('limit-warning');
            }
        }
    } catch (error) {
        console.error('Error loading message limit:', error);
    }
}

/**
 * Load all contacts (with debounce)
 */
async function loadContacts() {
    try {
        const response = await fetch('api.php/contacts');
        
        if (!response.ok) {
            throw new Error('Failed to load contacts');
        }
        
        const payload = await response.json();
        // API returns { data: [...], pagination: {...} }
        contacts = Array.isArray(payload) ? payload : (payload.data || []);
        renderContacts(Array.isArray(contacts) ? contacts : []);
        
        // Don't reload messages - that causes the loop
        
    } catch (error) {
        console.error('Error loading contacts:', error);
    }
}

/**
 * Render contacts list
 */
function renderContacts(contactsList) {
    const container = document.getElementById('contactsList');
    // Defensive: ensure we have an array before mapping
    if (!Array.isArray(contactsList)) {
        contactsList = [];
    }
    
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
        
        // Conversation status indicators
        const starredIcon = contact.is_starred ? `<span title="Starred" style="color: #fbbf24; font-size: 14px;">⭐</span>` : '';
        const archivedIcon = contact.is_archived ? `<span title="Archived" style="color: #9ca3af; font-size: 14px;">📦</span>` : '';
        const statusIndicators = (starredIcon || archivedIcon) ? `<div style="display: flex; gap: 4px;">${starredIcon}${archivedIcon}</div>` : '';
        
        // CRM features
        const stageBadge = contact.stage ? `<span class="stage-badge stage-${contact.stage}">${contact.stage}</span>` : '';
        const leadScore = contact.lead_score !== null ? `<span class="lead-score" title="Lead Score">${contact.lead_score}</span>` : '';
        const companyInfo = contact.company_name ? `<div class="contact-company">${escapeHtml(contact.company_name)}</div>` : '';
        const tagBadges = Array.isArray(contact.tags) && contact.tags.length > 0 
            ? `<div class="contact-tags">${renderTagBadges(contact.tags)}</div>`
            : '';
        
        return `
            <div class="contact-item ${activeClass}" onclick="selectContact(${contact.id}, '${escapeHtml(contact.name)}', '${contact.phone_number}')">
                <div class="contact-avatar">${initials}</div>
                <div class="contact-info">
                    <div class="contact-name-row">
                        <span class="contact-name">${escapeHtml(contact.name)}</span>
                        ${stageBadge}
                    </div>
                    ${companyInfo}
                    ${statusIndicators}
                    ${tagBadges}
                    <div class="contact-last-message">${escapeHtml(lastMessage)}</div>
                </div>
                <div class="contact-meta">
                    ${leadScore}
                    <div class="contact-time">${time}</div>
                    ${unreadBadge}
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Filter conversations by type (All, Unread, Starred, Archived)
 */
let currentConversationFilter = 'all';

function filterConversations(filter) {
    currentConversationFilter = filter;
    
    // Update filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === filter) {
            btn.classList.add('active');
        }
    });
    
    // Filter contacts
    const query = document.getElementById('searchContacts')?.value || '';
    filterContacts(query);
}

/**
 * Filter contacts by search query and conversation filter
 */
function filterContacts(query) {
    let filtered = contacts;
    
    // Apply conversation filter first
    if (currentConversationFilter === 'unread') {
        filtered = filtered.filter(c => c.unread_count > 0);
    } else if (currentConversationFilter === 'starred') {
        filtered = filtered.filter(c => c.is_starred === true || c.is_starred === 1);
    } else if (currentConversationFilter === 'archived') {
        filtered = filtered.filter(c => c.is_archived === true || c.is_archived === 1);
    }
    
    // Then apply search query
    if (query) {
        filtered = filtered.filter(contact => 
            contact.name.toLowerCase().includes(query) ||
            contact.phone_number.includes(query) ||
            (contact.company_name && contact.company_name.toLowerCase().includes(query))
        );
    }
    
    renderContacts(filtered);
}

/**
 * Select a contact
 */
async function selectContact(contactId, name, phoneNumber) {
    currentContactId = contactId;
    messages = [];
    lastMessageId = null;
    hideNewMessageIndicator();
    
    // Get full contact data
    const contact = contacts.find(c => c.id === contactId);
    
    // Update UI
    const chatHeader = document.getElementById('chatHeader');
    const crmInfo = contact ? `
        <div class="chat-crm-info">
            ${contact.company_name ? `<span class="crm-company">${escapeHtml(contact.company_name)}</span>` : ''}
            ${contact.stage ? `<span class="stage-badge stage-${contact.stage}">${contact.stage}</span>` : ''}
            ${contact.lead_score !== null ? `<span class="lead-score-badge">${contact.lead_score}/100</span>` : ''}
            ${Array.isArray(contact.tags) && contact.tags.length > 0 ? `<span class="chat-tags">${renderTagBadges(contact.tags)}</span>` : ''}
        </div>
    ` : '';
    
    chatHeader.innerHTML = `
        <div class="contact-avatar">${getInitials(name)}</div>
        <div class="chat-contact-info">
            <h3>${escapeHtml(name)}</h3>
            <div class="chat-contact-phone">${phoneNumber}</div>
            ${crmInfo}
        </div>
        <div class="crm-actions">
            <button onclick="openTemplateModal(${contactId}, '${phoneNumber}')" class="btn-crm" title="Send Template Message">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    <path d="M16 13H8v-2h8v2zm0 4H8v-2h8v2z"/>
                </svg>
            </button>
            <button onclick="openCrmModal(${contactId})" class="btn-crm" title="CRM Actions">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/>
                    <path d="M19 10h-2v2h-2v-2h-2V8h2V6h2v2h2v2z"/>
                </svg>
            </button>
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
        renderMessages(messages, { replace: true });
        lastMessageId = messages.length > 0 ? messages[messages.length - 1].id : null;
        hideNewMessageIndicator();
        
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

/**
 * Poll for new messages and status updates without reloading the thread
 */
async function pollNewMessages() {
    if (!currentContactId) {
        return;
    }
    
    try {
        // Fetch recent messages (last 50) to get status updates for existing messages
        // If we have lastMessageId, use after_id for efficiency. Otherwise fetch recent 50 for status updates
        const url = lastMessageId 
            ? `api.php/messages?contact_id=${currentContactId}&after_id=${lastMessageId}`
            : `api.php/messages?contact_id=${currentContactId}&limit=50`;
        
        const response = await fetch(url);
        if (!response.ok) {
            return;
        }
        
        const allMessages = await response.json();
        if (!Array.isArray(allMessages) || allMessages.length === 0) {
            // If we were polling for status updates and got no new messages, fetch recent to check statuses
            if (lastMessageId && messages.length > 0) {
                const statusCheckResponse = await fetch(`api.php/messages?contact_id=${currentContactId}&limit=20`);
                if (statusCheckResponse.ok) {
                    const recentMessages = await statusCheckResponse.json();
                    updateMessageStatuses(recentMessages);
                }
            }
            return;
        }
        
        // Messages are already in chronological order from API (oldest to newest)
        const recentMessages = allMessages;
        
        // If using after_id, these are all new messages
        if (lastMessageId) {
            messages = messages.concat(recentMessages);
            if (recentMessages.length > 0) {
                lastMessageId = recentMessages[recentMessages.length - 1].id;
                renderMessages(recentMessages, { replace: false });
            }
        } else {
            // Fetching recent messages - check for new ones and status updates
            const hasNewMessages = messages.length === 0 || recentMessages.some(rm => {
                return !messages.find(m => m.id === rm.id);
            });
            
            // Update statuses of existing messages
            updateMessageStatuses(recentMessages);
            
            // Add new messages
            if (hasNewMessages) {
                const existingIds = new Set(messages.map(m => m.id));
                const newMessages = recentMessages.filter(m => !existingIds.has(m.id));
                
                if (newMessages.length > 0) {
                    messages = messages.concat(newMessages);
                    lastMessageId = newMessages[newMessages.length - 1].id;
                    renderMessages(newMessages, { replace: false });
                }
            }
            
            // Update lastMessageId if we have messages
            if (recentMessages.length > 0) {
                lastMessageId = recentMessages[recentMessages.length - 1].id;
            }
        }
    } catch (error) {
        console.error('Error polling new messages:', error);
    }
}

/**
 * Render messages
 */
function renderMessages(messagesList, options = {}) {
    const { replace = true } = options;
    const container = document.getElementById('messagesContainer');
    if (!container) return;

    if (replace && messagesList.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No messages yet. Start a conversation!</p></div>';
        return;
    }

    const wasNearBottom = isNearBottom(container);

    const html = messagesList.map(message => {
        const direction = message.direction;
        const time = formatTime(message.timestamp);
        const status = direction === 'outgoing' ? getStatusIcon(message.status) : '';
        
        // Render media messages
        let content = '';
        
        // For images - support both uploaded files and WhatsApp media URLs
        if (message.message_type === 'image' && (message.media_filename || message.media_url)) {
            const imageUrl = message.media_filename ? `/uploads/${escapeHtml(message.media_filename)}` : message.media_url;
            content = `
                <div class="message-media">
                    <img src="${imageUrl}" alt="Image" onerror="this.style.display='none';this.nextElementSibling.style.display='block';" onclick="openImageModal('${imageUrl}')" style="max-width: 300px; border-radius: 8px; cursor: pointer;">
                    <div style="display:none;padding:10px;background:#f0f0f0;border-radius:8px;color:#666;">📷 Image unavailable</div>
                    ${message.message_body && message.message_body !== '[IMAGE]' ? `<div class="message-text" style="margin-top: 8px;">${escapeHtml(message.message_body)}</div>` : ''}
                </div>
            `;
        } 
        // For videos
        else if (message.message_type === 'video' && (message.media_filename || message.media_url)) {
            const videoUrl = message.media_filename ? `/uploads/${escapeHtml(message.media_filename)}` : message.media_url;
            content = `
                <div class="message-media">
                    <video controls style="max-width: 300px; border-radius: 8px;">
                        <source src="${videoUrl}" type="video/mp4">
                    </video>
                    ${message.message_body && message.message_body !== '[VIDEO]' ? `<div class="message-text" style="margin-top: 8px;">${escapeHtml(message.message_body)}</div>` : ''}
                </div>
            `;
        } 
        // For audio
        else if (message.message_type === 'audio' && (message.media_filename || message.media_url)) {
            const audioUrl = message.media_filename ? `/uploads/${escapeHtml(message.media_filename)}` : message.media_url;
            content = `
                <div class="message-media">
                    <audio controls style="max-width: 300px;">
                        <source src="${audioUrl}" type="audio/mpeg">
                    </audio>
                    ${message.message_body && message.message_body !== '[AUDIO]' ? `<div class="message-text" style="margin-top: 8px;">${escapeHtml(message.message_body)}</div>` : ''}
                </div>
            `;
        } 
        // For documents
        else if (message.message_type === 'document' && (message.media_filename || message.media_url)) {
            const docUrl = message.media_filename ? `/uploads/${escapeHtml(message.media_filename)}` : message.media_url;
            const displayName = message.media_filename || message.media_caption || 'Document';
            content = `
                <div class="message-media">
                    <a href="${docUrl}" target="_blank" class="document-link">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        <span>${escapeHtml(displayName)}</span>
                    </a>
                    ${message.message_body && message.message_body !== '[DOCUMENT]' ? `<div class="message-text" style="margin-top: 8px;">${escapeHtml(message.message_body)}</div>` : ''}
                </div>
            `;
        } 
        // For unsupported/system messages - show warning icon with summary
        else if (message.message_type === 'unsupported' || message.message_type === 'system') {
            const isError = message.message_body?.includes('not supported') || message.message_body?.includes('error');
            const icon = isError ? '⚠️' : 'ℹ️';
            content = `
                <div class="message-text" style="padding: 8px 12px; background: ${isError ? '#fff5f5' : '#f0f9ff'}; border-left: 3px solid ${isError ? '#ef4444' : '#3b82f6'}; border-radius: 4px; font-size: 13px;">
                    ${icon} ${escapeHtml(message.message_body)}
                </div>
            `;
        }
        // For stickers
        else if (message.message_type === 'sticker' && (message.media_filename || message.media_url)) {
            const stickerUrl = message.media_filename ? `/uploads/${escapeHtml(message.media_filename)}` : message.media_url;
            content = `
                <div class="message-media">
                    <img src="${stickerUrl}" alt="Sticker" style="max-width: 200px; height: auto; border-radius: 8px;" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                    <div style="display:none;padding:10px;background:#f0f0f0;border-radius:8px;color:#666;text-align:center;">😊 Sticker unavailable</div>
                </div>
            `;
        }
        // For reactions - show emoji reply
        else if (message.message_type === 'reaction') {
            const emoji = message.message_body?.match(/Reaction: (.)/)?.[1] || '❤️';
            content = `
                <div class="message-text" style="display: inline-block; padding: 4px 8px; background: #fff9e6; border-radius: 20px; font-size: 20px;">
                    ${emoji}
                </div>
            `;
        }
        // For location messages
        else if (message.message_type === 'location') {
            const locMatch = message.message_body?.match(/Location: ([-\d.]+), ([-\d.]+)/);
            if (locMatch) {
                const lat = locMatch[1];
                const lng = locMatch[2];
                const name = message.message_body?.match(/\(([^)]+)\)/)?.[1] || 'Location';
                const mapUrl = `https://www.google.com/maps/search/${lat},${lng}`;
                content = `
                    <div class="message-media">
                        <a href="${mapUrl}" target="_blank" style="display: block; text-decoration: none;">
                            <img src="https://maps.googleapis.com/maps/api/staticmap?center=${lat},${lng}&zoom=15&size=300x200&markers=${lat},${lng}&key=AIzaSyDummyKey" alt="Map" style="max-width: 300px; border-radius: 8px;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22200%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22300%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22%3E📍 ${escapeHtml(name)}<br/>${lat}, ${lng}%3C/text%3E%3C/svg%3E';">
                            <div style="padding: 8px; background: #f9fafb; border-radius: 0 0 8px 8px;">
                                <div style="font-weight: 600; margin-bottom: 4px;">📍 ${escapeHtml(name)}</div>
                                <div style="font-size: 12px; color: #6b7280;">${lat}, ${lng}</div>
                                <div style="font-size: 11px; color: #3b82f6; margin-top: 6px;">Open in Maps →</div>
                            </div>
                        </a>
                    </div>
                `;
            } else {
                content = `<div class="message-text">📍 ${escapeHtml(message.message_body)}</div>`;
            }
        }
        // For contact cards
        else if (message.message_type === 'contacts') {
            const contactsMatch = message.message_body?.match(/Contact(?:s)?: (.+)/);
            const contactsList = contactsMatch ? contactsMatch[1].split(', ').map(c => {
                const parts = c.match(/([^(]+)\s*\(([^)]+)\)?/);
                const name = parts?.[1]?.trim() || c;
                const phone = parts?.[2]?.trim() || '';
                return `<div style="padding: 8px 12px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#3b82f6"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm8 0v1.54c0 .87-.5 1.64-1.23 2.05.5-1.18.5-2.49 0-3.59.73.41 1.23 1.18 1.23 2.05zM15.5 19v1.5H.5V19c0-2.64 5.05-4 7.5-4s7.5 1.36 7.5 4z"/></svg>
                    <div>
                        <div style="font-weight: 600; font-size: 14px;">${escapeHtml(name)}</div>
                        ${phone ? `<div style="font-size: 12px; color: #6b7280;">📞 ${escapeHtml(phone)}</div>` : ''}
                    </div>
                </div>`;
            }).join('') : '';
            
            content = `
                <div class="message-media" style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                    <div style="padding: 8px 12px; background: #f9fafb; font-weight: 600; border-bottom: 1px solid #e5e7eb;">
                        👤 Contact${message.message_body?.includes('Contacts:') ? 's' : ''}
                    </div>
                    ${contactsList}
                </div>
            `;
        }
        // For interactive messages (buttons, lists)
        else if (message.message_type === 'interactive') {
            const typeMatch = message.message_body?.match(/Interactive message \((\w+)\)/);
            const msgType = typeMatch?.[1] || 'interactive';
            const selectedMatch = message.message_body?.match(/: (.+)/);
            const selected = selectedMatch?.[1] || message.message_body;
            
            content = `
                <div class="message-text" style="padding: 10px 12px; background: #f3f4f6; border-left: 3px solid #6366f1; border-radius: 4px;">
                    <div style="font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <span>🎯</span> ${msgType.charAt(0).toUpperCase() + msgType.slice(1)}
                    </div>
                    <div style="font-size: 14px; color: #374151;">${escapeHtml(selected)}</div>
                </div>
            `;
        }
        // For button messages
        else if (message.message_type === 'button') {
            const btnMatch = message.message_body?.match(/Button message: (.+)/);
            const btnText = btnMatch?.[1] || message.message_body;
            
            content = `
                <div class="message-text" style="padding: 8px 12px; background: #f0f9ff; border-left: 3px solid #0ea5e9; border-radius: 4px;">
                    <span style="display: inline-block; background: #0ea5e9; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 500;">
                        🔘 ${escapeHtml(btnText)}
                    </span>
                </div>
            `;
        }
        // For list messages
        else if (message.message_type === 'list') {
            const listMatch = message.message_body?.match(/List message: ([^-]+)(?: - (.+))?/);
            const title = listMatch?.[1] || 'List';
            const desc = listMatch?.[2] || '';
            
            content = `
                <div class="message-text" style="padding: 10px 12px; background: #f5f3ff; border-left: 3px solid #a78bfa; border-radius: 4px;">
                    <div style="font-weight: 600; margin-bottom: 4px;">📋 ${escapeHtml(title)}</div>
                    ${desc ? `<div style="font-size: 13px; color: #6b7280;">${escapeHtml(desc)}</div>` : ''}
                </div>
            `;
        }
        // For template messages
        else if (message.message_type === 'template') {
            const templateMatch = message.message_body?.match(/Template: ([^(]+)(?:\(([^)]+)\))?/);
            const templateName = templateMatch?.[1]?.trim() || 'Template';
            const language = templateMatch?.[2] || '';
            
            content = `
                <div class="message-text" style="padding: 10px 12px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
                    <div style="font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                        <span>📋</span> Template Message
                    </div>
                    <div style="font-size: 14px;">${escapeHtml(templateName)}</div>
                    ${language ? `<div style="font-size: 11px; color: #6b7280; margin-top: 4px;">🌐 ${escapeHtml(language)}</div>` : ''}
                </div>
            `;
        }
        // For order messages
        else if (message.message_type === 'order') {
            const orderMatch = message.message_body?.match(/Order: ([^\s]+)(?: \((.+?)\))?/);
            const orderId = orderMatch?.[1] || 'Order';
            const catalog = orderMatch?.[2] || '';
            
            content = `
                <div class="message-text" style="padding: 10px 12px; background: #dcfce7; border-left: 3px solid #22c55e; border-radius: 4px;">
                    <div style="font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                        <span>🛒</span> Order
                    </div>
                    <div style="font-size: 14px; font-family: monospace;">${escapeHtml(orderId)}</div>
                    ${catalog ? `<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">From Catalog</div>` : ''}
                </div>
            `;
        }
        // For ephemeral/view-once messages
        else if (message.message_type === 'ephemeral') {
            content = `
                <div class="message-text" style="padding: 10px 12px; background: #fce7f3; border-left: 3px solid #ec4899; border-radius: 4px;">
                    <div style="font-weight: 600; display: flex; align-items: center; gap: 6px;">
                        <span>👁️</span> View Once Message
                    </div>
                </div>
            `;
        }
        // For text messages
        else {
            content = `<div class="message-text">${escapeHtml(message.message_body)}</div>`;
        }
        
        // Get full timestamp for tooltip
        const fullTime = new Date(message.timestamp).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Message actions (star, forward, etc.)
        const messageActions = direction === 'incoming' ? `
            <div class="message-actions">
                <button onclick="starMessage(${message.id}, this)" class="msg-action-btn" title="Star message">
                    <i class="far fa-star"></i>
                </button>
                <button onclick="forwardMessage(${message.id})" class="msg-action-btn" title="Forward">
                    <i class="fas fa-share"></i>
                </button>
            </div>
        ` : '';
        
        return `
            <div class="message ${direction}" data-message-id="${message.id}">
                <div class="message-bubble">
                    ${content}
                    <div class="message-time">
                        <span class="time-text" title="${fullTime}" onclick="showFullTime(this, '${message.timestamp}')">${time}</span>
                        ${status}
                    </div>
                    ${messageActions}
                </div>
            </div>
        `;
    }).join('');

    if (replace) {
        container.innerHTML = html;
    } else if (html) {
        container.insertAdjacentHTML('beforeend', html);
    }

    if (replace || wasNearBottom) {
        scrollToBottom(container);
        hideNewMessageIndicator();
    } else {
        showNewMessageIndicator();
    }
}

/**
 * Send a message
 */
async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    // Check if sending media or text
    if (!selectedMediaFile && !message) {
        return;
    }
    
    if (!currentContactId) {
        return;
    }
    
    // Get contact phone number
    const contact = contacts.find(c => c.id === currentContactId);
    if (!contact) {
        showToast('Contact not found', 'error');
        return;
    }
    
    // Disable input while sending
    input.disabled = true;
    const sendBtn = document.querySelector('.send-btn');
    const originalBtnContent = sendBtn.innerHTML;
    sendBtn.innerHTML = '<div style="width:20px;height:20px;border:2px solid white;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></div>';
    sendBtn.disabled = true;
    
    let response;
    let result;
    
    try {
        if (selectedMediaFile) {
            const formData = new FormData();
            formData.append('media', selectedMediaFile);
            formData.append('media_type', selectedMediaType);
            formData.append('to', contact.phone_number);
            formData.append('contact_id', currentContactId);
            
            const caption = document.getElementById('mediaCaption').value.trim();
            if (caption) {
                formData.append('caption', caption);
            }
            
            response = await fetch('api.php/send-media', {
                method: 'POST',
                body: formData
            });
            
            result = await response.json();
            console.log('📤 Media upload response:', response.status, result);
        } else {
            // Send text message
            response = await fetch('api.php/send', {
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
            
            result = await response.json();
        }
        
        if (response.ok && result.success) {
            // Clear input and validation
            input.value = '';
            clearMediaSelection();
            updateCharacterCounter();
            
            // Show success state
            const form = document.getElementById('messageForm');
            const validationMsg = document.getElementById('validationMessage');
            if (form) {
                form.classList.add('success');
                form.classList.remove('error', 'warning');
            }
            if (validationMsg) {
                validationMsg.classList.remove('show', 'error', 'warning');
                validationMsg.classList.add('show', 'success');
                validationMsg.textContent = selectedMediaFile ? 'Media sent successfully!' : 'Message sent!';
                setTimeout(() => {
                    validationMsg.classList.remove('show');
                    if (form) form.classList.remove('success');
                }, 2000);
            }
            
            // Reload messages
            await loadMessages(currentContactId);
            
            // Update message limit display
            if (result.messages_remaining !== undefined) {
                updateMessageLimitDisplay();
                if (result.messages_remaining <= 10) {
                    showToast(`Warning: Only ${result.messages_remaining} messages remaining!`, 'info');
                }
            }
            
            showToast(selectedMediaFile ? 'Media sent successfully!' : 'Message sent!', 'success');
        } else {
            const errorMsg = result.error || 'Unknown error';
            const validationErrors = result.errors || {};
            
            // Clear success state
            const form = document.getElementById('messageForm');
            if (form) {
                form.classList.remove('success');
            }
            
            // Check if it's message limit
            if (response.status === 429) {
                showValidationError('Message limit reached! Please upgrade to continue.', {});
                showToast('Message limit reached! Please upgrade to continue.', 'error');
                document.getElementById('messageInput').disabled = true;
                updateMessageLimitDisplay();
                return;
            }
            
            // Check if it's validation error
            if (response.status === 422) {
                showValidationError(errorMsg, validationErrors);
                showToast(errorMsg, 'error');
                return;
            }
            
            // Check if it's a 24-hour window issue
            if (response.status === 403 && errorMsg.includes('not messaged you recently')) {
                showValidationError('Cannot send: Contact must message you first (24-hour window). Use a template message instead.', {});
                showToast('Cannot send: Contact must message you first (24-hour window)', 'error');
            } else {
                // For media errors, provide more helpful error messages
                if (selectedMediaFile) {
                    const details = result.errors?.details || {};
                    let detailedError = errorMsg;
                    
                    if (details.error) {
                        detailedError = details.error;
                    } else if (details.response) {
                        try {
                            const errorResponse = typeof details.response === 'string' ? JSON.parse(details.response) : details.response;
                            if (errorResponse.error?.message) {
                                detailedError = errorResponse.error.message;
                            }
                        } catch (e) {
                            // Ignore JSON parse errors
                        }
                    }
                    
                    // Check for common WhatsApp API errors
                    if (detailedError.includes('Media URL') || detailedError.includes('Invalid URL')) {
                        showToast('Media URL is not accessible. Make sure your server is publicly accessible.', 'error');
                    } else if (detailedError.includes('400') || detailedError.includes('Bad Request')) {
                        showToast('Invalid media format or URL. Please try again.', 'error');
                    } else {
                        showToast('Failed to send media: ' + detailedError, 'error');
                    }
                } else {
                    showValidationError(errorMsg, validationErrors);
                    showToast('Failed to send: ' + errorMsg, 'error');
                }
            }
        }
        
    } catch (error) {
        console.error('Error sending message:', error);
        showToast('Failed to send message', 'error');
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalBtnContent;
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
 * Format timestamp - Modern relative time
 */
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    // Just now (less than 1 minute)
    if (seconds < 60) {
        return 'Just now';
    }
    
    // Minutes ago (less than 1 hour)
    if (minutes < 60) {
        return `${minutes}m ago`;
    }
    
    // Hours ago (less than 24 hours)
    if (hours < 24) {
        return `${hours}h ago`;
    }
    
    // Yesterday
    if (days === 1) {
        return 'Yesterday';
    }
    
    // Days ago (less than 7 days)
    if (days < 7) {
        return `${days}d ago`;
    }
    
    // Weeks ago (less than 4 weeks)
    if (days < 28) {
        const weeks = Math.floor(days / 7);
        return `${weeks}w ago`;
    }
    
    // Show date for older messages
    const isThisYear = date.getFullYear() === now.getFullYear();
    if (isThisYear) {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Update message statuses in the UI
 */
function updateMessageStatuses(recentMessages) {
    if (messages.length === 0) return;
    
    recentMessages.forEach(rm => {
        const existingIndex = messages.findIndex(m => m.id === rm.id);
        if (existingIndex !== -1 && messages[existingIndex].status !== rm.status) {
            // Update in memory
            messages[existingIndex].status = rm.status;
            
            // Update the UI element
            const messageEl = document.querySelector(`[data-message-id="${rm.id}"]`);
            if (messageEl) {
                const timeEl = messageEl.querySelector('.message-time');
                if (timeEl) {
                    const timeText = timeEl.querySelector('.time-text');
                    if (timeText) {
                        const statusHtml = getStatusIcon(rm.status);
                        const fullTime = timeText.getAttribute('title') || '';
                        timeEl.innerHTML = timeText.outerHTML + statusHtml;
                        // Restore title if it was there
                        if (fullTime) {
                            const newTimeText = timeEl.querySelector('.time-text');
                            if (newTimeText) {
                                newTimeText.setAttribute('title', fullTime);
                            }
                        }
                    }
                }
            }
        }
    });
}

/**
 * Get status icon - Modern SVG icons
 */
function getStatusIcon(status) {
    switch (status) {
        case 'sent':
            return `<svg class="message-status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>`;
        case 'delivered':
            return `<span class="status-double-check">
                <svg class="message-status-icon delivered" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <svg class="message-status-icon delivered" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>`;
        case 'read':
            return `<span class="status-double-check">
                <svg class="message-status-icon read" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <svg class="message-status-icon read" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>`;
        case 'failed':
            return `<svg class="message-status-icon failed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>`;
        default:
            return '';
    }
}

/**
 * Render tag badges
 */
function renderTagBadges(tags) {
    return tags.map(t => {
        const color = t.color || '#6b7280';
        const name = escapeHtml(t.name || 'Tag');
        return `<span class="tag-badge" style="background-color: ${color};">${name}</span>`;
    }).join(' ');
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Open CRM side panel
 */
// Always use advanced CRM modal with tabs (Overview, Timeline, Tasks, Notes, Deals)
function openCrmModal(contactId) {
    // Prioritize advanced CRM modal with tabs
    if (typeof window.openCrmModalAdvanced === 'function') {
        window.openCrmModalAdvanced(contactId);
        return;
    }
    
    // Wait a moment for script to load if not ready yet
    setTimeout(() => {
        if (typeof window.openCrmModalAdvanced === 'function') {
            window.openCrmModalAdvanced(contactId);
            return;
        }
        console.warn('Advanced CRM modal not available, using fallback');
    }, 200);
    
    // Fallback to basic modal only if advanced fails
    const contact = contacts.find(c => c.id === contactId);
    if (!contact) return;
    
    const modal = document.getElementById('crmModal');
    const content = document.getElementById('crmModalContent');
    const modalTitle = document.getElementById('crmModalTitle');
    
    // Update header title
    modalTitle.textContent = escapeHtml(contact.name);
    
    content.innerHTML = `
        <div class="crm-section">
            <div class="crm-section-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <h3>Stage Management</h3>
            </div>
            <select id="crmStage" class="crm-select">
                    <option value="new" ${contact.stage === 'new' ? 'selected' : ''}>New</option>
                    <option value="contacted" ${contact.stage === 'contacted' ? 'selected' : ''}>Contacted</option>
                    <option value="qualified" ${contact.stage === 'qualified' ? 'selected' : ''}>Qualified</option>
                    <option value="proposal" ${contact.stage === 'proposal' ? 'selected' : ''}>Proposal</option>
                    <option value="negotiation" ${contact.stage === 'negotiation' ? 'selected' : ''}>Negotiation</option>
                    <option value="customer" ${contact.stage === 'customer' ? 'selected' : ''}>Customer</option>
                    <option value="lost" ${contact.stage === 'lost' ? 'selected' : ''}>Lost</option>
                </select>
                <button onclick="updateStage(${contactId})" class="btn-primary">Update Stage</button>
            </div>
            
            <div class="crm-section">
                <div class="crm-section-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
                        <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                        <polyline points="13 2 13 9 20 9"/>
                    </svg>
                    <h3>Lead Score</h3>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span id="currentScore" style="font-size: 28px; font-weight: 700; color: var(--primary);">${contact.lead_score || 0}</span>
                    <span style="color: var(--text-secondary); font-size: 14px;">/100</span>
                </div>
                <div class="score-bar">
                    <div class="score-fill" style="width: ${contact.lead_score || 0}%"></div>
                </div>
            </div>
            
            <div class="crm-section">
                <div class="crm-section-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <h3>Company Information</h3>
                </div>
                <form id="companyInfoForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <input type="text" id="crmCompany" name="company_name" class="crm-input form-control" placeholder="Company Name" value="${contact.company_name || ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <input type="email" id="crmEmail" name="email" class="crm-input form-control" placeholder="Email" value="${contact.email || ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <input type="text" id="crmCity" name="city" class="crm-input form-control" placeholder="City" value="${contact.city || ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <button type="button" onclick="updateCompanyInfo(${contactId})" class="btn-primary">Update Info</button>
                </form>
            </div>
            
            <div class="crm-section">
                <div class="crm-section-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <h3>Add Note</h3>
                </div>
                <form id="addNoteForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <textarea id="crmNote" name="content" class="crm-textarea form-control" placeholder="Type your note here..." rows="3" required></textarea>
                        <div class="invalid-feedback">Please enter a note</div>
                    </div>
                    <div class="mb-3">
                        <select id="crmNoteType" name="type" class="crm-select form-select">
                            <option value="general">General</option>
                            <option value="call">Call</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <button type="button" onclick="addNote(${contactId})" class="btn-primary">Add Note</button>
                </form>
            </div>
            
            <div class="crm-section">
                <div class="crm-section-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                        <path d="M21 3v5h-5"/>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                        <path d="M3 21v-5h5"/>
                    </svg>
                    <h3>Recent Notes</h3>
                </div>
                <div id="notesList" class="notes-list">
                    <div class="loading">Loading notes...</div>
                </div>
            </div>
            
            <div class="crm-section">
                <div class="crm-section-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <h3>Deal History</h3>
                </div>
                <div class="deal-summary" id="dealSummary">
                    <div class="loading">Loading deals...</div>
                </div>
                <div id="dealsList" class="deals-list">
                </div>
                <button onclick="showAddDealForm(${contactId})" class="btn-secondary">+ Add New Deal</button>
            </div>
            
            <div class="crm-section" id="addDealForm" style="display: none;">
                <div class="crm-section-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"/>
                        <circle cx="19" cy="12" r="1"/>
                        <circle cx="5" cy="12" r="1"/>
                    </svg>
                    <h3>Add New Deal</h3>
                </div>
                <form id="addDealFormElement" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <input type="text" id="dealName" name="deal_name" class="crm-input form-control" placeholder="Deal Name (e.g., Website Package)" required>
                        <div class="invalid-feedback">Please enter a deal name</div>
                    </div>
                    <div class="mb-3">
                        <input type="number" id="dealAmount" name="amount" class="crm-input form-control" placeholder="Amount" step="0.01" min="0" required>
                        <div class="invalid-feedback">Please enter a valid amount</div>
                    </div>
                    <div class="mb-3">
                        <select id="dealStatus" name="status" class="crm-select form-select" required>
                            <option value="pending">Pending</option>
                            <option value="won">Won</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <input type="date" id="dealDate" name="deal_date" class="crm-input form-control" required>
                        <div class="invalid-feedback">Please select a date</div>
                    </div>
                    <div class="mb-3">
                        <textarea id="dealNotes" name="notes" class="crm-textarea form-control" placeholder="Deal notes..." rows="2"></textarea>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" onclick="saveDeal(${contactId})" class="btn-primary" style="flex: 1;">Save Deal</button>
                        <button type="button" onclick="hideAddDealForm()" class="btn-secondary" style="flex: 1;">Cancel</button>
                    </div>
                </form>
            </div>
    `;
    
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        loadNotes(contactId);
        loadDeals(contactId);
    }
}

/**
 * Close CRM modal
 */
function closeCrmModal() {
    const modal = document.getElementById('crmModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        // Hide add deal form if open
        if (typeof hideAddDealForm === 'function') {
            hideAddDealForm();
        }
    }
}

// Modals should NOT close when clicking outside - removed backdrop click handler

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const crmModal = document.getElementById('crmModal');
        if (crmModal && crmModal.classList.contains('show')) {
            closeCrmModal();
        }
    }
});


/**
 * Update contact stage
 */
async function updateStage(contactId) {
    const stage = document.getElementById('crmStage').value;
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/crm`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ stage })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Stage updated successfully!', 'success');
            loadContacts();
        } else {
            showToast('Failed to update stage: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating stage:', error);
        showToast('Failed to update stage', 'error');
    }
}

/**
 * Update company info
 */
async function updateCompanyInfo(contactId) {
    const form = document.getElementById('companyInfoForm');
    if (!form) return;
    
    // Validate form
    if (typeof FormValidator !== 'undefined') {
        const validator = new FormValidator('companyInfoForm', {
            email: ['email', 'max:255'],
            company_name: ['max:255'],
            city: ['max:100']
        });
        
        if (!validator.validate()) {
            return;
        }
    } else {
        // Fallback validation
        const email = document.getElementById('crmEmail').value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            const emailField = document.getElementById('crmEmail');
            emailField.classList.add('is-invalid');
            emailField.nextElementSibling.textContent = 'Please enter a valid email address';
            showToast('Please enter a valid email address', 'error');
            return;
        }
    }
    
    const company_name = document.getElementById('crmCompany').value.trim();
    const email = document.getElementById('crmEmail').value.trim();
    const city = document.getElementById('crmCity').value.trim();
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/crm`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ company_name, email, city })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            // Clear validation
            form.classList.remove('was-validated');
            ['crmCompany', 'crmEmail', 'crmCity'].forEach(id => {
                const field = document.getElementById(id);
                if (field) {
                    field.classList.remove('is-invalid', 'is-valid');
                }
            });
            showToast('Company info updated!', 'success');
            loadContacts();
        } else {
            // Show backend validation errors
            if (result.errors && typeof FormValidator !== 'undefined') {
                const validator = new FormValidator('companyInfoForm', {});
                validator.setErrors(result.errors);
            } else {
                showToast('Failed to update: ' + (result.error || 'Unknown error'), 'error');
            }
        }
    } catch (error) {
        console.error('Error updating company info:', error);
        showToast('Failed to update', 'error');
    }
}

/**
 * Update deal info
 */
async function updateDealInfo(contactId) {
    const deal_value = document.getElementById('crmDealValue').value;
    const expected_close_date = document.getElementById('crmExpectedClose').value;
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/crm`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ deal_value, expected_close_date })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Deal info updated!', 'success');
            loadContacts();
            openCrmModal(contactId); // Refresh modal to show updated values
        } else {
            showToast('Failed to update: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating deal info:', error);
        showToast('Failed to update', 'error');
    }
}

/**
 * Add note
 */
async function addNote(contactId) {
    const form = document.getElementById('addNoteForm');
    if (!form) return;
    
    const content = document.getElementById('crmNote').value.trim();
    const type = document.getElementById('crmNoteType').value;
    
    // Validate
    if (typeof FormValidator !== 'undefined') {
        const validator = new FormValidator('addNoteForm', {
            content: ['required', 'min:1', 'max:5000']
        });
        
        if (!validator.validate()) {
            return;
        }
    } else {
        // Fallback validation
        if (!content) {
            const noteField = document.getElementById('crmNote');
            noteField.classList.add('is-invalid');
            noteField.nextElementSibling.textContent = 'Please enter a note';
            showToast('Please enter a note', 'error');
            return;
        }
    }
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/note`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ content, type })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            // Clear form and validation
            document.getElementById('crmNote').value = '';
            form.classList.remove('was-validated');
            const noteField = document.getElementById('crmNote');
            if (noteField) {
                noteField.classList.remove('is-invalid', 'is-valid');
            }
            showToast('Note added successfully!', 'success');
            await loadNotes(contactId);
        } else {
            // Show backend validation errors
            if (result.errors && typeof FormValidator !== 'undefined') {
                const validator = new FormValidator('addNoteForm', {});
                validator.setErrors(result.errors);
            } else {
                showToast('Failed to add note: ' + (result.error || 'Unknown error'), 'error');
            }
        }
    } catch (error) {
        console.error('Error adding note:', error);
        showToast('Failed to add note', 'error');
    }
}

/**
 * Load notes for contact
 */
async function loadNotes(contactId) {
    console.log('Loading notes for contact:', contactId);
    try {
        const response = await fetch(`crm.php/contact/${contactId}/notes`);
        const result = await response.json();
        
        console.log('Notes API response:', result);
        
        const notesList = document.getElementById('notesList');
        
        if (!notesList) {
            console.error('notesList element not found!');
            return;
        }
        
        // Check if API returned notes (with or without success field)
        const notesArray = result.notes || [];
        
        if (notesArray.length > 0) {
            console.log('Rendering', notesArray.length, 'notes');
            notesList.innerHTML = notesArray.map(note => {
                console.log('Note:', note);
                return `
                <div class="note-item note-type-${note.type}">
                    <div class="note-header">
                        <span class="note-type">${note.type.toUpperCase()}</span>
                        <span class="note-date">${formatPakistanTime(note.created_at)}</span>
                    </div>
                    <div class="note-content">${escapeHtml(note.content)}</div>
                    <div class="note-author">by ${note.created_by_name || note.creator?.name || 'Admin'}</div>
                </div>
            `;
            }).join('');
            console.log('Notes rendered successfully');
        } else {
            console.log('No notes found or empty array');
            notesList.innerHTML = '<div class="empty-state"><p>No notes yet</p></div>';
        }
    } catch (error) {
        console.error('Error loading notes:', error);
        const notesList = document.getElementById('notesList');
        if (notesList) {
            notesList.innerHTML = '<div class="empty-state"><p>Failed to load notes</p></div>';
        }
    }
}
/**
 * Load deals for contact
 */
async function loadDeals(contactId) {
    console.log('Loading deals for contact:', contactId);
    try {
        const response = await fetch(`crm.php/contact/${contactId}/deals`);
        const result = await response.json();
        
        console.log('Deals API response:', result);
        
        const dealSummary = document.getElementById('dealSummary');
        const dealsList = document.getElementById('dealsList');
        
        if (!dealsList) {
            console.error('dealsList element not found!');
            return;
        }
        
        const deals = result.deals || [];
        
        // Calculate summary
        const totalDeals = deals.length;
        const wonDeals = deals.filter(d => d.status === 'won');
        const totalRevenue = wonDeals.reduce((sum, d) => sum + parseFloat(d.amount), 0);
        
        // Display summary
        dealSummary.innerHTML = `
            <div class="deal-stats">
                <div class="deal-stat">
                    <span class="deal-stat-value">${totalDeals}</span>
                    <span class="deal-stat-label">Total Deals</span>
                </div>
                <div class="deal-stat">
                    <span class="deal-stat-value">${wonDeals.length}</span>
                    <span class="deal-stat-label">Won</span>
                </div>
                <div class="deal-stat success">
                    <span class="deal-stat-value">PKR ${totalRevenue.toFixed(0)}</span>
                    <span class="deal-stat-label">Revenue</span>
                </div>
            </div>
        `;
        
        if (deals.length > 0) {
            dealsList.innerHTML = deals.map(deal => {
                const statusColors = {
                    won: '#10b981',
                    pending: '#f59e0b',
                    lost: '#ef4444',
                    cancelled: '#6b7280'
                };
                
                return `
                <div class="deal-item deal-status-${deal.status}">
                    <div class="deal-header">
                        <span class="deal-name">${escapeHtml(deal.deal_name || 'Unnamed Deal')}</span>
                        <span class="deal-amount">${deal.currency} ${parseFloat(deal.amount).toFixed(0)}</span>
                    </div>
                    <div class="deal-meta">
                        <span class="deal-status" style="background: ${statusColors[deal.status]};">${deal.status.toUpperCase()}</span>
                        <span class="deal-date">${formatPakistanTime(deal.deal_date || deal.created_at)}</span>
                    </div>
                    ${deal.notes ? `<div class="deal-notes">${escapeHtml(deal.notes)}</div>` : ''}
                </div>
            `;
            }).join('');
        } else {
            dealsList.innerHTML = '<div class="empty-state"><p>No deals yet</p></div>';
        }
    } catch (error) {
        console.error('Error loading deals:', error);
        const dealsList = document.getElementById('dealsList');
        if (dealsList) {
            dealsList.innerHTML = '<div class="empty-state"><p>Failed to load deals</p></div>';
        }
    }
}

/**
 * Show add deal form
 */
function showAddDealForm(contactId) {
    const form = document.getElementById('addDealForm');
    if (!form) {
        console.error('addDealForm element not found');
        showToast('Error: Form not found. Please refresh the page.', 'error');
        return;
    }
    
    form.style.display = 'block';
    
    // Set default date
    const dealDateInput = document.getElementById('dealDate');
    if (dealDateInput) {
        dealDateInput.valueAsDate = new Date();
    }
    
    // Scroll form into view - scroll the modal body to show the form
    const modalContent = document.getElementById('crmModalContent');
    if (modalContent) {
        setTimeout(() => {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Also scroll the modal content container
            const formTop = form.offsetTop;
            modalContent.scrollTo({
                top: formTop - 20,
                behavior: 'smooth'
            });
        }, 100);
    }
    
    // Focus on first input
    const dealNameInput = document.getElementById('dealName');
    if (dealNameInput) {
        setTimeout(() => dealNameInput.focus(), 200);
    }
}

/**
 * Hide add deal form
 */
function hideAddDealForm() {
    const form = document.getElementById('addDealForm');
    if (!form) return;
    
    form.style.display = 'none';
    
    // Clear form fields
    const dealName = document.getElementById('dealName');
    const dealAmount = document.getElementById('dealAmount');
    const dealStatus = document.getElementById('dealStatus');
    const dealDate = document.getElementById('dealDate');
    const dealNotes = document.getElementById('dealNotes');
    
    if (dealName) dealName.value = '';
    if (dealAmount) dealAmount.value = '';
    if (dealStatus) dealStatus.value = 'pending';
    if (dealDate) dealDate.valueAsDate = new Date();
    if (dealNotes) dealNotes.value = '';
}

/**
 * Save new deal
 */
async function saveDeal(contactId) {
    const form = document.getElementById('addDealFormElement');
    if (!form) return;
    
    // Validate form
    if (typeof FormValidator !== 'undefined') {
        const validator = new FormValidator('addDealFormElement', {
            deal_name: ['required', 'min:2', 'max:255'],
            amount: ['required', 'number', {min: 0}],
            deal_date: ['required']
        });
        
        if (!validator.validate()) {
            return;
        }
    } else {
        // Fallback validation
        const dealName = document.getElementById('dealName').value.trim();
        const amount = document.getElementById('dealAmount').value;
        const dealDate = document.getElementById('dealDate').value;
        
        let hasError = false;
        
        if (!dealName) {
            const nameField = document.getElementById('dealName');
            nameField.classList.add('is-invalid');
            nameField.nextElementSibling.textContent = 'Please enter a deal name';
            hasError = true;
        }
        
        if (!amount || parseFloat(amount) <= 0) {
            const amountField = document.getElementById('dealAmount');
            amountField.classList.add('is-invalid');
            amountField.nextElementSibling.textContent = 'Please enter a valid amount';
            hasError = true;
        }
        
        if (!dealDate) {
            const dateField = document.getElementById('dealDate');
            dateField.classList.add('is-invalid');
            dateField.nextElementSibling.textContent = 'Please select a date';
            hasError = true;
        }
        
        if (hasError) {
            showToast('Please fill in all required fields', 'error');
            return;
        }
    }
    
    const dealName = document.getElementById('dealName').value.trim();
    const amount = document.getElementById('dealAmount').value;
    const status = document.getElementById('dealStatus').value;
    const dealDate = document.getElementById('dealDate').value;
    const notes = document.getElementById('dealNotes').value.trim();
    
    try {
        // Try PATH_INFO style URL first
        let url = `crm.php/contact/${contactId}/deal`;
        let response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                deal_name: dealName,
                amount: amount,
                status: status,
                deal_date: dealDate,
                notes: notes
            })
        });
        
        // If that fails, try with query parameter fallback
        if (!response.ok && response.status === 404) {
            url = `crm.php?path=/contact/${contactId}/deal`;
            response = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    deal_name: dealName,
                    amount: amount,
                    status: status,
                    deal_date: dealDate,
                    notes: notes
                })
            });
        }
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Deal added successfully!', 'success');
            hideAddDealForm();
            await loadDeals(contactId);
            loadContacts(); // Refresh contact list
        } else {
            console.error('Deal API error:', result);
            showToast('Failed to add deal: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error adding deal:', error);
        showToast('Failed to add deal: ' + error.message, 'error');
    }
}

/**
 * Template Message Functions
 */
let templateContactId = null;
let templatePhoneNumber = null;

function openTemplateModal(contactId, phoneNumber) {
    templateContactId = contactId;
    templatePhoneNumber = phoneNumber;
    const templateModal = document.getElementById('templateModal');
    if (templateModal) {
        templateModal.style.display = 'flex';
        templateModal.classList.add('show');
        document.body.classList.add('modal-open');
    }
}

function closeTemplateModal() {
    const templateModal = document.getElementById('templateModal');
    if (templateModal) {
        templateModal.style.display = 'none';
        templateModal.classList.remove('show');
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        
        // Reset form
        const templateNameInput = document.getElementById('templateName');
        const templateLanguageSelect = document.getElementById('templateLanguage');
        if (templateNameInput) templateNameInput.value = 'hello_world';
        if (templateLanguageSelect) templateLanguageSelect.value = 'en';
        
        // Clear all parameters
        const paramContainer = document.getElementById('templateParameters');
        if (paramContainer) {
            paramContainer.innerHTML = '';
        }
    }
    templateContactId = null;
    templatePhoneNumber = null;
}

function addTemplateParam() {
    const container = document.getElementById('templateParameters');
    const paramCount = container.children.length + 1;
    
    const row = document.createElement('div');
    row.className = 'template-param-row';
    row.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
    
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'crm-input template-param-input';
    input.placeholder = `Parameter ${paramCount} (e.g., order number, name)`;
    input.style.cssText = 'flex: 1;';
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-secondary';
    removeBtn.textContent = 'Remove';
    removeBtn.style.cssText = 'padding: 8px 12px; min-width: auto;';
    removeBtn.onclick = function() { removeTemplateParam(this); };
    
    row.appendChild(input);
    row.appendChild(removeBtn);
    container.appendChild(row);
    
    // Focus on the new input
    input.focus();
}

function removeTemplateParam(button) {
    const row = button.closest('.template-param-row');
    if (row) {
        row.remove();
    }
}

async function sendTemplate() {
    const templateName = document.getElementById('templateName').value.trim();
    const languageCode = document.getElementById('templateLanguage').value;
    
    if (!templateName) {
        showToast('Please enter a template name', 'error');
        return;
    }
    
    if (!templateContactId || !templatePhoneNumber) {
        showToast('Contact information missing', 'error');
        return;
    }
    
    // Collect template parameters
    const paramInputs = document.querySelectorAll('.template-param-input');
    const parameters = [];
    paramInputs.forEach(input => {
        const value = input.value.trim();
        if (value) {
            parameters.push(value);
        }
    });
    
    try {
        const response = await fetch('api.php/send-template', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                to: templatePhoneNumber,
                template_name: templateName,
                language_code: languageCode,
                parameters: parameters,
                contact_id: templateContactId
            })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Template message sent successfully!', 'success');
            closeTemplateModal();
            await loadMessages(templateContactId);
            
            // Note: Message may fail later due to payment issues - check status updates
            // The webhook will update the status if delivery fails
        } else {
            const errorMsg = result.error || result.errors?.details?.error || 'Unknown error';
            
            // Check for payment issue
            if (errorMsg.includes('131042') || errorMsg.includes('payment issue') || errorMsg.includes('Business eligibility')) {
                showToast('Payment issue: Please check WhatsApp Business Manager billing settings. Message could not be sent.', 'error');
            } else {
                showToast('Failed to send template: ' + errorMsg, 'error');
            }
            console.error('Template error:', result);
        }
    } catch (error) {
        console.error('Error sending template:', error);
        showToast('Failed to send template message', 'error');
    }
}

// Modals should NOT close when clicking outside - removed backdrop click handler

/**
 * Validate message input
 */
function validateMessageInput() {
    const input = document.getElementById('messageInput');
    const message = input ? input.value.trim() : '';
    const validationMsg = document.getElementById('validationMessage');
    const form = document.getElementById('messageForm');
    
    // Check if there's a message or media selected
    if (!message && !selectedMediaFile) {
        if (validationMsg) {
            validationMsg.textContent = 'Please enter a message or select media';
            validationMsg.classList.add('show', 'error');
            validationMsg.classList.remove('success', 'warning', 'info');
        }
        if (form) {
            form.classList.add('error');
            form.classList.remove('success', 'warning');
        }
        return false;
    }
    
    // Check message length
    if (message && message.length > 4096) {
        if (validationMsg) {
            validationMsg.textContent = `Message too long (${message.length}/4096 characters)`;
            validationMsg.classList.add('show', 'error');
            validationMsg.classList.remove('success', 'warning', 'info');
        }
        if (form) {
            form.classList.add('error');
            form.classList.remove('success', 'warning');
        }
        return false;
    }
    
    // Check if contact is selected
    if (!currentContactId) {
        if (validationMsg) {
            validationMsg.textContent = 'Please select a contact first';
            validationMsg.classList.add('show', 'error');
            validationMsg.classList.remove('success', 'warning', 'info');
        }
        if (form) {
            form.classList.add('error');
            form.classList.remove('success', 'warning');
        }
        return false;
    }
    
    // Validation passed
    if (form) {
        form.classList.remove('error', 'warning');
        form.classList.add('success');
    }
    if (validationMsg) {
        validationMsg.classList.remove('show');
    }
    return true;
}

/**
 * Real-time message validation
 */
function validateMessageInputRealTime() {
    const input = document.getElementById('messageInput');
    const message = input ? input.value.trim() : '';
    const validationMsg = document.getElementById('validationMessage');
    const form = document.getElementById('messageForm');
    
    // Clear validation message while typing
    if (message.length < 4096 && !validationMsg?.classList.contains('error')) {
        if (validationMsg) {
            validationMsg.classList.remove('show');
        }
        return true;
    }
    
    // Check for message too long
    if (message.length > 4096) {
        if (validationMsg) {
            validationMsg.textContent = `Message too long (${message.length}/4096 characters)`;
            validationMsg.classList.add('show', 'warning');
            validationMsg.classList.remove('error', 'success', 'info');
        }
        if (form) {
            form.classList.add('warning');
            form.classList.remove('error', 'success');
        }
        return false;
    }
    
    return true;
}

/**
 * Update character counter for message input
 */
function updateCharacterCounter() {
    const input = document.getElementById('messageInput');
    const counter = document.getElementById('characterCounter');
    
    if (input && counter) {
        const count = input.value.length;
        counter.textContent = `${count}/4096`;
        
        if (count > 4000) {
            counter.style.color = '#e74c3c';
        } else if (count > 3000) {
            counter.style.color = '#f39c12';
        } else {
            counter.style.color = 'var(--text-secondary)';
        }
    }
}

/**
 * Media Upload Functions
 */
let selectedMediaFile = null;
let selectedMediaType = null;

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const validationMsg = document.getElementById('validationMessage');
    const form = document.getElementById('messageForm');
    
    // Validate file size (max 16MB for WhatsApp)
    const maxSize = 16 * 1024 * 1024; // 16MB
    if (file.size > maxSize) {
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        const errorMsg = `File size (${fileSizeMB}MB) exceeds the maximum limit of 16MB`;
        
        if (form) {
            form.classList.add('error');
            form.classList.remove('success', 'warning');
        }
        if (validationMsg) {
            validationMsg.textContent = errorMsg;
            validationMsg.classList.add('show', 'error');
            validationMsg.classList.remove('success', 'warning', 'info');
        }
        showToast('File size must be less than 16MB', 'error');
        clearMediaSelection();
        return;
    }
    
    // Validate file type
    const allowedTypes = ['image/', 'video/', 'audio/', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const isValidType = allowedTypes.some(type => file.type.startsWith(type) || file.type === type);
    
    if (!isValidType) {
        const errorMsg = `File type "${file.type}" is not supported. Please use images, videos, audio, or PDF files.`;
        if (form) {
            form.classList.add('error');
            form.classList.remove('success', 'warning');
        }
        if (validationMsg) {
            validationMsg.textContent = errorMsg;
            validationMsg.classList.add('show', 'error');
            validationMsg.classList.remove('success', 'warning', 'info');
        }
        showToast('File type not supported', 'error');
        clearMediaSelection();
        return;
    }
    
    // Clear any previous errors
    if (form) {
        form.classList.remove('error');
        form.classList.add('success');
    }
    if (validationMsg) {
        validationMsg.classList.remove('show', 'error');
    }
    
    selectedMediaFile = file;
    
    // Determine media type based on file type
    if (file.type.startsWith('image/')) {
        selectedMediaType = 'image';
    } else if (file.type.startsWith('video/')) {
        selectedMediaType = 'video';
    } else if (file.type.startsWith('audio/')) {
        selectedMediaType = 'audio';
    } else {
        selectedMediaType = 'document';
    }
    
    // Show preview
    const preview = document.getElementById('mediaPreview');
    const previewImage = document.getElementById('mediaPreviewImage');
    const fileName = document.getElementById('mediaFileName');
    const fileSize = document.getElementById('mediaFileSize');
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    // Create preview based on file type
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(file);
    } else if (file.type.startsWith('video/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.innerHTML = `<video controls><source src="${e.target.result}" type="${file.type}"></video>`;
        };
        reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
        previewImage.innerHTML = `
            <div style="padding: 40px; text-align: center;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="#d32f2f">
                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M15.5,16H14V19H12.5V16H11V19H9.5V13.5H15.5V16M9.5,11.5A1.5,1.5 0 0,1 8,10V9.5A1.5,1.5 0 0,1 9.5,8H11V11.5H9.5M14.5,11.5A1.5,1.5 0 0,1 13,10V9.5A1.5,1.5 0 0,1 14.5,8H16V11.5H14.5Z"/>
                </svg>
                <div style="margin-top: 12px; color: var(--text-secondary);">PDF Document</div>
            </div>
        `;
    } else {
        previewImage.innerHTML = `
            <div style="padding: 40px; text-align: center;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="var(--text-secondary)">
                    <path d="M13,9H18.5L13,3.5V9M6,2H14L20,8V20A2,2 0 0,1 18,22H6C4.89,22 4,21.1 4,20V4C4,2.89 4.89,2 6,2M15,18V16H6V18H15M18,14V12H6V14H18Z"/>
                </svg>
                <div style="margin-top: 12px; color: var(--text-secondary);">Document</div>
            </div>
        `;
    }
    
    preview.style.display = 'block';
}

function clearMediaSelection() {
    selectedMediaFile = null;
    selectedMediaType = null;
    document.getElementById('mediaInput').value = '';
    document.getElementById('mediaPreview').style.display = 'none';
    document.getElementById('mediaPreviewImage').innerHTML = '';
    document.getElementById('mediaCaption').value = '';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Star a message
 */
async function starMessage(messageId, buttonElement) {
    try {
        const response = await fetch('api.php/message-action', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message_id: messageId,
                action_type: 'star'
            })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            // Toggle star icon
            const icon = buttonElement.querySelector('i');
            if (icon) {
                if (icon.classList.contains('far')) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    showToast('Message starred', 'success');
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    showToast('Message unstarred', 'success');
                }
            }
        } else {
            showToast(result.error || 'Failed to star message', 'error');
        }
    } catch (error) {
        console.error('Error starring message:', error);
        showToast('Failed to star message', 'error');
    }
}

/**
 * Forward a message
 */
async function forwardMessage(messageId) {
    // Build contact selection modal dynamically
    let modal = document.getElementById('forwardModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'forwardModal';
        modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1100;';
        modal.innerHTML = `
            <div style="background: white; border-radius: 12px; width: 520px; max-width: 90vw; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                <div style="padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
                    <h3 style="margin: 0; font-size: 18px;">Forward to Contact</h3>
                    <button id="forwardCloseBtn" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
                </div>
                <div style="padding: 14px 20px;">
                    <input id="forwardSearch" type="text" placeholder="Search contacts..." style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                    <div id="forwardList" style="max-height: 320px; overflow:auto; margin-top: 12px; border:1px solid #eee; border-radius:8px;"></div>
                </div>
                <div style="padding: 12px 20px; border-top: 1px solid #eee; display:flex; gap:10px; justify-content:flex-end;">
                    <button id="forwardCancel" class="btn-secondary" style="padding:8px 12px;">Cancel</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const listEl = modal.querySelector('#forwardList');
    const searchEl = modal.querySelector('#forwardSearch');
    const closeBtn = modal.querySelector('#forwardCloseBtn');
    const cancelBtn = modal.querySelector('#forwardCancel');

    function renderForwardList(items) {
        listEl.innerHTML = items.map(c => `
            <div class="forward-item" data-id="${c.id}" style="padding:10px 12px; display:flex; align-items:center; gap:10px; cursor:pointer; border-bottom:1px solid #f5f5f5;">
                <div class="contact-avatar">${getInitials(c.name)}</div>
                <div style="flex:1;">
                    <div style="font-weight:600;">${escapeHtml(c.name)}</div>
                    <div style="color:#6b7280; font-size:12px;">${c.phone_number}</div>
                </div>
                <button class="btn-primary" style="padding:6px 10px;">Forward</button>
            </div>
        `).join('');
    }

    // Initial render
    renderForwardList(contacts);

    // Search
    searchEl.oninput = function(e) {
        const q = e.target.value.toLowerCase();
        const filtered = contacts.filter(c => (c.name || '').toLowerCase().includes(q) || (c.phone_number || '').includes(q));
        renderForwardList(filtered);
    };

    // Item click
    listEl.onclick = async function(e) {
        const item = e.target.closest('.forward-item');
        if (!item) return;
        const targetId = parseInt(item.dataset.id, 10);
        try {
            const response = await fetch('api.php/message-action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message_id: messageId,
                    action_type: 'forward',
                    forward_to_contact_id: targetId
                })
            });
            const result = await response.json();
            if (response.ok && result.success) {
                showToast('Message forwarded successfully!', 'success');
                modal.style.display = 'none';
            } else {
                showToast(result.error || 'Failed to forward message', 'error');
            }
        } catch (err) {
            console.error('Forward error:', err);
            showToast('Failed to forward message', 'error');
        }
    };

    closeBtn.onclick = () => { modal.style.display = 'none'; };
    cancelBtn.onclick = () => { modal.style.display = 'none'; };

    // Show modal
    modal.style.display = 'flex';
}

/**
 * Desktop Notifications
 */
let lastMessageIds = new Set();

function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showToast('Desktop notifications enabled!', 'success');
            }
        });
    }
}

async function checkForNewMessages() {
    // Don't check if notifications aren't supported or permitted
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }
    
    // Don't show notifications if user is viewing the page
    if (!document.hidden) {
        return;
    }
    
    try {
        const response = await fetch('api.php/contacts');
        if (!response.ok) return;
        
        const currentContacts = await response.json();
        
        for (const contact of currentContacts) {
            // Check if this contact has unread messages
            if (contact.unread_count > 0 && contact.last_message) {
                // Create a unique ID for this message
                const messageKey = `${contact.id}_${contact.last_message_time}`;
                
                // Only notify if we haven't seen this message before
                if (!lastMessageIds.has(messageKey)) {
                    lastMessageIds.add(messageKey);
                    
                    // Show notification
                    const notification = new Notification(`New message from ${contact.name}`, {
                        body: truncateText(contact.last_message, 100),
                        icon: contact.profile_picture_url || '/assets/img/default-avatar.png',
                        badge: '/assets/img/whatsapp-icon.png',
                        tag: `message_${contact.id}`,
                        requireInteraction: false,
                        silent: false
                    });
                    
                    // Handle notification click
                    notification.onclick = function() {
                        window.focus();
                        // Navigate to the contact
                        window.location.href = `index.php?contact=${contact.id}`;
                        notification.close();
                    };
                    
                    // Auto close after 5 seconds
                    setTimeout(() => notification.close(), 5000);
                }
            }
        }
        
        // Clean up old message IDs (keep only last 100)
        if (lastMessageIds.size > 100) {
            const idsArray = Array.from(lastMessageIds);
            lastMessageIds = new Set(idsArray.slice(-100));
        }
    } catch (error) {
        console.error('Error checking for new messages:', error);
    }
}

/**
 * Open image in lightbox modal
 */
function openImageModal(imageUrl) {
    // Create modal if doesn't exist
    let modal = document.getElementById('imageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'imageModal';
        modal.style.cssText = `
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            cursor: zoom-out;
        `;
        // Modal should NOT close when clicking outside - removed backdrop click handler
        
        const img = document.createElement('img');
        img.id = 'modalImage';
        img.style.cssText = `
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        `;
        
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        `;
        closeBtn.onclick = function(e) {
            e.stopPropagation();
            modal.style.display = 'none';
        };
        
        modal.appendChild(img);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);
    }
    
    // Show modal with image
    const modalImage = document.getElementById('modalImage');
    modalImage.src = imageUrl;
    modalImage.onerror = function() {
        modal.style.display = 'none';
        showToast('Image failed to load', 'error');
    };
    modal.style.display = 'block';
    
    // ESC key to close
    document.onkeydown = function(e) {
        if (e.key === 'Escape') {
            modal.style.display = 'none';
        }
    };
}

function isNearBottom(container) {
    return container.scrollHeight - container.scrollTop - container.clientHeight <= NEW_MESSAGE_SCROLL_THRESHOLD;
}

function scrollToBottom(container) {
    container.scrollTop = container.scrollHeight;
}

function createNewMessageIndicator() {
    if (newMessageIndicator) {
        return;
    }
    newMessageIndicator = document.createElement('button');
    newMessageIndicator.id = 'newMessageIndicator';
    newMessageIndicator.textContent = 'New messages';
    newMessageIndicator.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #128C7E;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 999px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        cursor: pointer;
        display: none;
        z-index: 1100;
    `;
    newMessageIndicator.onclick = () => {
        const container = document.getElementById('messagesContainer');
        if (container) {
            scrollToBottom(container);
        }
        hideNewMessageIndicator();
    };
    document.body.appendChild(newMessageIndicator);
}

function showNewMessageIndicator() {
    if (newMessageIndicator) {
        newMessageIndicator.style.display = 'inline-flex';
    }
}

function hideNewMessageIndicator() {
    if (newMessageIndicator) {
        newMessageIndicator.style.display = 'none';
    }
}

function handleMessageScroll() {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    if (isNearBottom(container)) {
        hideNewMessageIndicator();
    }
}

/**
 * Show full time when clicking on time
 */
function showFullTime(element, timestamp) {
    const date = new Date(timestamp);
    const fullTime = date.toLocaleString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    // Toggle between relative and full time
    if (element.dataset.showingFull === 'true') {
        // Switch back to relative time
        element.dataset.showingFull = 'false';
        const relativeTime = formatTime(timestamp);
        element.textContent = relativeTime;
    } else {
        // Show full time
        element.dataset.showingFull = 'true';
        element.textContent = fullTime;
        
        // Auto-switch back after 3 seconds
        setTimeout(() => {
            if (element.dataset.showingFull === 'true') {
                element.dataset.showingFull = 'false';
                const relativeTime = formatTime(timestamp);
                element.textContent = relativeTime;
            }
        }, 3000);
    }
}

function truncateText(text, maxLength) {
    if (!text) return '';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}
