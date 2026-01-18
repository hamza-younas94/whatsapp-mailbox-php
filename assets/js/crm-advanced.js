/**
 * Advanced CRM Features JavaScript
 * Timeline, Tasks, Message Actions, etc.
 */

let currentCrmContactId = null;
let currentCrmTab = 'overview';

/**
 * Open CRM Modal with Tabs (Advanced)
 */
window.openCrmModalAdvanced = async function openCrmModal(contactId) {
    // Use global contacts array (from crm.js or app.js)
    let contactsList = window.allContacts || window.contacts || [];
    let contact = contactsList.find(c => c.id == contactId);
    
    // If contact not found, try to fetch all contacts
    if (!contact) {
        try {
            const response = await fetch('api.php/contacts');
            if (response.ok) {
                const allContactsData = await response.json();
                // Update global arrays
                window.allContacts = allContactsData;
                window.contacts = allContactsData;
                // Update local allContacts if it exists (from crm.js)
                if (typeof allContacts !== 'undefined') {
                    allContacts = allContactsData;
                }
                contactsList = allContactsData;
                contact = allContactsData.find(c => c.id == contactId);
            }
        } catch (e) {
            console.error('Error fetching contacts:', e);
        }
    }
    
    if (!contact) {
        console.error('Contact not found:', contactId);
        if (typeof showToast === 'function') {
            showToast('Contact not found', 'error');
        }
        return;
    }
    
    currentCrmContactId = contactId;
    currentCrmTab = 'overview';
    
    const modal = document.getElementById('crmModal');
    if (!modal) {
        console.error('CRM Modal not found');
        return;
    }
    
    let content = document.getElementById('crmModalContent');
    let modalTitle = document.getElementById('crmModalTitle');
    
    // Ensure modal has proper structure
    if (!content) {
        // Create modal body if it doesn't exist
        content = document.createElement('div');
        content.className = 'modal-body';
        content.id = 'crmModalContent';
        
        const modalContent = modal.querySelector('.modal-content') || modal;
        if (!modalContent.querySelector('.modal-body')) {
            modalContent.appendChild(content);
        }
    }
    
    if (!modalTitle) {
        // Create modal title if it doesn't exist
        let header = modal.querySelector('.modal-header');
        if (!header) {
            header = document.createElement('div');
            header.className = 'modal-header';
            const modalContent = modal.querySelector('.modal-content') || modal;
            modalContent.insertBefore(header, modalContent.firstChild);
        }
        modalTitle = document.createElement('h2');
        modalTitle.id = 'crmModalTitle';
        header.insertBefore(modalTitle, header.firstChild);
        
        // Add close button if missing
        if (!header.querySelector('.modal-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'modal-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeCrmModal();
            };
            header.appendChild(closeBtn);
        }
        
        // Update existing close button onclick
        const existingCloseBtn = header.querySelector('.modal-close');
        if (existingCloseBtn) {
            existingCloseBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeCrmModal();
            };
        }
    }
    
    if (modalTitle) {
        modalTitle.textContent = escapeHtml(contact.name);
    }
    
    // Load tags for contact
    await loadTagsForCrm(contact.id, contact);
    
    content.innerHTML = `
        <div class="crm-tabs">
            <button class="crm-tab ${currentCrmTab === 'overview' ? 'active' : ''}" onclick="switchCrmTab('overview')">
                <i class="fas fa-chart-line"></i> Overview
            </button>
            <button class="crm-tab ${currentCrmTab === 'timeline' ? 'active' : ''}" onclick="switchCrmTab('timeline')">
                <i class="fas fa-history"></i> Timeline
            </button>
            <button class="crm-tab ${currentCrmTab === 'tasks' ? 'active' : ''}" onclick="switchCrmTab('tasks')">
                <i class="fas fa-tasks"></i> Tasks
            </button>
            <button class="crm-tab ${currentCrmTab === 'notes' ? 'active' : ''}" onclick="switchCrmTab('notes')">
                <i class="fas fa-sticky-note"></i> Notes
            </button>
            <button class="crm-tab ${currentCrmTab === 'deals' ? 'active' : ''}" onclick="switchCrmTab('deals')">
                <i class="fas fa-dollar-sign"></i> Deals
            </button>
        </div>
        <div class="crm-tab-content" id="crmTabContent">
            <div class="loading">Loading...</div>
        </div>
    `;
    
    // Load initial tab content
    const tabContent = document.getElementById('crmTabContent');
    if (tabContent) {
        tabContent.innerHTML = await renderCrmTab('overview', contact);
    }
    
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Switch CRM Tab
 */
async function switchCrmTab(tab) {
    currentCrmTab = tab;
    
    // Update tab buttons
    document.querySelectorAll('.crm-tab').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.includes(getTabLabel(tab))) {
            btn.classList.add('active');
        }
    });
    
    // Load tab content
    const contactsList = window.allContacts || window.contacts || [];
    const contact = contactsList.find(c => c.id === currentCrmContactId);
    if (!contact) {
        console.error('Contact not found for tab switch');
        return;
    }
    
    const content = document.getElementById('crmTabContent');
    if (content) {
        content.innerHTML = '<div class="loading">Loading...</div>';
        content.innerHTML = await renderCrmTab(tab, contact);
    }
}

function getTabLabel(tab) {
    const labels = {
        'overview': 'Overview',
        'timeline': 'Timeline',
        'tasks': 'Tasks',
        'notes': 'Notes',
        'deals': 'Deals'
    };
    return labels[tab] || tab;
}

/**
 * Render CRM Tab Content
 */
async function renderCrmTab(tab, contact) {
    try {
        switch (tab) {
            case 'overview':
                return renderOverviewTab(contact);
            case 'timeline':
                return await renderTimelineTab(contact);
            case 'tasks':
                return await renderTasksTab(contact);
            case 'notes':
                return await renderNotesTab(contact);
            case 'deals':
                return await renderDealsTab(contact);
            default:
                return '<div>Tab not found</div>';
        }
    } catch (error) {
        console.error('Error rendering tab:', error);
        return '<div class="empty-state"><p>Error loading tab content</p></div>';
    }
}

/**
 * Load tags for CRM (shared function)
 */
async function loadTagsForCrm(contactId, contact) {
    try {
        const response = await fetch('tags.php?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        
        if (response.ok && result.success) {
            window.crmTags = result.tags || [];
            window.crmContactTags = (contact && Array.isArray(contact.tag_ids)) ? contact.tag_ids : [];
        }
    } catch (err) {
        console.error('Error loading tags:', err);
        window.crmTags = [];
        window.crmContactTags = [];
    }
}

/**
 * Overview Tab
 */
function renderOverviewTab(contact) {
    const tags = window.crmTags || [];
    const selectedIds = window.crmContactTags || [];
    
    return `
        <div class="crm-section">
            <div class="crm-section-header">
                <i class="fas fa-check-circle"></i>
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
            <button onclick="updateStage(${contact.id})" class="btn-primary">Update Stage</button>
        </div>
        
        <div class="crm-section">
            <div class="crm-section-header">
                <i class="fas fa-star"></i>
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
                <i class="fas fa-building"></i>
                <h3>Company Information</h3>
            </div>
            <form id="companyInfoForm" class="needs-validation" novalidate>
                <div class="mb-3">
                    <input type="text" id="crmCompany" name="company_name" class="crm-input form-control" placeholder="Company Name" value="${contact.company_name || ''}">
                </div>
                <div class="mb-3">
                    <input type="email" id="crmEmail" name="email" class="crm-input form-control" placeholder="Email" value="${contact.email || ''}">
                </div>
                <div class="mb-3">
                    <input type="text" id="crmCity" name="city" class="crm-input form-control" placeholder="City" value="${contact.city || ''}">
                </div>
                <button type="button" onclick="updateCompanyInfo(${contact.id})" class="btn-primary">Update Info</button>
            </form>
        </div>
        
        <div class="crm-section">
            <div class="crm-section-header">
                <i class="fas fa-tags"></i>
                <h3>Tags</h3>
            </div>
            <div id="crmTagsList" class="tags-list">
                ${tags.length === 0 ? '<div class="empty-state"><p>No tags available</p></div>' : tags.map(tag => {
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
                }).join('')}
            </div>
            <button onclick="saveContactTags(${contact.id})" class="btn-primary">Save Tags</button>
        </div>
    `;
}

/**
 * Save contact tags
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
            if (typeof showToast === 'function') {
                showToast('Tags updated successfully!', 'success');
            }
            if (typeof loadCrmData === 'function') {
                loadCrmData();
            }
            closeCrmModal();
        } else {
            if (typeof showToast === 'function') {
                showToast('Failed to update tags: ' + (result.error || 'Unknown error'), 'error');
            }
        }
    } catch (err) {
        console.error('Error saving tags:', err);
        if (typeof showToast === 'function') {
            showToast('Failed to update tags', 'error');
        }
    }
}

/**
 * Escape HTML helper
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format time helper
 */
function formatTime(timestamp) {
    if (!timestamp) return '';
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

/**
 * Show Add Deal Form
 */
function showAddDealForm(contactId) {
    const form = document.getElementById('addDealForm');
    if (form) {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

/**
 * Hide Add Deal Form
 */
function hideAddDealForm() {
    const form = document.getElementById('addDealForm');
    if (form) {
        form.style.display = 'none';
    }
}

/**
 * Save Deal
 */
async function saveDeal(event, contactId) {
    event.preventDefault();
    
    const dealData = {
        deal_name: document.getElementById('dealName').value,
        amount: parseFloat(document.getElementById('dealAmount').value),
        status: document.getElementById('dealStatus').value,
        deal_date: document.getElementById('dealDate').value,
        notes: document.getElementById('dealNotes').value
    };
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/deal`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(dealData)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            if (typeof showToast === 'function') {
                showToast('Deal added successfully!', 'success');
            }
            hideAddDealForm();
            switchCrmTab('deals');
        } else {
            if (typeof showToast === 'function') {
                showToast('Failed to add deal: ' + (result.error || 'Unknown error'), 'error');
            }
        }
    } catch (error) {
        console.error('Error saving deal:', error);
        if (typeof showToast === 'function') {
            showToast('Failed to add deal', 'error');
        }
    }
}

/**
 * Timeline Tab
 */
async function renderTimelineTab(contact) {
    try {
        const response = await fetch(`api.php/contact-timeline/${contact.id}`);
        const data = await response.json();
        
        if (!data.success || !data.timeline || data.timeline.length === 0) {
            return '<div class="empty-state"><p>No timeline data available</p></div>';
        }
        
        // Group by date
        const grouped = {};
        data.timeline.forEach(item => {
            const date = new Date(item.timestamp).toLocaleDateString();
            if (!grouped[date]) grouped[date] = [];
            grouped[date].push(item);
        });
        
        let html = '<div class="timeline-container">';
        
        Object.keys(grouped).sort((a, b) => new Date(b) - new Date(a)).forEach(date => {
            html += `<div class="timeline-date-group">
                <div class="timeline-date-header">${date}</div>`;
            
            grouped[date].forEach(item => {
                const icon = getTimelineIcon(item.type);
                const time = new Date(item.timestamp).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                
                html += `
                    <div class="timeline-item timeline-${item.type}">
                        <div class="timeline-icon">${icon}</div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <strong>${item.title}</strong>
                                <span class="timeline-time">${time}</span>
                            </div>
                            <div class="timeline-description">${escapeHtml(item.description || '')}</div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    } catch (error) {
        console.error('Error loading timeline:', error);
        return '<div class="empty-state"><p>Error loading timeline</p></div>';
    }
}

/**
 * Tasks Tab
 */
async function renderTasksTab(contact) {
    try {
        const response = await fetch(`api.php/tasks?contact_id=${contact.id}`);
        const data = await response.json();
        
        const tasks = data.tasks || [];
        
        return `
            <div class="crm-section">
                <div class="crm-section-header">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Create Task</h3>
                </div>
                <form id="taskForm" onsubmit="createTask(event, ${contact.id})">
                    <div class="mb-3">
                        <input type="text" id="taskTitle" class="form-control" placeholder="Task title" required>
                    </div>
                    <div class="mb-3">
                        <textarea id="taskDescription" class="form-control" placeholder="Description" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <select id="taskType" class="form-select">
                                <option value="follow_up">Follow Up</option>
                                <option value="call">Call</option>
                                <option value="meeting">Meeting</option>
                                <option value="email">Email</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select id="taskPriority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <input type="datetime-local" id="taskDueDate" class="form-control">
                    </div>
                    <button type="submit" class="btn-primary">Create Task</button>
                </form>
            </div>
            
            <div class="crm-section">
                <div class="crm-section-header">
                    <i class="fas fa-list"></i>
                    <h3>Tasks (${tasks.length})</h3>
                </div>
                <div id="tasksList" class="tasks-list">
                    ${tasks.length === 0 ? '<div class="empty-state"><p>No tasks yet</p></div>' : tasks.map(task => `
                        <div class="task-item task-${task.status} ${task.is_overdue ? 'task-overdue' : ''}">
                            <div class="task-header">
                                <strong>${escapeHtml(task.title)}</strong>
                                <span class="task-priority task-priority-${task.priority}">${task.priority}</span>
                            </div>
                            ${task.description ? `<div class="task-description">${escapeHtml(task.description)}</div>` : ''}
                            <div class="task-meta">
                                <span>${task.type}</span>
                                ${task.due_date ? `<span>Due: ${new Date(task.due_date).toLocaleString()}</span>` : ''}
                                ${task.is_overdue ? '<span class="text-danger">⚠️ Overdue</span>' : ''}
                            </div>
                            <div class="task-actions">
                                ${task.status !== 'completed' ? `<button onclick="completeTask(${task.id})" class="btn-sm btn-success">Complete</button>` : ''}
                                <button onclick="deleteTask(${task.id})" class="btn-sm btn-danger">Delete</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error loading tasks:', error);
        return '<div class="empty-state"><p>Error loading tasks</p></div>';
    }
}

/**
 * Notes Tab
 */
async function renderNotesTab(contact) {
    try {
        const response = await fetch(`crm.php/contact/${contact.id}/notes`);
        const data = await response.json();
        
        const notes = data.notes || [];
        
    return `
        <div class="crm-section">
            <div class="crm-section-header">
                <i class="fas fa-plus-circle"></i>
                <h3>Add Note</h3>
            </div>
            <form id="addNoteForm" onsubmit="addNote(event, ${contact.id})">
                <div class="mb-3">
                    <textarea id="crmNote" class="form-control" placeholder="Type your note here..." rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <select id="crmNoteType" class="form-select">
                        <option value="general">General</option>
                        <option value="call">Call</option>
                        <option value="meeting">Meeting</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Add Note</button>
            </form>
        </div>
        
        <div class="crm-section">
            <div class="crm-section-header">
                <i class="fas fa-sticky-note"></i>
                <h3>Recent Notes (${notes.length})</h3>
            </div>
            <div id="notesList" class="notes-list">
                ${notes.length === 0 ? '<div class="empty-state"><p>No notes yet</p></div>' : notes.map(note => `
                    <div class="note-item note-type-${note.type}">
                        <div class="note-header">
                            <span class="note-type">${note.type}</span>
                            <span class="note-date">${formatTime(note.created_at)}</span>
                        </div>
                        <div class="note-content">${escapeHtml(note.content)}</div>
                        <div class="note-author">by ${note.created_by_name || 'Admin'}</div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    } catch (error) {
        console.error('Error loading notes:', error);
        return '<div class="empty-state"><p>Error loading notes</p></div>';
    }
}

/**
 * Deals Tab
 */
async function renderDealsTab(contact) {
    try {
        const response = await fetch(`crm.php/contact/${contact.id}/deals`);
        const data = await response.json();
        
        const deals = data.deals || [];
        
        return `
            <div class="crm-section">
                <div class="crm-section-header">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>Deal History (${deals.length})</h3>
                </div>
                <div class="deals-list">
                    ${deals.length === 0 ? '<div class="empty-state"><p>No deals yet</p></div>' : deals.map(deal => `
                        <div class="deal-item deal-${deal.status}">
                            <div class="deal-header">
                                <strong>${escapeHtml(deal.deal_name)}</strong>
                                <span class="deal-status">${deal.status}</span>
                            </div>
                            <div class="deal-amount">$${parseFloat(deal.amount || 0).toLocaleString()}</div>
                            <div class="deal-date">${new Date(deal.deal_date).toLocaleDateString()}</div>
                            ${deal.notes ? `<div class="deal-notes">${escapeHtml(deal.notes)}</div>` : ''}
                        </div>
                    `).join('')}
                </div>
                <button onclick="showAddDealForm(${contact.id})" class="btn-primary">+ Add New Deal</button>
            </div>
        `;
    } catch (error) {
        console.error('Error loading deals:', error);
        return '<div class="empty-state"><p>Error loading deals</p></div>';
    }
}

/**
 * Helper Functions
 */
function getTimelineIcon(type) {
    const icons = {
        'message': '<i class="fas fa-comment"></i>',
        'note': '<i class="fas fa-sticky-note"></i>',
        'activity': '<i class="fas fa-history"></i>',
        'task': '<i class="fas fa-tasks"></i>'
    };
    return icons[type] || '<i class="fas fa-circle"></i>';
}

/**
 * Create Task
 */
async function createTask(event, contactId) {
    event.preventDefault();
    
    const taskData = {
        contact_id: contactId,
        title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDescription').value,
        type: document.getElementById('taskType').value,
        priority: document.getElementById('taskPriority').value,
        due_date: document.getElementById('taskDueDate').value
    };
    
    try {
        const response = await fetch('api.php/tasks', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(taskData)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Task created!', 'success');
            switchCrmTab('tasks');
        } else {
            showToast('Failed to create task', 'error');
        }
    } catch (error) {
        console.error('Error creating task:', error);
        showToast('Failed to create task', 'error');
    }
}

/**
 * Complete Task
 */
async function completeTask(taskId) {
    try {
        const response = await fetch(`api.php/tasks/${taskId}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ status: 'completed' })
        });
        
        if (response.ok) {
            showToast('Task completed!', 'success');
            switchCrmTab('tasks');
        }
    } catch (error) {
        console.error('Error completing task:', error);
    }
}

/**
 * Delete Task
 */
async function deleteTask(taskId) {
    if (!confirm('Delete this task?')) return;
    
    try {
        const response = await fetch(`api.php/tasks/${taskId}`, {
            method: 'DELETE'
        });
        
        if (response.ok) {
            showToast('Task deleted!', 'success');
            switchCrmTab('tasks');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
    }
}

/**
 * Add Note (enhanced)
 */
async function addNote(event, contactId) {
    event.preventDefault();
    
    const noteData = {
        content: document.getElementById('crmNote').value,
        type: document.getElementById('crmNoteType').value
    };
    
    try {
        const response = await fetch(`crm.php/contact/${contactId}/note`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(noteData)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Note added!', 'success');
            document.getElementById('crmNote').value = '';
            switchCrmTab('notes');
        } else {
            showToast('Failed to add note', 'error');
        }
    } catch (error) {
        console.error('Error adding note:', error);
        showToast('Failed to add note', 'error');
    }
}

