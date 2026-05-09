// ============================================
// API INTEGRATION LAYER
// ============================================
// Provides API calls to backend endpoints
// Used by database.js for server synchronization
// ============================================

class ApiIntegration {
    constructor() {
        this.baseUrl = '';
        this.isOnline = true;
        this.availableEndpoints = new Set();
        this.checkedEndpoints = new Set();
    }

    // Check if endpoint exists before calling
    async endpointExists(endpoint) {
        if (this.checkedEndpoints.has(endpoint)) {
            return this.availableEndpoints.has(endpoint);
        }

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                method: 'HEAD',
                credentials: 'include'
            });
            const exists = response.status !== 404;
            this.checkedEndpoints.add(endpoint);
            if (exists) {
                this.availableEndpoints.add(endpoint);
            }
            return exists;
        } catch (error) {
            this.checkedEndpoints.add(endpoint);
            return false;
        }
    }

    // Generic API call method
    async call(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include' // Include session cookies
        };

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            
            if (response.status === 401) {
                // Session expired
                window.location.href = 'index.html';
                throw new Error('Session expired');
            }

            if (!response.ok) {
                // Try to parse error response
                let errorMsg = `API Error: ${response.status}`;
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.error || errorMsg;
                } catch (e) {
                    // Response wasn't JSON
                }
                throw new Error(errorMsg);
            }

            return await response.json();
        } catch (error) {
            console.warn(`API call failed (${endpoint}):`, error.message);
            this.isOnline = false;
            throw error;
        }
    }

    // ============================================
    // EMPLOYEE ENDPOINTS
    // ============================================
    async getEmployees() {
        return this.call('/api/employees');
    }

    async getEmployee(id) {
        return this.call(`/api/employee?id=${id}`);
    }

    async createEmployee(data) {
        return this.call('/api/employees', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async updateEmployee(id, data) {
        return this.call('/api/employees', {
            method: 'PUT',
            body: JSON.stringify({ ...data, id })
        });
    }

    async deleteEmployee(id) {
        return this.call(`/api/employees?id=${id}`, {
            method: 'DELETE'
        });
    }

    // ============================================
    // PAYROLL ENDPOINTS
    // ============================================
    async getPayroll() {
        return this.call('/api/payroll');
    }

    async getPayrollById(id) {
        return this.call(`/api/payroll?id=${id}`);
    }

    async createPayroll(data) {
        return this.call('/api/payroll', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async updatePayroll(id, data) {
        return this.call('/api/payroll', {
            method: 'PUT',
            body: JSON.stringify({ ...data, id })
        });
    }

    async deletePayroll(id) {
        return this.call(`/api/payroll?id=${id}`, {
            method: 'DELETE'
        });
    }

    async generatePayrollFromAttendance(periodStart, periodEnd, periodDisplay) {
        return this.call('/api/payroll', {
            method: 'POST',
            body: JSON.stringify({
                action: 'generate_from_attendance',
                period_start: periodStart,
                period_end: periodEnd,
                period_display: periodDisplay
            })
        });
    }

    // ============================================
    // ATTENDANCE ENDPOINTS
    // ============================================
    async getAttendance(type = 'admin-master') {
        return this.call(`/api/attendance/${type}`);
    }

    async createAttendance(type, data) {
        return this.call(`/api/attendance/${type}`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async updateAttendance(type, id, data) {
        return this.call(`/api/attendance/${type}`, {
            method: 'PUT',
            body: JSON.stringify({ ...data, id })
        });
    }

    async deleteAttendance(type, id) {
        return this.call(`/api/attendance/${type}?id=${id}`, {
            method: 'DELETE'
        });
    }

    // ============================================
    // USER ENDPOINTS
    // ============================================
    async getUsers() {
        return this.call('/api/users');
    }

    async createUser(data) {
        return this.call('/api/users', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async updateUser(id, data) {
        return this.call('/api/users', {
            method: 'PUT',
            body: JSON.stringify({ ...data, id })
        });
    }

    async deleteUser(id) {
        return this.call(`/api/users?id=${id}`, {
            method: 'DELETE'
        });
    }

    // ============================================
    // SETTINGS ENDPOINTS
    // ============================================
    async getSettings() {
        return this.call('/api/settings');
    }

    async getSetting(key) {
        return this.call(`/api/settings?key=${key}`);
    }

    async updateSettings(data) {
        return this.call('/api/settings', {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
}

// Create global singleton
window.ApiIntegration = window.ApiIntegration || new ApiIntegration();
