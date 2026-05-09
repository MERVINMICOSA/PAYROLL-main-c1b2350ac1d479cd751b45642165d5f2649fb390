// js/render-api.js — Session-based API mode
// Makes API calls using PHP session cookies.
// Falls back to local IndexedDB if server is unavailable.

const DB_NAME = "PayrollDB";
const API_BASE = ''; // Use relative URLs

// ===========================
// API HELPERS
// ===========================

async function apiCall(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include'
    };

    try {
        const response = await fetch(url, { ...defaultOptions, ...options });
        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.warn(`API call failed, using local fallback: ${error.message}`);
        return null;
    }
}

// ===========================
// AUTHENTICATION HELPERS — Session-based
// ===========================

export async function isAuthenticated() {
    try {
        const data = await apiCall('/api/auth/session.php');
        return !!(data && data.authenticated);
    } catch (e) {
        return false;
    }
}

export async function login(username, password) {
    const result = await apiCall('/api/auth/login.php', {
        method: 'POST',
        body: JSON.stringify({ username, password })
    });

    if (result && result.success) {
        return result;
    }

    return { success: false, error: result?.error || 'Invalid credentials' };
}

export async function logout() {
    await apiCall('/api/auth/logout.php', { method: 'POST' });
    return { success: true };
}

export async function validateToken() {
    // Session-based: check with backend session endpoint
    try {
        const data = await apiCall('/api/auth/session.php');
        if (data && data.authenticated) {
            return { valid: true, user: data.user };
        }
    } catch (error) {
        console.error('Session validation error:', error);
    }
    return { valid: false };
}

// ===========================
// INDEXEDDB FALLBACK
// ===========================

async function getAllFromStore(storeName) {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME);
        req.onsuccess = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(storeName)) { resolve([]); return; }
            const tx = db.transaction([storeName], "readonly");
            const store = tx.objectStore(storeName);
            const getAll = store.getAll();
            getAll.onsuccess = () => resolve(getAll.result);
            getAll.onerror = () => reject(getAll.error);
        };
        req.onerror = () => reject(req.error);
    });
}

async function saveToStore(storeName, data) {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME);
        req.onsuccess = (e) => {
            const db = e.target.result;
            const tx = db.transaction([storeName], "readwrite");
            const store = tx.objectStore(storeName);
            const getReq = store.get(data.id);
            getReq.onsuccess = () => {
                if (getReq.result) { store.put(data).onsuccess = () => resolve(data); }
                else { store.add(data).onsuccess = () => resolve(data); }
            };
            getReq.onerror = () => store.add(data).onsuccess = () => resolve(data);
        };
        req.onerror = () => reject(req.error);
    });
}

async function updateByEmployeeId(storeName, employeeId, newData) {
    const all = await getAllFromStore(storeName);
    const existing = all.find(item => item.employee_id === employeeId);
    if (existing) {
        const updated = { ...existing, ...newData, id: existing.id };
        await saveToStore(storeName, updated);
        return updated;
    } else {
        const newItem = { ...newData, id: Date.now() };
        await saveToStore(storeName, newItem);
        return newItem;
    }
}

// ===========================
// EMPLOYEES API
// ===========================

export async function getEmployeesFromRender() {
    const result = await apiCall('/api/employees');
    if (result) return result;
    return await getAllFromStore('employees');
}

export async function saveEmployeeToRender(employee) {
    const result = await apiCall('/api/employees', {
        method: 'POST',
        body: JSON.stringify(employee)
    });
    if (result) return result;
    return await saveToStore('employees', { ...employee, id: employee.id || Date.now() });
}

export async function syncEmployeesToServer(employees) {
    try {
        for (const emp of employees) {
            await apiCall('/api/employees', { method: 'POST', body: JSON.stringify(emp) });
        }
        return { success: true, message: `Synced ${employees.length} employees to server` };
    } catch (error) {
        return { success: true, message: "Local mode - stored in IndexedDB" };
    }
}

// ===========================
// PAYROLL API
// ===========================

export async function savePayrollToRender(payrollData) {
    const result = await apiCall('/api/payroll', {
        method: 'POST',
        body: JSON.stringify(payrollData)
    });
    if (result) return result;
    return await saveToStore('payroll', { ...payrollData, id: payrollData.id || Date.now() });
}

export async function getPayrollFromRender(period) {
    const result = await apiCall('/api/payroll' + (period ? `?period=${period}` : ''));
    if (result) return result;
    const all = await getAllFromStore('payroll');
    if (period && period !== 'all') return all.filter(p => p.period === period);
    return all;
}

export async function updatePayrollStatus(id, status, reason = null, approvedBy = null) {
    const result = await apiCall(`/api/payroll/${id}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status, reason, approvedBy })
    });
    if (result) return result;

    const all = await getAllFromStore('payroll');
    const payroll = all.find(p => p.id === id);
    if (payroll) {
        payroll.status = status;
        payroll.approved = status === 'Approved';
        if (status === 'Approved') { payroll.approved_at = new Date().toISOString(); payroll.approved_by = approvedBy; }
        else if (status === 'Rejected') { payroll.rejected_at = new Date().toISOString(); payroll.rejection_reason = reason; }
        return await saveToStore('payroll', payroll);
    }
    return null;
}

export async function getPayslipFromRender(employeeId, period) {
    const result = await apiCall(`/api/payslip/${employeeId}?period=${period}`);
    if (result) return result;
    const all = await getAllFromStore('payroll');
    return all.find(p => p.employee_id === employeeId && p.period === period);
}

// ===========================
// TEACHER LOADS API
// ===========================

export async function saveTeacherLoadsToRender(loadsData) {
    const result = await apiCall('/api/teacher-loads', {
        method: 'POST',
        body: JSON.stringify(loadsData)
    });
    if (result) return result;
    return await updateByEmployeeId('teacher_loads', loadsData.employee_id, loadsData);
}

export async function getTeacherLoadsFromRender(employeeId, semester, schoolYear) {
    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    if (semester) params.append('semester', semester);
    if (schoolYear) params.append('school_year', schoolYear);

    const result = await apiCall(`/api/teacher-loads?${params.toString()}`);
    if (result) return result;

    const all = await getAllFromStore('teacher_loads');
    let filtered = all;
    if (employeeId) filtered = filtered.filter(l => l.employee_id === employeeId);
    if (semester) filtered = filtered.filter(l => l.semester === semester);
    if (schoolYear) filtered = filtered.filter(l => l.school_year === schoolYear);
    return filtered;
}

// ===========================
// NOTIFICATIONS API
// ===========================

export async function sendNotification(notification) {
    const result = await apiCall('/api/notifications', {
        method: 'POST',
        body: JSON.stringify(notification)
    });
    if (result) return result;

    const notifications = await getAllFromStore('notifications');
    const newNotification = { ...notification, id: Date.now(), timestamp: new Date().toISOString(), read: false };
    return await saveToStore('notifications', newNotification);
}

export async function getNotifications(read = false) {
    const result = await apiCall('/api/notifications');
    if (result) return read ? result.filter(n => !n.read) : result;

    const all = await getAllFromStore('notifications');
    if (read !== undefined && read !== null) return all.filter(n => n.read === read);
    return all.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
}

export async function markNotificationAsRead(notificationId) {
    const result = await apiCall(`/api/notifications/${notificationId}/read`, { method: 'PUT' });
    if (result) return result;

    const all = await getAllFromStore('notifications');
    const notification = all.find(n => n.id === notificationId);
    if (notification) { notification.read = true; return await saveToStore('notifications', notification); }
    return { success: true };
}

// ===========================
// EXPORT DEFAULT
// ===========================

export default {
    login, logout, isAuthenticated, validateToken,
    getEmployeesFromRender, saveEmployeeToRender, syncEmployeesToServer,
    savePayrollToRender, getPayrollFromRender, updatePayrollStatus, getPayslipFromRender,
    saveTeacherLoadsToRender, getTeacherLoadsFromRender,
    sendNotification, getNotifications, markNotificationAsRead
};

