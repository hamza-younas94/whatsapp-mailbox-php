/**
 * Advanced Search JavaScript
 */

let allTags = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadTags();
    
    // Score slider
    document.getElementById('filterMinScore').addEventListener('input', function(e) {
        document.getElementById('scoreDisplay').textContent = e.target.value;
    });
    
    // Allow Enter key to search
    document.getElementById('searchQuery').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    // Set today's date as default for "To Date"
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('filterToDate').value = today;
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
 * Load available tags
 */
async function loadTags() {
    try {
        const response = await fetch('api.php/tags');
        if (response.ok) {
            allTags = await response.json();
            const select = document.getElementById('filterTags');
            select.innerHTML = '<option value="">All Tags</option>' +
                allTags.map(tag => `<option value="${tag.id}">${tag.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading tags:', error);
    }
}

/**
 * Perform search
 */
async function performSearch() {
    const query = document.getElementById('searchQuery').value.trim();
    const stage = document.getElementById('filterStage').value;
    const tags = Array.from(document.getElementById('filterTags').selectedOptions).map(o => o.value).filter(v => v);
    const messageType = document.getElementById('filterMessageType').value;
    const fromDate = document.getElementById('filterFromDate').value;
    const toDate = document.getElementById('filterToDate').value;
    const direction = document.getElementById('filterDirection').value;
    const minScore = parseInt(document.getElementById('filterMinScore').value);
    
    if (!query) {
        showToast('Please enter a search query', 'error');
        return;
    }
    
    try {
        const params = new URLSearchParams({
            q: query,
            stage: stage,
            tags: tags.join(','),
            message_type: messageType,
            from_date: fromDate,
            to_date: toDate,
            direction: direction,
            min_score: minScore
        });
        
        const response = await fetch(`api.php/search?${params}`);
        if (!response.ok) throw new Error('Search failed');
        
        const results = await response.json();
        renderResults(results);
    } catch (error) {
        console.error('Error performing search:', error);
        showToast('Search failed', 'error');
    }
}

/**
 * Render search results
 */
function renderResults(results) {
    const resultsSection = document.getElementById('resultsSection');
    const noResults = document.getElementById('noResults');
    const searchResults = document.getElementById('searchResults');
    const resultCount = document.getElementById('resultCount');
    
    if (!results || results.length === 0) {
        resultsSection.style.display = 'none';
        noResults.style.display = 'flex';
        return;
    }
    
    resultsSection.style.display = 'block';
    noResults.style.display = 'none';
    resultCount.textContent = `(${results.length} result${results.length !== 1 ? 's' : ''})`;
    
    searchResults.innerHTML = `
        <div class="search-results-list">
            ${results.map((result, index) => `
                <div class="search-result-item">
                    <div class="result-number">${index + 1}</div>
                    <div class="result-content">
                        <div class="result-header">
                            <strong>${escapeHtml(result.contact_name)}</strong>
                            <span class="result-phone text-muted">${result.phone_number}</span>
                            ${result.stage ? `<span class="stage-badge stage-${result.stage}">${result.stage.toUpperCase()}</span>` : ''}
                            ${result.lead_score !== null ? `<span class="score-badge">${result.lead_score}/100</span>` : ''}
                        </div>
                        <div class="result-message">
                            ${highlightSearchTerm(escapeHtml(result.message_body), document.getElementById('searchQuery').value)}
                        </div>
                        <div class="result-meta">
                            <span class="meta-item">
                                <i class="fas fa-clock"></i> ${formatTime(result.timestamp)}
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-exchange-alt"></i> ${result.direction === 'incoming' ? 'Incoming' : 'Outgoing'}
                            </span>
                            ${result.message_type !== 'text' ? `<span class="meta-item"><i class="fas fa-file"></i> ${result.message_type.toUpperCase()}</span>` : ''}
                            ${result.tags && result.tags.length > 0 ? `
                                <span class="meta-item tags">
                                    ${result.tags.map(tag => `<span class="tag-mini" style="background-color: ${tag.color || '#3b82f6'};">${escapeHtml(tag.name)}</span>`).join('')}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                    <div class="result-actions">
                        <button onclick="viewMessageDetail(${result.id})" class="btn-action" title="View Details">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <a href="index.php?contact=${result.contact_id}" class="btn-action" title="View Conversation">
                            <i class="fas fa-comments"></i>
                        </a>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

/**
 * Highlight search term in results
 */
function highlightSearchTerm(text, term) {
    if (!term) return text;
    
    const regex = new RegExp(`(${term})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

/**
 * View message detail
 */
async function viewMessageDetail(messageId) {
    try {
        const response = await fetch(`api.php/messages/${messageId}`);
        if (!response.ok) throw new Error('Failed to load message');
        
        const message = await response.json();
        const content = document.getElementById('messageDetailContent');
        
        content.innerHTML = `
            <div class="message-detail">
                <div class="detail-group">
                    <label>From</label>
                    <p>${escapeHtml(message.contact_name)}</p>
                </div>
                <div class="detail-group">
                    <label>Phone Number</label>
                    <p>${message.phone_number}</p>
                </div>
                <div class="detail-group">
                    <label>Date</label>
                    <p>${formatDateTime(message.timestamp)}</p>
                </div>
                <div class="detail-group">
                    <label>Direction</label>
                    <p>${message.direction === 'incoming' ? 'Incoming' : 'Outgoing'}</p>
                </div>
                ${message.message_type !== 'text' ? `
                    <div class="detail-group">
                        <label>Type</label>
                        <p>${message.message_type.toUpperCase()}</p>
                    </div>
                ` : ''}
                <div class="detail-group">
                    <label>Message</label>
                    <p style="line-height: 1.6; word-wrap: break-word;">${escapeHtml(message.message_body)}</p>
                </div>
                ${message.tags && message.tags.length > 0 ? `
                    <div class="detail-group">
                        <label>Tags</label>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            ${message.tags.map(tag => `<span class="tag-badge" style="background-color: ${tag.color || '#3b82f6'};">${escapeHtml(tag.name)}</span>`).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
        
        document.getElementById('messageDetailModal').style.display = 'flex';
    } catch (error) {
        console.error('Error loading message detail:', error);
        showToast('Failed to load message', 'error');
    }
}

/**
 * Close detail modal
 */
function closeDetailModal() {
    document.getElementById('messageDetailModal').style.display = 'none';
}

/**
 * Reset all filters
 */
function resetFilters() {
    document.getElementById('searchQuery').value = '';
    document.getElementById('filterStage').value = '';
    document.getElementById('filterTags').value = '';
    document.getElementById('filterMessageType').value = '';
    document.getElementById('filterFromDate').value = '';
    document.getElementById('filterDirection').value = '';
    document.getElementById('filterMinScore').value = '0';
    document.getElementById('scoreDisplay').textContent = '0';
    
    document.getElementById('resultsSection').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';
    document.getElementById('searchQuery').focus();
}

/**
 * Format time
 */
function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format date time
 */
function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
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
