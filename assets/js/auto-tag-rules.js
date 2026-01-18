/**
 * Auto-Tag Rules Management JavaScript
 */

let rules = [];
let tags = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadTags();
    loadRules();
    
    // Search functionality
    document.getElementById('searchRules').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        filterRules(query);
    });
});

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
 * Load tags for dropdown
 */
async function loadTags() {
    try {
        const response = await fetch('api.php/tags');
        if (!response.ok) throw new Error('Failed to load tags');
        
        tags = await response.json();
        
        // Populate dropdown
        const select = document.getElementById('ruleTagId');
        select.innerHTML = '<option value="">Select a tag...</option>' + 
            tags.map(tag => `<option value="${tag.id}">${tag.name}</option>`).join('');
    } catch (error) {
        console.error('Error loading tags:', error);
        showToast('Failed to load tags', 'error');
    }
}

/**
 * Load all auto-tag rules
 */
async function loadRules() {
    try {
        const response = await fetch('api.php/auto-tag-rules');
        if (!response.ok) throw new Error('Failed to load rules');
        
        rules = await response.json();
        renderRules(rules);
    } catch (error) {
        console.error('Error loading rules:', error);
        document.getElementById('rulesList').innerHTML = 
            '<div class="empty-state"><p>Failed to load rules</p></div>';
    }
}

/**
 * Render rules list
 */
function renderRules(rulesList) {
    const container = document.getElementById('rulesList');
    
    if (rulesList.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-robot" style="font-size: 48px; color: var(--text-muted);"></i>
                <p>No auto-tag rules yet</p>
                <p style="font-size: 14px; color: var(--text-muted);">Create rules to automatically tag contacts based on keywords</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rule Name</th>
                        <th>Tag</th>
                        <th>Keywords</th>
                        <th>Match Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${rulesList.map(rule => `
                        <tr>
                            <td><strong>${escapeHtml(rule.rule_name)}</strong></td>
                            <td>
                                <span class="tag-badge" style="background-color: ${rule.tag_color || '#3b82f6'};">
                                    ${escapeHtml(rule.tag_name || 'Unknown')}
                                </span>
                            </td>
                            <td>
                                <div style="max-width: 300px; font-size: 13px; color: var(--text-secondary);">
                                    ${formatKeywords(rule.keywords)}
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-${rule.match_type === 'all' ? 'info' : rule.match_type === 'exact' ? 'warning' : 'secondary'}">
                                    ${rule.match_type.toUpperCase()}
                                </span>
                            </td>
                            <td><span class="priority-badge">${rule.priority}</span></td>
                            <td>
                                <span class="badge badge-${rule.enabled ? 'success' : 'secondary'}">
                                    ${rule.enabled ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="editRule(${rule.id})" class="btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleRule(${rule.id}, ${!rule.enabled})" class="btn-icon" title="${rule.enabled ? 'Disable' : 'Enable'}">
                                        <i class="fas fa-${rule.enabled ? 'pause' : 'play'}"></i>
                                    </button>
                                    <button onclick="deleteRule(${rule.id})" class="btn-icon btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Format keywords for display
 */
function formatKeywords(keywords) {
    if (!keywords || keywords.length === 0) return '<em>No keywords</em>';
    
    const keywordArray = Array.isArray(keywords) ? keywords : JSON.parse(keywords || '[]');
    const displayed = keywordArray.slice(0, 3);
    const remaining = keywordArray.length - displayed.length;
    
    return displayed.map(k => `<span class="keyword-chip">${escapeHtml(k)}</span>`).join(' ') +
        (remaining > 0 ? ` <span style="color: var(--text-muted);">+${remaining} more</span>` : '');
}

/**
 * Filter rules by search query
 */
function filterRules(query) {
    if (!query) {
        renderRules(rules);
        return;
    }
    
    const filtered = rules.filter(rule => 
        rule.rule_name.toLowerCase().includes(query) ||
        rule.tag_name.toLowerCase().includes(query) ||
        (Array.isArray(rule.keywords) && rule.keywords.some(k => k.toLowerCase().includes(query)))
    );
    
    renderRules(filtered);
}

/**
 * Open modal to create new rule
 */
function openRuleModal() {
    document.getElementById('ruleModalTitle').textContent = 'Add Auto-Tag Rule';
    document.getElementById('ruleId').value = '';
    document.getElementById('ruleName').value = '';
    document.getElementById('ruleTagId').value = '';
    document.getElementById('ruleKeywords').value = '';
    document.getElementById('ruleMatchType').value = 'any';
    document.getElementById('rulePriority').value = '1';
    document.getElementById('ruleEnabled').checked = true;
    document.getElementById('ruleModal').style.display = 'flex';
}

/**
 * Edit existing rule
 */
function editRule(ruleId) {
    const rule = rules.find(r => r.id === ruleId);
    if (!rule) return;
    
    document.getElementById('ruleModalTitle').textContent = 'Edit Auto-Tag Rule';
    document.getElementById('ruleId').value = rule.id;
    document.getElementById('ruleName').value = rule.rule_name;
    document.getElementById('ruleTagId').value = rule.tag_id;
    
    // Handle keywords - could be array or JSON string
    const keywords = Array.isArray(rule.keywords) ? rule.keywords : JSON.parse(rule.keywords || '[]');
    document.getElementById('ruleKeywords').value = keywords.join('\n');
    
    document.getElementById('ruleMatchType').value = rule.match_type;
    document.getElementById('rulePriority').value = rule.priority;
    document.getElementById('ruleEnabled').checked = rule.enabled;
    document.getElementById('ruleModal').style.display = 'flex';
}

/**
 * Close rule modal
 */
function closeRuleModal() {
    document.getElementById('ruleModal').style.display = 'none';
}

/**
 * Save rule (create or update)
 */
async function saveRule() {
    const ruleId = document.getElementById('ruleId').value;
    const ruleName = document.getElementById('ruleName').value.trim();
    const tagId = document.getElementById('ruleTagId').value;
    const keywordsText = document.getElementById('ruleKeywords').value.trim();
    const matchType = document.getElementById('ruleMatchType').value;
    const priority = parseInt(document.getElementById('rulePriority').value);
    const enabled = document.getElementById('ruleEnabled').checked;
    
    // Validation
    if (!ruleName) {
        showToast('Please enter a rule name', 'error');
        return;
    }
    
    if (!tagId) {
        showToast('Please select a tag', 'error');
        return;
    }
    
    if (!keywordsText) {
        showToast('Please enter at least one keyword', 'error');
        return;
    }
    
    // Parse keywords
    const keywords = keywordsText.split('\n')
        .map(k => k.trim())
        .filter(k => k.length > 0);
    
    if (keywords.length === 0) {
        showToast('Please enter at least one valid keyword', 'error');
        return;
    }
    
    const data = {
        rule_name: ruleName,
        tag_id: tagId,
        keywords: JSON.stringify(keywords),
        match_type: matchType,
        priority: priority,
        enabled: enabled
    };
    
    try {
        const url = ruleId ? `api.php/auto-tag-rules/${ruleId}` : 'api.php/auto-tag-rules';
        const method = ruleId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast(ruleId ? 'Rule updated successfully!' : 'Rule created successfully!', 'success');
            closeRuleModal();
            loadRules();
        } else {
            showToast('Failed to save rule: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error saving rule:', error);
        showToast('Failed to save rule', 'error');
    }
}

/**
 * Toggle rule enabled/disabled
 */
async function toggleRule(ruleId, enabled) {
    try {
        const response = await fetch(`api.php/auto-tag-rules/${ruleId}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ enabled: enabled })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast(`Rule ${enabled ? 'enabled' : 'disabled'} successfully!`, 'success');
            loadRules();
        } else {
            showToast('Failed to update rule', 'error');
        }
    } catch (error) {
        console.error('Error toggling rule:', error);
        showToast('Failed to update rule', 'error');
    }
}

/**
 * Delete rule
 */
async function deleteRule(ruleId) {
    if (!confirm('Are you sure you want to delete this auto-tag rule?')) {
        return;
    }
    
    try {
        const response = await fetch(`api.php/auto-tag-rules/${ruleId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showToast('Rule deleted successfully!', 'success');
            loadRules();
        } else {
            showToast('Failed to delete rule', 'error');
        }
    } catch (error) {
        console.error('Error deleting rule:', error);
        showToast('Failed to delete rule', 'error');
    }
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modals should NOT close when clicking outside - removed backdrop click handler
