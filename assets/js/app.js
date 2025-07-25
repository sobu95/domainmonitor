// Domain Monitor JavaScript

// Determine the directory of this script and the application base path
const scriptDirectory = new URL('.', document.currentScript.src).href;
const BASE_PATH = new URL('../..', scriptDirectory).href;

function initApp() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Toggle favorite domains
    document.addEventListener('click', function(e) {
        if (e.target.closest('.toggle-favorite')) {
            e.preventDefault();
            const button = e.target.closest('.toggle-favorite');
            const domainId = button.dataset.domainId;

            toggleFavorite(domainId, button);
        }
    });

    // Refresh Moz metrics
    document.addEventListener('click', function(e) {
        if (e.target.closest('#refreshMozBtn')) {
            e.preventDefault();
            const btn = e.target.closest('#refreshMozBtn');
            const domainId = btn.dataset.domainId;
            refreshMozMetrics(domainId, btn);
        }
    });

    // Search functionality
    const searchInput = document.getElementById('domainSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.domain-row');
            
            rows.forEach(row => {
                const domainName = row.querySelector('.domain-name').textContent.toLowerCase();
                const description = row.querySelector('.domain-description')?.textContent.toLowerCase() || '';
                
                if (domainName.includes(searchTerm) || description.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Category filter
    document.addEventListener('click', function(e) {
        if (e.target.closest('.category-filter')) {
            e.preventDefault();
            const categoryId = e.target.closest('.category-filter').dataset.categoryId;
            filterByCategory(categoryId);
        }
    });

    // Auto-refresh dashboard stats every 5 minutes
    if (document.querySelector('.dashboard-stats')) {
        setInterval(refreshDashboardStats, 300000);
    }

    // Initialize date pickers
    const datePickers = document.querySelectorAll('.date-picker');
    datePickers.forEach(picker => {
        // You can integrate with a date picker library here
    });

    // Confirm delete actions
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-action')) {
            if (!confirm('Czy na pewno chcesz usunąć ten element?')) {
                e.preventDefault();
            }
        }
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

function toggleFavorite(domainId, button) {
    const icon = button.querySelector('i');
    const originalClass = icon.className;
    
    // Show loading state
    icon.className = 'fas fa-spinner fa-spin';
    button.disabled = true;
    
    fetch(`${BASE_PATH}ajax/toggle_favorite.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ domain_id: domainId })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                const msg = text || `Błąd ${response.status}`;
                throw new Error(msg);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.is_favorite) {
                icon.className = 'fas fa-heart';
                button.classList.add('active');
                button.title = 'Usuń z ulubionych';
            } else {
                icon.className = 'far fa-heart';
                button.classList.remove('active');
                button.title = 'Dodaj do ulubionych';
            }
            
            // Show success message
            showNotification('Zaktualizowano ulubione', 'success');
        } else {
            // Restore original state
            icon.className = originalClass;
            showNotification('Błąd podczas aktualizacji', 'error');
        }
    })
    .catch(error => {
        // Restore original state
        icon.className = originalClass;
        if (error && error.message && error.message !== 'Failed to fetch') {
            showNotification(error.message, 'error');
        } else {
            showNotification('Błąd połączenia', 'error');
        }
    })
    .finally(() => {
        button.disabled = false;
    });
}

function filterByCategory(categoryId) {
    const rows = document.querySelectorAll('.domain-row');
    const filters = document.querySelectorAll('.category-filter');
    
    // Update active filter
    filters.forEach(filter => {
        filter.classList.remove('active');
    });
    
    if (categoryId) {
        document.querySelector(`[data-category-id="${categoryId}"]`).classList.add('active');
    } else {
        document.querySelector('[data-category-id=""]').classList.add('active');
    }
    
    // Filter rows
    rows.forEach(row => {
        if (!categoryId) {
            row.style.display = '';
        } else {
            const rowCategories = row.dataset.categories ? row.dataset.categories.split(',') : [];
            if (rowCategories.includes(categoryId)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

function refreshDashboardStats() {
    fetch(`${BASE_PATH}ajax/dashboard_stats.php`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update stats
            document.querySelector('.today-domains').textContent = data.stats.today_domains;
            document.querySelector('.interesting-domains').textContent = data.stats.interesting_domains;
            document.querySelector('.favorite-domains').textContent = data.stats.favorite_domains;
        }
    })
    .catch(error => {
        console.error('Error refreshing stats:', error);
    });
}

function refreshMozMetrics(domainId, button) {
    const icon = button.querySelector('i');
    const originalClass = icon.className;

    icon.className = 'fas fa-spinner fa-spin';
    button.disabled = true;

    fetch(`${BASE_PATH}ajax/refresh_moz.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ domain_id: domainId })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text || `Błąd ${response.status}`); });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const m = data.metrics || {};
            document.getElementById('moz-da').textContent = m.domain_authority ?? '-';
            document.getElementById('moz-pa').textContent = m.page_authority ?? '-';
            document.getElementById('moz-links').textContent = m.linking_domains ?? '-';

            const wrapper = document.getElementById('moz-linking-wrapper');
            const tbody = document.getElementById('moz-linking-tbody');
            tbody.innerHTML = '';
            if (m.linking_domains_list) {
                m.linking_domains_list.split('\n').forEach(ld => {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.textContent = ld;
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                });
                wrapper.style.display = '';
            } else {
                wrapper.style.display = 'none';
            }
            showNotification('Zaktualizowano dane Moz', 'success');
        } else {
            showNotification(data.error || 'Błąd podczas aktualizacji', 'error');
        }
    })
    .catch(err => {
        showNotification(err.message || 'Błąd połączenia', 'error');
    })
    .finally(() => {
        icon.className = originalClass;
        button.disabled = false;
    });
}

function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 
                     type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

// Export functions for global use
window.toggleFavorite = toggleFavorite;
window.filterByCategory = filterByCategory;
window.showNotification = showNotification;
window.refreshMozMetrics = refreshMozMetrics;
