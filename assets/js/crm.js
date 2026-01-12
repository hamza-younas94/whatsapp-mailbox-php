/**
 * CRM Dashboard JavaScript
 */

let allContacts = [];
let currentFilter = 'all';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadCrmData();
    
    // Search
    document.getElementById('crmSearch').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        filterContacts(query);
    });
    
    // Refresh every 10 seconds
    setInterval(loadCrmData, 10000);
});

/**
 * Load CRM data
 */
async function loadCrmData() {
    try {
        const response = await fetch('api.php/contacts');
        
        if (!response.ok) {
            throw new Error('Failed to load contacts');
        }
        
        allContacts = await response.json();
        updateStats(allContacts);
        renderCrmTable(allContacts);
        
    } catch (error) {
        console.error('Error loading CRM data:', error);
    }
}

/**
 * Update statistics cards
 */
function updateStats(contacts) {
    // Total contacts
    document.getElementById('totalContacts').textContent = contacts.length || '0';
    
    // Qualified leads (qualified stage or higher)
    const qualifiedStages = ['qualified', 'proposal', 'negotiation', 'customer'];
    const qualified = contacts.filter(c => c.stage && qualifiedStages.includes(c.stage)).length;
    document.getElementById('qualifiedLeads').textContent = qualified || '0';
    
    // Total deal value
    const totalDealValue = contacts.reduce((sum, c) => {
        const value = parseFloat(c.deal_value);
        return sum + (isNaN(value) ? 0 : value);
    }, 0);
    document.getElementById('totalDealValue').textContent = '$' + (totalDealValue || 0).toLocaleString();
    
    // Average lead score
    const scores = contacts.filter(c => c.lead_score !== null && c.lead_score !== undefined).map(c => parseInt(c.lead_score));
    const avgScore = scores.length > 0 
        ? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length)
        : 0;
    document.getElementById('averageScore').textContent = avgScore || '0';
}

/**
 * Render CRM table
 */
function renderCrmTable(contacts) {
    const tbody = document.getElementById('crmTableBody');
    
    if (contacts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No contacts found</td></tr>';
        return;
    }
    
    tbody.innerHTML = contacts.map(contact => {
        const stageBadge = contact.stage 
            ? `<span class="stage-badge stage-${contact.stage}">${contact.stage.toUpperCase()}</span>`
            : '<span class="text-muted">New</span>';
        
        const leadScore = contact.lead_score !== null && contact.lead_score !== undefined
            ? `<div class="score-indicator">
                <div class="score-bar-small">
                    <div class="score-fill" style="width: ${contact.lead_score}%"></div>
                </div>
                <span>${contact.lead_score}/100</span>
               </div>`
            : '<span class="text-muted">0/100</span>';
        
        const dealValue = contact.deal_value && !isNaN(parseFloat(contact.deal_value))
            ? `$${parseFloat(contact.deal_value).toLocaleString()}`
            : '<span class="text-muted">-</span>';
        
        const lastActivity = contact.last_activity_at
            ? formatTime(contact.last_activity_at)
            : '<span class="text-muted">Never</span>';
        
        return `
            <tr>
                <td>
                    <div class="contact-cell">
                        <div class="contact-avatar-small">${getInitials(contact.name)}</div>
                        <strong>${escapeHtml(contact.name)}</strong>
                    </div>
                </td>
                <td>${contact.company_name ? escapeHtml(contact.company_name) : '<span class="text-muted">-</span>'}</td>
                <td>${contact.phone_number}</td>
                <td>${stageBadge}</td>
                <td>${leadScore}</td>
                <td>${dealValue}</td>
                <td>${lastActivity}</td>
                <td>
                    <button onclick="openCrmModal(${contact.id})" class="btn-action" title="Manage CRM">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                    </button>
                    <a href="index.php?contact=${contact.id}" class="btn-action" title="View Messages">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                        </svg>
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Filter by stage
 */
function filterByStage(stage) {
    currentFilter = stage;
    
    // Update active button
    document.querySelectorAll('.stage-filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-stage="${stage}"]`).classList.add('active');
    
    // Filter contacts
    if (stage === 'all') {
        renderCrmTable(allContacts);
    } else {
        const filtered = allContacts.filter(c => c.stage === stage);
        renderCrmTable(filtered);
    }
}

/**
 * Filter contacts by search query
 */
function filterContacts(query) {
    if (!query) {
        filterByStage(currentFilter);
        return;
    }
    
    const filtered = allContacts.filter(contact => 
        contact.name.toLowerCase().includes(query) ||
        contact.phone_number.includes(query) ||
        (contact.company_name && contact.company_name.toLowerCase().includes(query)) ||
        (contact.email && contact.email.toLowerCase().includes(query))
    );
    
    renderCrmTable(filtered);
}

/**
 * Open CRM modal (same as mailbox)
 */
function openCrmModal(contactId) {
    const contact = allContacts.find(c => c.id === contactId);
    if (!contact) return;
    
    const modal = document.getElementById('crmModal');
    const content = document.getElementById('crmModalContent');
    
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
                <h3>Tags</h3>
                <div id="crmTagsList" class="tags-list">
                    <div class="loading">Loading tags...</div>
                </div>
                <button onclick="saveContactTags(${contactId})" class="btn-primary">Save Tags</button>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
    loadNotes(contactId);
    loadTagsForCrm(contactId, contact);
}

function closeCrmModal() {
    document.getElementById('crmModal').style.display = 'none';
}

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
            alert('Stage updated successfully!');
            loadCrmData();
            closeCrmModal();
        } else {
            alert('Failed to update stage: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating stage:', error);
        alert('Failed to update stage');
    }
}

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
            alert('Company info updated!');
            loadCrmData();
        } else {
            alert('Failed to update: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating company info:', error);
        alert('Failed to update');
    }
}

async function addNote(contactId) {
    const content = document.getElementById('crmNote').value.trim();
    const type = document.getElementById('crmNoteType').value;
    
    if (!content) {
        alert('Please enter a note');
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
            loadNotes(contactId);
            alert('Note added!');
        } else {
            alert('Failed to add note: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error adding note:', error);
        alert('Failed to add note');
    }
}

async function loadNotes(contactId) {
    try {
        const response = await fetch(`crm.php/contact/${contactId}/notes`);
        const result = await response.json();
        
        const notesList = document.getElementById('notesList');
        
        if (result.success && result.notes.length > 0) {
            notesList.innerHTML = result.notes.map(note => `
                <div class="note-item note-type-${note.type}">
                    <div class="note-header">
                        <span class="note-type">${note.type}</span>
                        <span class="note-date">${formatTime(note.created_at)}</span>
                    </div>
                    <div class="note-content">${escapeHtml(note.content)}</div>
                    <div class="note-author">by ${note.created_by_name || 'Admin'}</div>
                </div>
            `).join('');
        } else {
            notesList.innerHTML = '<div class="empty-state"><p>No notes yet</p></div>';
        }
    } catch (error) {
        console.error('Error loading notes:', error);
        document.getElementById('notesList').innerHTML = '<div class="empty-state"><p>Failed to load notes</p></div>';
    }
}

/**
 * Load tags list and preselect for contact
 */
async function loadTagsForCrm(contactId, contact) {
    try {
        const response = await fetch('tags.php?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        const container = document.getElementById('crmTagsList');
        
        if (!container) return;
        
        if (response.ok && result.success) {
            const tags = result.tags || [];
            const selectedIds = (contact && Array.isArray(contact.tag_ids)) ? contact.tag_ids : [];
            container.innerHTML = tags.map(tag => {
                const checked = selectedIds.includes(tag.id) ? 'checked' : '';
                const color = tag.color || '#6b7280';
                const name = escapeHtml(tag.name);
                return `
                    <label class="tag-option">
                        <input type="checkbox" class="tag-checkbox" value="${tag.id}" ${checked}>
                        <span class="tag-color" style="background-color: ${color};"></span>
                        <span class="tag-name">${name}</span>
                    </label>
                `;
            }).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><p>Failed to load tags</p></div>';
        }
    } catch (err) {
        console.error('Error loading tags:', err);
        const container = document.getElementById('crmTagsList');
        if (container) container.innerHTML = '<div class="empty-state"><p>Failed to load tags</p></div>';
    }
}

/**
 * Save selected tags for contact
 */
async function saveContactTags(contactId) {
    const checkboxes = document.querySelectorAll('#crmTagsList .tag-checkbox');
    const tagIds = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
    
    const formData = new FormData();
    formData.append('action', 'assign');
    formData.append('contact_id', contactId);
    tagIds.forEach(id => formData.append('tag_ids[]', id));
    
    try {
        const response = await fetch('tags.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const result = await response.json();
        if (response.ok && result.success) {
            showToast('Tags updated successfully!', 'success');
            loadCrmData();
            closeCrmModal();
        } else {
            showToast('Failed to update tags: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (err) {
        console.error('Error saving tags:', err);
        showToast('Failed to update tags', 'error');
    }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Create toast element if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} show`;
    toast.style.cssText = 'background:white;padding:16px 24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);margin-bottom:10px;border-left:4px solid;display:flex;align-items:center;gap:10px;min-width:300px;animation:slideIn 0.3s ease;';
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6'
    };
    toast.style.borderLeftColor = colors[type] || colors.info;
    
    const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ';
    toast.innerHTML = `<span style="font-size:18px;">${icon}</span><span style="color:#1f2937;font-weight:500;">${message}</span>`;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Utility functions
function getInitials(name) {
    if (!name) return '?';
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 86400000) {
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    if (diff < 604800000) {
        return date.toLocaleDateString('en-US', { weekday: 'short' });
    }
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('crmModal');
    if (event.target === modal) {
        closeCrmModal();
    }
}
