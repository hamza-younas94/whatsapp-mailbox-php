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
        
        contacts = await response.json();
        renderContacts(contacts);
        
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
 * Poll for new messages without reloading the thread
 */
async function pollNewMessages() {
    if (!currentContactId || !lastMessageId) {
        return;
    }
    
    try {
        const response = await fetch(`api.php/messages?contact_id=${currentContactId}&after_id=${lastMessageId}`);
        if (!response.ok) {
            return;
        }
        
        const newMessages = await response.json();
        if (!Array.isArray(newMessages) || newMessages.length === 0) {
            return;
        }
        
        messages = messages.concat(newMessages);
        lastMessageId = newMessages[newMessages.length - 1].id;
        renderMessages(newMessages, { replace: false });
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
        // For text messages
        else {
            content = `<div class="message-text">${escapeHtml(message.message_body)}</div>`;
        }
        
        return `
            <div class="message ${direction}">
                <div class="message-bubble">
                    ${content}
                    <div class="message-time">
                        ${time}
                        ${status}
                    </div>
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
                showValidationError(errorMsg, validationErrors);
                showToast('Failed to send: ' + errorMsg, 'error');
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
 * Get status icon - Modern SVG icons
 */
function getStatusIcon(status) {
    switch (status) {
        case 'sent':
            return `<svg class="message-status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>`;
        case 'delivered':
            return `<span class="message-status-double">
                <svg class="message-status-icon delivered" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <svg class="message-status-icon delivered" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>`;
        case 'read':
            return `<span class="message-status-double">
                <svg class="message-status-icon read" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <svg class="message-status-icon read" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
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
function openCrmModal(contactId) {
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
        // Hide add deal form if open
        hideAddDealForm();
    }
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const crmModal = document.getElementById('crmModal');
    if (crmModal) {
        crmModal.addEventListener('click', function(e) {
            if (e.target === crmModal) {
                closeCrmModal();
            }
        });
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
    }
    templateContactId = null;
    templatePhoneNumber = null;
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
    
    try {
        const response = await fetch('api.php/send-template', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                to: templatePhoneNumber,
                template_name: templateName,
                language_code: languageCode,
                contact_id: templateContactId
            })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Template message sent successfully!', 'success');
            closeTemplateModal();
            await loadMessages(templateContactId);
        } else {
            showToast('Failed to send template: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error sending template:', error);
        showToast('Failed to send template message', 'error');
    }
}

// Close template modal when clicking outside (on backdrop only)
window.onclick = function(event) {
    const templateModal = document.getElementById('templateModal');
    
    if (event.target === templateModal) {
        closeTemplateModal();
    }
}
/**
 * Media Upload Functions
 */
let selectedMediaFile = null;

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
        modal.onclick = function() {
            modal.style.display = 'none';
        };
        
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

function truncateText(text, maxLength) {
    if (!text) return '';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}
