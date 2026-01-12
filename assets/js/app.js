/**
 * WhatsApp Mailbox JavaScript
 */

let currentContactId = null;
let contacts = [];
let messages = [];
let messagePollingInterval = null;

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
        
        // CRM features
        const stageBadge = contact.stage ? `<span class="stage-badge stage-${contact.stage}">${contact.stage}</span>` : '';
        const leadScore = contact.lead_score !== null ? `<span class="lead-score" title="Lead Score">${contact.lead_score}</span>` : '';
        const companyInfo = contact.company_name ? `<div class="contact-company">${escapeHtml(contact.company_name)}</div>` : '';
        
        return `
            <div class="contact-item ${activeClass}" onclick="selectContact(${contact.id}, '${escapeHtml(contact.name)}', '${contact.phone_number}')">
                <div class="contact-avatar">${initials}</div>
                <div class="contact-info">
                    <div class="contact-name-row">
                        <span class="contact-name">${escapeHtml(contact.name)}</span>
                        ${stageBadge}
                    </div>
                    ${companyInfo}
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
    
    // Get full contact data
    const contact = contacts.find(c => c.id === contactId);
    
    // Update UI
    const chatHeader = document.getElementById('chatHeader');
    const crmInfo = contact ? `
        <div class="chat-crm-info">
            ${contact.company_name ? `<span class="crm-company">${escapeHtml(contact.company_name)}</span>` : ''}
            ${contact.stage ? `<span class="stage-badge stage-${contact.stage}">${contact.stage}</span>` : ''}
            ${contact.lead_score !== null ? `<span class="lead-score-badge">${contact.lead_score}/100</span>` : ''}
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20,2H4A2,2 0 0,0 2,4V22L6,18H20A2,2 0 0,0 22,16V4A2,2 0 0,0 20,2M6,9H18V11H6M14,14H6V12H14M18,8H6V6H18"/>
                </svg>
            </button>
            <button onclick="openCrmModal(${contactId})" class="btn-crm" title="CRM Actions">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M12,3A1,1 0 0,1 13,4A1,1 0 0,1 12,5A1,1 0 0,1 11,4A1,1 0 0,1 12,3Z"/>
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
        showToast('Contact not found', 'error');
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
            
            // Update message limit display
            if (result.messages_remaining !== undefined) {
                updateMessageLimitDisplay();
                if (result.messages_remaining <= 10) {
                    showToast(`Warning: Only ${result.messages_remaining} messages remaining!`, 'info');
                }
            }
        } else {
            const errorMsg = result.error || 'Unknown error';
            
            // Check if it's message limit
            if (response.status === 429) {
                showToast('Message limit reached! Please upgrade to continue.', 'error');
                document.getElementById('messageInput').disabled = true;
                updateMessageLimitDisplay();
                return;
            }
            
            // Check if it's a 24-hour window issue
            if (response.status === 403 && errorMsg.includes('not messaged you recently')) {
                showToast('Cannot send: Contact must message you first (24-hour window)', 'error');
            } else {
                showToast('Failed to send: ' + errorMsg, 'error');
            }
        }
        
    } catch (error) {
        console.error('Error sending message:', error);
        showToast('Failed to send message', 'error');
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

/**
 * Open CRM side panel
 */
function openCrmModal(contactId) {
    const contact = contacts.find(c => c.id === contactId);
    if (!contact) return;
    
    const panel = document.getElementById('crmSidePanel');
    const content = document.getElementById('crmPanelContent');
    const container = document.querySelector('.mailbox-container');
    
    content.innerHTML = `
        <div class="modal-header">
            <h2>CRM: ${escapeHtml(contact.name)}</h2>
            <button onclick="closeCrmModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="crm-section">
                <h3>Stage Management</h3>
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
                <h3>Lead Score: <span id="currentScore">${contact.lead_score || 0}</span>/100</h3>
                <div class="score-bar">
                    <div class="score-fill" style="width: ${contact.lead_score || 0}%"></div>
                </div>
            </div>
            
            <div class="crm-section">
                <h3>Company Information</h3>
                <input type="text" id="crmCompany" class="crm-input" placeholder="Company Name" value="${contact.company_name || ''}">
                <input type="email" id="crmEmail" class="crm-input" placeholder="Email" value="${contact.email || ''}">
                <input type="text" id="crmCity" class="crm-input" placeholder="City" value="${contact.city || ''}">
                <button onclick="updateCompanyInfo(${contactId})" class="btn-primary">Update Info</button>
            </div>
            
            <div class="crm-section">
                <h3>Deal Information</h3>
                <input type="number" id="crmDealValue" class="crm-input" placeholder="Deal Value" value="${contact.deal_value || ''}">
                <input type="date" id="crmExpectedClose" class="crm-input" value="${contact.expected_close_date || ''}">
                <button onclick="updateDealInfo(${contactId})" class="btn-primary">Update Deal</button>
            </div>
            
            <div class="crm-section">
                <h3>Add Note</h3>
                <textarea id="crmNote" class="crm-textarea" placeholder="Type your note here..." rows="3"></textarea>
                <select id="crmNoteType" class="crm-select">
                    <option value="general">General</option>
                    <option value="call">Call</option>
                    <option value="meeting">Meeting</option>
                    <option value="email">Email</option>
                </select>
                <button onclick="addNote(${contactId})" class="btn-primary">Add Note</button>
            </div>
            
            <div class="crm-section">
                <h3>Recent Notes</h3>
                <div id="notesList" class="notes-list">
                    <div class="loading">Loading notes...</div>
                </div>
            </div>
            
            <div class="crm-section">
                <h3>Deal History</h3>
                <div class="deal-summary" id="dealSummary">
                    <div class="loading">Loading deals...</div>
                </div>
                <div id="dealsList" class="deals-list">
                </div>
                <button onclick="showAddDealForm(${contactId})" class="btn-secondary">+ Add New Deal</button>
            </div>
            
            <div class="crm-section" id="addDealForm" style="display: none;">
                <h3>Add New Deal</h3>
                <input type="text" id="dealName" class="crm-input" placeholder="Deal Name (e.g., Website Package)">
                <input type="number" id="dealAmount" class="crm-input" placeholder="Amount" step="0.01">
                <select id="dealStatus" class="crm-select">
                    <option value="pending">Pending</option>
                    <option value="won">Won</option>
                    <option value="lost">Lost</option>
                </select>
                <input type="date" id="dealDate" class="crm-input">
                <textarea id="dealNotes" class="crm-textarea" placeholder="Deal notes..." rows="2"></textarea>
                <div style="display: flex; gap: 10px;">
                    <button onclick="saveDeal(${contactId})" class="btn-primary" style="flex: 1;">Save Deal</button>
                    <button onclick="hideAddDealForm()" class="btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </div>
        </div>
    `;
    
    panel.style.display = 'flex';
    container.classList.add('panel-open');
    loadNotes(contactId);
    loadDeals(contactId);
}

/**
 * Close CRM side panel
 */
function closeCrmPanel() {
    document.getElementById('crmSidePanel').style.display = 'none';
    document.querySelector('.mailbox-container').classList.remove('panel-open');
}

// Keep old function for backward compatibility
function closeCrmModal() {
    closeCrmPanel();
}

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
            selectContact(contactId, '', '');
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
    const company_name = document.getElementById('crmCompany').value;
    const email = document.getElementById('crmEmail').value;
    const city = document.getElementById('crmCity').value;
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/crm`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ company_name, email, city })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Company info updated!', 'success');
            loadContacts();
        } else {
            showToast('Failed to update: ' + (result.error || 'Unknown error'), 'error');
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
    const content = document.getElementById('crmNote').value.trim();
    const type = document.getElementById('crmNoteType').value;
    
    if (!content) {
        showToast('Please enter a note', 'error');
        return;
    }
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/note`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ content, type })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            document.getElementById('crmNote').value = '';
            showToast('Note added successfully!', 'success');
            await loadNotes(contactId);
        } else {
            showToast('Failed to add note: ' + (result.error || 'Unknown error'), 'error');
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
    form.style.display = 'block';
    document.getElementById('dealDate').valueAsDate = new Date();
}

/**
 * Hide add deal form
 */
function hideAddDealForm() {
    const form = document.getElementById('addDealForm');
    form.style.display = 'none';
    // Clear form
    document.getElementById('dealName').value = '';
    document.getElementById('dealAmount').value = '';
    document.getElementById('dealStatus').value = 'pending';
    document.getElementById('dealDate').valueAsDate = new Date();
    document.getElementById('dealNotes').value = '';
}

/**
 * Save new deal
 */
async function saveDeal(contactId) {
    const dealName = document.getElementById('dealName').value.trim();
    const amount = document.getElementById('dealAmount').value;
    const status = document.getElementById('dealStatus').value;
    const dealDate = document.getElementById('dealDate').value;
    const notes = document.getElementById('dealNotes').value.trim();
    
    if (!dealName || !amount) {
        showToast('Please enter deal name and amount', 'error');
        return;
    }
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/deal`, {
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
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Deal added successfully!', 'success');
            hideAddDealForm();
            await loadDeals(contactId);
            loadContacts(); // Refresh contact list
        } else {
            showToast('Failed to add deal: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error adding deal:', error);
        showToast('Failed to add deal', 'error');
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
    document.getElementById('templateModal').style.display = 'flex';
}

function closeTemplateModal() {
    document.getElementById('templateModal').style.display = 'none';
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
            loadContacts();
        } else {
            showToast('Failed to send template: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error sending template:', error);
        showToast('Failed to send template message', 'error');
    }
}

// Close panel when clicking outside
window.onclick = function(event) {
    const panel = document.getElementById('crmSidePanel');
    const modal = document.getElementById('templateModal');
    
    if (event.target === panel) {
        closeCrmPanel();
    }
    
    if (event.target === modal) {
        closeTemplateModal();
    }
}
