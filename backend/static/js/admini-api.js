/**
 * Admini Control Panel - Frontend API Integration
 * Provides JavaScript functions for backend API communication
 */

class AdminiAPI {
    constructor() {
        this.baseURL = window.location.origin;
        this.csrfToken = this.getCSRFToken();
    }

    /**
     * Get CSRF token from meta tag or cookie
     */
    getCSRFToken() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }
        // Fallback to cookie
        return this.getCookie('csrf_token');
    }

    /**
     * Get cookie value by name
     */
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Make API request with proper headers
     */
    async request(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (this.csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = this.csrfToken;
        }

        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    /**
     * GET request
     */
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    // Admin API methods
    async getUsers() {
        return this.get('/api/users');
    }

    async createUser(userData) {
        return this.post('/api/users', userData);
    }

    async updateUser(username, userData) {
        return this.put(`/api/users/${username}`, userData);
    }

    async deleteUser(username) {
        return this.delete(`/api/users/${username}`);
    }

    async suspendUser(username) {
        return this.post(`/api/users/${username}/suspend`);
    }

    async unsuspendUser(username) {
        return this.post(`/api/users/${username}/unsuspend`);
    }

    // Domain API methods
    async getDomains() {
        return this.get('/api/domains');
    }

    async createDomain(domainData) {
        return this.post('/api/domains', domainData);
    }

    async suspendDomain(domain) {
        return this.post(`/api/domains/${domain}/suspend`);
    }

    async unsuspendDomain(domain) {
        return this.post(`/api/domains/${domain}/unsuspend`);
    }

    // System API methods
    async getSystemStats() {
        return this.get('/api/system/stats');
    }

    async getSystemInfo() {
        return this.get('/api/system/info');
    }

    // AJAX helper methods
    async checkUsername(username) {
        return this.get(`/ajax/check-username?username=${encodeURIComponent(username)}`);
    }

    async checkDomain(domain) {
        return this.get(`/ajax/check-domain?domain=${encodeURIComponent(domain)}`);
    }

    async search(query, type = 'all') {
        return this.get(`/ajax/search?q=${encodeURIComponent(query)}&type=${type}`);
    }

    async getCounts() {
        return this.get('/ajax/counts');
    }
}

/**
 * UI Helper functions
 */
class AdminiUI {
    constructor(api) {
        this.api = api;
        this.initializeEventListeners();
    }

    /**
     * Initialize global event listeners
     */
    initializeEventListeners() {
        // Auto-save forms
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('auto-save')) {
                this.autoSaveField(e.target);
            }
        });

        // AJAX form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                this.submitAjaxForm(e.target);
            }
        });

        // Real-time username validation
        document.addEventListener('input', (e) => {
            if (e.target.name === 'username') {
                this.validateUsername(e.target);
            }
        });
    }

    /**
     * Auto-save form field
     */
    async autoSaveField(field) {
        try {
            const formData = new FormData(field.form);
            await this.api.post('/api/config/update', Object.fromEntries(formData));
            this.showNotification('Settings saved automatically', 'success');
        } catch (error) {
            this.showNotification('Failed to save settings', 'error');
        }
    }

    /**
     * Submit AJAX form
     */
    async submitAjaxForm(form) {
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn.textContent;
        
        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Loading...';
            
            const formData = new FormData(form);
            const endpoint = form.getAttribute('action') || form.dataset.endpoint;
            
            const result = await this.api.post(endpoint, Object.fromEntries(formData));
            
            this.showNotification(result.message || 'Operation completed successfully', 'success');
            
            // Reset form if needed
            if (form.dataset.resetOnSuccess === 'true') {
                form.reset();
            }
            
        } catch (error) {
            this.showNotification(error.message || 'Operation failed', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    /**
     * Validate username in real-time
     */
    async validateUsername(input) {
        const username = input.value;
        if (username.length < 3) return;

        try {
            const result = await this.api.checkUsername(username);
            this.showFieldValidation(input, result.available, result.message);
        } catch (error) {
            console.error('Username validation failed:', error);
        }
    }

    /**
     * Show field validation message
     */
    showFieldValidation(field, isValid, message) {
        let validationMsg = field.parentNode.querySelector('.validation-message');
        
        if (!validationMsg) {
            validationMsg = document.createElement('div');
            validationMsg.className = 'validation-message';
            field.parentNode.appendChild(validationMsg);
        }
        
        validationMsg.textContent = message;
        validationMsg.className = `validation-message ${isValid ? 'valid' : 'invalid'}`;
        field.classList.toggle('field-valid', isValid);
        field.classList.toggle('field-invalid', !isValid);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `admini-notification admini-notification-${type}`;
        notification.textContent = message;
        
        // Remove existing notifications
        document.querySelectorAll('.admini-notification').forEach(n => n.remove());
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * Update dashboard stats
     */
    async updateDashboardStats() {
        try {
            const stats = await this.api.getCounts();
            
            Object.entries(stats).forEach(([key, value]) => {
                const element = document.querySelector(`[data-stat="${key}"]`);
                if (element) {
                    element.textContent = value;
                }
            });
        } catch (error) {
            console.error('Failed to update dashboard stats:', error);
        }
    }

    /**
     * Load users table
     */
    async loadUsersTable() {
        try {
            const users = await this.api.getUsers();
            const tableBody = document.querySelector('#users-table tbody');
            
            if (tableBody) {
                tableBody.innerHTML = users.map(user => `
                    <tr>
                        <td>${user.username}</td>
                        <td>${user.domain}</td>
                        <td>${user.package}</td>
                        <td><span class="admini-status admini-status-${user.status}">${user.status}</span></td>
                        <td>
                            <button onclick="adminiUI.suspendUser('${user.username}')" class="admini-btn admini-btn-sm">Suspend</button>
                            <button onclick="adminiUI.editUser('${user.username}')" class="admini-btn admini-btn-sm">Edit</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Failed to load users:', error);
        }
    }

    /**
     * Suspend user
     */
    async suspendUser(username) {
        if (!confirm(`Are you sure you want to suspend user ${username}?`)) return;
        
        try {
            await this.api.suspendUser(username);
            this.showNotification(`User ${username} suspended successfully`, 'success');
            this.loadUsersTable(); // Refresh table
        } catch (error) {
            this.showNotification(`Failed to suspend user: ${error.message}`, 'error');
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.adminiAPI = new AdminiAPI();
    window.adminiUI = new AdminiUI(window.adminiAPI);
    
    // Update stats on dashboard
    if (document.querySelector('.admini-dashboard')) {
        window.adminiUI.updateDashboardStats();
    }
    
    // Load users table if present
    if (document.querySelector('#users-table')) {
        window.adminiUI.loadUsersTable();
    }
});