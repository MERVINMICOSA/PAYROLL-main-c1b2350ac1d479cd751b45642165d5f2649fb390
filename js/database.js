// database.js - Complete Standalone Version for Philtech GMA
// No ES6 imports - pure JavaScript for all pages

// ===========================
// GLOBAL NAMESPACE
// ===========================
window.Database = window.Database || {};
window.DB_EVENTS = window.DB_EVENTS || {};

// ===========================
// API INTEGRATION AUTO-INIT
// ===========================
// Auto-create ApiIntegration if not already available
async function ensureApiIntegration() {
    if (window.ApiIntegration && typeof window.ApiIntegration.getEmployees === 'function') {
        return window.ApiIntegration;
    }
    
    // If not available, create a basic fallback
    console.warn("ApiIntegration not found, creating fallback...");
    
    // Simple fetch-based API fallback
    window.ApiIntegration = {
        async call(endpoint, options = {}) {
            const response = await fetch(endpoint, {
                ...options,
                headers: { 'Content-Type': 'application/json', ...options.headers },
                credentials: 'include'
            });
            if (!response.ok) throw new Error(`API Error: ${response.status}`);
            return response.json();
        },
        async getEmployees() { return this.call('/api/employees'); },
        async getEmployee(id) { return this.call(`/api/employee?id=${id}`); },
        async createEmployee(data) { return this.call('/api/employees', { method: 'POST', body: JSON.stringify(data) }); },
        async updateEmployee(id, data) { return this.call('/api/employees', { method: 'PUT', body: JSON.stringify({ ...data, id }) }); },
        async deleteEmployee(id) { return this.call(`/api/employees?id=${id}`, { method: 'DELETE' }); },
        async getPayroll() { return this.call('/api/payroll'); },
        async getPayrollById(id) { return this.call(`/api/payroll?id=${id}`); },
        async createPayroll(data) { return this.call('/api/payroll', { method: 'POST', body: JSON.stringify(data) }); },
        async updatePayroll(id, data) { return this.call('/api/payroll', { method: 'PUT', body: JSON.stringify({ ...data, id }) }); },
        async deletePayroll(id) { return this.call(`/api/payroll?id=${id}`, { method: 'DELETE' }); },
        async getAttendance(type = 'admin-master') { return this.call(`/api/attendance/${type}`); },
        async createAttendance(type, data) { return this.call(`/api/attendance/${type}`, { method: 'POST', body: JSON.stringify(data) }); },
        async updateAttendance(type, id, data) { return this.call(`/api/attendance/${type}`, { method: 'PUT', body: JSON.stringify({ ...data, id }) }); },
        async deleteAttendance(type, id) { return this.call(`/api/attendance/${type}?id=${id}`, { method: 'DELETE' }); },
        async getUsers() { return this.call('/api/users'); },
        async createUser(data) { return this.call('/api/users', { method: 'POST', body: JSON.stringify(data) }); },
        async updateUser(id, data) { return this.call('/api/users', { method: 'PUT', body: JSON.stringify({ ...data, id }) }); },
        async deleteUser(id) { return this.call(`/api/users?id=${id}`, { method: 'DELETE' }); },
        async updateSettings(data) { return this.call('/api/settings', { method: 'PUT', body: JSON.stringify(data) }); }
    };
    
    return window.ApiIntegration;
}

// ===========================
// DATABASE CONFIGURATION
// ===========================
const DB_NAME = "PhiltechGMADB";
const DB_VERSION = 6;

let dbInstance = null;
let dbInitialized = false;
let initPromise = null;
const eventListeners = {};

// ===========================
// EVENT HANDLING
// ===========================
window.DB_EVENTS.on = function(event, callback) {
    if (!eventListeners[event]) eventListeners[event] = [];
    eventListeners[event].push(callback);
};

window.DB_EVENTS.off = function(event, callback) {
    if (!eventListeners[event]) return;
    eventListeners[event] = eventListeners[event].filter(cb => cb !== callback);
};

function emitEvent(event, data) {
    if (!eventListeners[event]) return;
    eventListeners[event].forEach(callback => {
        try { callback(data); } catch(e) { console.error(e); }
    });
}

// ===========================
// DATABASE INITIALIZATION
// ===========================
function initDatabase() {
    if (initPromise) return initPromise;
    
    initPromise = new Promise((resolve, reject) => {
        if (dbInitialized && dbInstance) {
            resolve(dbInstance);
            return;
        }
        
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        
        request.onerror = function(event) {
            console.error('Database error:', event.target.error);
            reject(event.target.error);
        };
        
        request.onsuccess = function(event) {
            dbInstance = event.target.result;
            dbInitialized = true;
            
            dbInstance.onversionchange = function() {
                dbInstance.close();
            };
            
            console.log("IndexedDB ready:", DB_NAME);
            resolve(dbInstance);
        };
        
        request.onupgradeneeded = function(event) {
            const db = event.target.result;
            console.log("Creating/upgrading database stores...");
            
            // Employees store
            if (!db.objectStoreNames.contains("employees")) {
                const store = db.createObjectStore("employees", { keyPath: "id", autoIncrement: true });
                store.createIndex("full_name", "full_name");
                store.createIndex("assignment", "assignment");
                store.createIndex("status", "status");
                store.createIndex("email", "email");
                console.log("Created employees store");
            }
            
            // Teacher loads store
            if (!db.objectStoreNames.contains("teacher_loads")) {
                const store = db.createObjectStore("teacher_loads", { keyPath: "id", autoIncrement: true });
                store.createIndex("employee_id", "employee_id");
                store.createIndex("semester", "semester");
                store.createIndex("school_year", "school_year");
                console.log("Created teacher_loads store");
            }
            
            // Attendance store
            if (!db.objectStoreNames.contains("attendance")) {
                const store = db.createObjectStore("attendance", { keyPath: "id", autoIncrement: true });
                store.createIndex("employee_id", "employee_id");
                store.createIndex("tab_type", "tab_type");
                store.createIndex("date", "date");
                store.createIndex("period_start", "period_start");
                store.createIndex("period_end", "period_end");
                console.log("Created attendance store");
            }
            
            // Users store
            if (!db.objectStoreNames.contains("users")) {
                const store = db.createObjectStore("users", { keyPath: "id", autoIncrement: true });
                store.createIndex("username", "username", { unique: true });
                store.createIndex("role", "role");
                store.createIndex("linked_employee", "linked_employee");
                console.log("Created users store");
            }
            
            // Payroll store
            if (!db.objectStoreNames.contains("payroll")) {
                const store = db.createObjectStore("payroll", { keyPath: "id", autoIncrement: true });
                store.createIndex("employee_id", "employee_id");
                store.createIndex("period", "period");
                store.createIndex("status", "status");
                console.log("Created payroll store");
            }
            
            // Settings store
            if (!db.objectStoreNames.contains("settings")) {
                db.createObjectStore("settings", { keyPath: "key" });
                console.log("Created settings store");
            }
            
            // Notifications store
            if (!db.objectStoreNames.contains("notifications")) {
                const store = db.createObjectStore("notifications", { keyPath: "id", autoIncrement: true });
                store.createIndex("read", "read");
                store.createIndex("type", "type");
                console.log("Created notifications store");
            }
            
            console.log("All database stores created/upgraded");
        };
    });
    
    return initPromise;
}

// Wait for database helper
window.ensureDatabaseReady = function() {
    return initDatabase();
};

// ===========================
// INDEXEDDB HELPERS
// ===========================
async function getAllFromStore(storeName) {
    const db = await initDatabase();
    if (!db.objectStoreNames.contains(storeName)) return [];
    
    return new Promise((resolve, reject) => {
        const tx = db.transaction([storeName], "readonly");
        const store = tx.objectStore(storeName);
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getByIdFromStore(storeName, id) {
    const all = await getAllFromStore(storeName);
    return all.find(item => item.id === id);
}

async function addToStore(storeName, data) {
    const db = await initDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([storeName], "readwrite");
        const store = tx.objectStore(storeName);
        const request = store.add(data);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function updateInStore(storeName, data) {
    const db = await initDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([storeName], "readwrite");
        const store = tx.objectStore(storeName);
        const request = store.put(data);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function deleteFromStore(storeName, id) {
    const db = await initDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([storeName], "readwrite");
        const store = tx.objectStore(storeName);
        const request = store.delete(Number(id));
        request.onsuccess = () => resolve(true);
        request.onerror = () => reject(request.error);
    });
}

// ===========================
// EMPLOYEES
// ===========================
async function getAllEmployees() {
    const api = await ensureApiIntegration();
    const employees = await api.getEmployees();
    console.log("✓ Loaded " + (employees?.length || 0) + " employees from database");
    return Array.isArray(employees) ? employees : [];
}

async function getEmployeeById(id) {
    // Try API first
    try {
        const api = await ensureApiIntegration();
        return await api.getEmployee(id);
    } catch (error) {
        console.warn("API call failed, falling back to IndexedDB:", error);
    }
    // Fallback to IndexedDB
    const employees = await getAllFromStore("employees");
    return employees.find(e => e.id === id);
}

async function getEmployeesByAssignment(assignment) {
    const employees = await getAllEmployees();
    return employees.filter(e => e.assignment === assignment && e.status === "Active");
}

async function addEmployee(employee) {
    const api = await ensureApiIntegration();
    const result = await api.createEmployee(employee);
    console.log("✓ Employee added to database");
    emitEvent(DB_EVENTS.EMPLOYEE_ADDED, result);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "employee_added", data: result });
    return result;
}

async function updateEmployee(employee) {
    const api = await ensureApiIntegration();
    const result = await api.updateEmployee(employee.id, employee);
    console.log("✓ Employee updated in database");
    emitEvent(DB_EVENTS.EMPLOYEE_UPDATED, result);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "employee_updated", data: result });
    return result;
}

async function deleteEmployee(id) {
    const api = await ensureApiIntegration();
    await api.deleteEmployee(id);
    console.log("✓ Employee deleted from database");
    emitEvent(DB_EVENTS.EMPLOYEE_DELETED, { id });
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "employee_deleted", id });
    return true;
}

// ===========================
// TEACHER LOADS
// ===========================
async function getAllTeacherLoads() {
    return getAllFromStore("teacher_loads");
}

async function getTeacherLoadsByEmployee(employeeId, semester, schoolYear) {
    const all = await getAllTeacherLoads();
    let filtered = all.filter(l => l.employee_id === employeeId);
    if (semester) filtered = filtered.filter(l => l.semester === semester);
    if (schoolYear) filtered = filtered.filter(l => l.school_year === schoolYear);
    return filtered[0] || null;
}

async function saveTeacherLoad(load) {
    const existing = await getTeacherLoadsByEmployee(load.employee_id, load.semester, load.school_year);
    
    if (existing && existing.id) {
        load.id = existing.id;
        await updateInStore("teacher_loads", load);
        emitEvent(DB_EVENTS.LOADING_UPDATED, load);
    } else {
        const newId = await addToStore("teacher_loads", load);
        load.id = newId;
        emitEvent(DB_EVENTS.LOADING_ADDED, load);
    }
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "loading_updated", data: load });
    return load;
}

// ===========================
// ATTENDANCE
// ===========================
async function getAllAttendance() {
    const api = await ensureApiIntegration();
    const attendance = await api.getAttendance('admin-master');
    console.log("✓ Loaded " + (attendance?.length || 0) + " attendance records from database");
    return Array.isArray(attendance) ? attendance : [];
}

async function getAttendanceByEmployee(employeeId) {
    const all = await getAllAttendance();
    return all.filter(a => Number(a.employee_id) === Number(employeeId));
}

async function getAttendanceById(id) {
    const all = await getAllAttendance();
    return all.find(a => a.id === id);
}

async function getAttendanceByPeriod(periodStart, periodEnd, tabType) {
    const all = await getAllAttendance();
    let filtered = all;
    if (periodStart) filtered = filtered.filter(a => new Date(a.date) >= new Date(periodStart));
    if (periodEnd) filtered = filtered.filter(a => new Date(a.date) <= new Date(periodEnd));
    if (tabType) filtered = filtered.filter(a => a.tab_type === tabType);
    return filtered;
}

async function addAttendance(record) {
    const api = await ensureApiIntegration();
    if (record.date && !record.date.includes("-")) {
        record.date = new Date(record.date).toISOString().split("T")[0];
    }
    const id = await api.createAttendance('admin-master', record);
    console.log("✓ Attendance record added to database");
    emitEvent(DB_EVENTS.ATTENDANCE_ADDED, record);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "attendance_added", data: record });
    return id;
}

async function updateAttendance(record) {
    const api = await ensureApiIntegration();
    if (record.date && !record.date.includes("-")) {
        record.date = new Date(record.date).toISOString().split("T")[0];
    }
    const result = await api.updateAttendance('admin-master', record.id, record);
    console.log("✓ Attendance record updated in database");
    emitEvent(DB_EVENTS.ATTENDANCE_UPDATED, result);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "attendance_updated", data: result });
    return result;
}

async function deleteAttendance(id) {
    const api = await ensureApiIntegration();
    await api.deleteAttendance('admin-master', id);
    console.log("✓ Attendance record deleted from database");
    emitEvent(DB_EVENTS.ATTENDANCE_DELETED, { id });
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "attendance_deleted", id });
    return true;
}

// ===========================
// USERS
// ===========================
async function getAllUsers() {
    const api = await ensureApiIntegration();
    const users = await api.getUsers();
    console.log("✓ Loaded " + (users?.length || 0) + " users from database");
    return Array.isArray(users) ? users : [];
}

async function getUserById(id) {
    const users = await getAllUsers();
    return users.find(u => u.id === id);
}

async function getUserByUsername(username) {
    const users = await getAllUsers();
    return users.find(u => u.username === username);
}

async function addUser(user) {
    const api = await ensureApiIntegration();
    const result = await api.createUser(user);
    console.log("✓ User added to database");
    emitEvent(DB_EVENTS.USER_ADDED, result);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "user_added", data: result });
    return result;
}

async function updateUser(user) {
    const api = await ensureApiIntegration();
    const result = await api.updateUser(user.id, user);
    console.log("✓ User updated in database");
    emitEvent(DB_EVENTS.USER_UPDATED, result);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "user_updated", data: result });
    return result;
}

async function deleteUser(id) {
    const api = await ensureApiIntegration();
    await api.deleteUser(id);
    console.log("✓ User deleted from database");
    emitEvent(DB_EVENTS.USER_DELETED, { id });
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "user_deleted", id });
    return true;
}

// ===========================
// PAYROLL
// ===========================
async function getAllPayroll() {
    const api = await ensureApiIntegration();
    const payroll = await api.getPayroll();
    console.log("✓ Loaded " + (payroll?.length || 0) + " payroll records from database");
    return Array.isArray(payroll) ? payroll : [];
}

async function getPayrollById(id) {
    // Try API first
    if (id) {
        try {
            const api = await ensureApiIntegration();
            return await api.getPayrollById(id);
        } catch (error) {
            console.warn("API call failed, falling back to IndexedDB:", error);
        }
    }
    // Fallback to IndexedDB
    const all = await getAllFromStore("payroll");
    return all.find(p => p.id === id);
}

async function addPayroll(record) {
    const api = await ensureApiIntegration();
    const id = await api.createPayroll(record);
    console.log("✓ Payroll record added to database");
    emitEvent(DB_EVENTS.PAYROLL_ADDED, record);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "payroll_added", data: record });
    return id;
}

async function updatePayroll(record) {
    const api = await ensureApiIntegration();
    const result = await api.updatePayroll(record.id, record);
    console.log("✓ Payroll record updated in database");
    emitEvent(DB_EVENTS.PAYROLL_UPDATED, result);
    emitEvent(DB_EVENTS.ANY_CHANGE, { type: "payroll_updated", data: result });
    return result;
}

async function updatePayrollStatus(id, status, reason = null, approvedBy = null) {
    const payroll = await getPayrollById(id);
    if (!payroll) throw new Error("Payroll not found");
    
    payroll.status = status;
    payroll.approved = status === "Approved";
    
    if (status === "Approved") {
        payroll.approved_at = new Date().toISOString();
        payroll.approved_by = approvedBy;
    } else if (status === "Rejected") {
        payroll.rejected_at = new Date().toISOString();
        payroll.rejection_reason = reason;
    }
    
    return updatePayroll(payroll);
}

async function submitForApproval(period) {
    const all = await getAllPayroll();
    const pendingPayroll = all.filter(p => p.period === period && (p.status === "Pending" || !p.approved));
    
    for (const payroll of pendingPayroll) {
        payroll.status = "Pending";
        payroll.submitted_at = new Date().toISOString();
        await updatePayroll(payroll);
    }
    
    return { count: pendingPayroll.length, records: pendingPayroll };
}

async function generatePayrollFromAttendance(periodStart, periodEnd) {
    try {
        const employees = await getAllEmployees();
        const attendance = await getAllAttendance();
        const payrollRecords = [];
        
        const periodLabel = `${new Date(periodStart).toLocaleDateString()} - ${new Date(periodEnd).toLocaleDateString()}`;
        const existingPayroll = await getAllPayroll();
        
        for (const employee of employees) {
            const empAttendance = attendance.filter(a => 
                Number(a.employee_id) === Number(employee.id) &&
                new Date(a.date) >= new Date(periodStart) &&
                new Date(a.date) <= new Date(periodEnd)
            );
            
            if (empAttendance.length === 0) continue;
            
            let totalHours = 0;
            let totalOvertime = 0;
            let totalLates = 0;
            let totalAdminPay = 0;
            
            empAttendance.forEach(att => {
                if (att.tab_type === "shs-dtr" || att.tab_type === "college-dtr") {
                    totalHours += (att.mon || 0) + (att.tue || 0) + (att.wed || 0) + 
                                  (att.thu || 0) + (att.fri || 0) + (att.sat || 0) + (att.sun || 0);
                } else if (att.tab_type === "eda") {
                    totalOvertime += att.overtime || 0;
                    totalLates += att.lates || 0;
                } else if (att.tab_type === "admin-pay") {
                    const adminHours = (att.mon || 0) + (att.tue || 0) + (att.wed || 0) + 
                                       (att.thu || 0) + (att.fri || 0) + (att.sat || 0) + (att.sun || 0);
                    const adminRate = att.admin_pay_rate || employee.admin_pay_rate || 0;
                    totalAdminPay += adminHours * adminRate;
                }
            });
            
            let hourlyRate = 0;
            switch(employee.assignment) {
                case "shs_only": hourlyRate = employee.rate_shs || 80; break;
                case "college_only": hourlyRate = employee.rate_college || 85; break;
                case "admin": hourlyRate = employee.rate_admin || 70; break;
                case "guard": hourlyRate = (employee.rate_guard || 433) / 8; break;
                case "sa": hourlyRate = employee.rate_sa || 100; break;
                default: hourlyRate = 80;
            }
            
            const regularSalary = totalHours * hourlyRate;
            const overtimeSalary = totalOvertime * hourlyRate * 1.25;
            const grossSalary = regularSalary + overtimeSalary + totalAdminPay;
            
            const undertimeHours = totalLates / 60;
            const undertimeDeduction = undertimeHours * hourlyRate;
            const sss = Math.round(grossSalary * 0.045);
            const philhealth = Math.round(grossSalary * 0.03);
            const pagibig = 100;
            const totalDeduction = sss + philhealth + pagibig + Math.round(undertimeDeduction);
            const netSalary = Math.round(grossSalary - totalDeduction);
            
            const payrollRecord = {
                employee_id: employee.id,
                period: periodLabel,
                period_start: periodStart,
                period_end: periodEnd,
                regular_hours: totalHours,
                overtime_hours: totalOvertime,
                admin_pay: totalAdminPay,
                gross_salary: Math.round(grossSalary),
                sss: sss,
                philhealth: philhealth,
                pagibig: pagibig,
                undertime_deduction: Math.round(undertimeDeduction),
                total_deduction: totalDeduction,
                net_salary: netSalary,
                status: "Pending",
                approved: false,
                generated_at: new Date().toISOString()
            };
            
            const existing = existingPayroll.find(p => 
                p.employee_id === employee.id && p.period === periodLabel
            );
            
            if (existing) {
                payrollRecord.id = existing.id;
                await updatePayroll(payrollRecord);
                payrollRecords.push(payrollRecord);
            } else {
                await addPayroll(payrollRecord);
                payrollRecords.push(payrollRecord);
            }
        }
        
        return payrollRecords;
    } catch (error) {
        console.error("Error generating payroll:", error);
        throw error;
    }
}

// ===========================
// NOTIFICATIONS
// ===========================
async function addNotification(notification) {
    const newNotification = {
        ...notification,
        id: Date.now(),
        timestamp: new Date().toISOString(),
        read: false
    };
    await addToStore("notifications", newNotification);
    return newNotification;
}

async function getNotifications(unreadOnly = false) {
    const all = await getAllFromStore("notifications");
    if (unreadOnly) return all.filter(n => !n.read);
    return all.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
}

async function markNotificationRead(id) {
    const notifications = await getAllFromStore("notifications");
    const notification = notifications.find(n => n.id === id);
    if (notification) {
        notification.read = true;
        await updateInStore("notifications", notification);
    }
    return true;
}

// ===========================
// SETTINGS
// ===========================
async function getSetting(key) {
    const api = await ensureApiIntegration();
    if (!key) {
        throw new Error("Setting key is required");
    }
    const result = await api.call(`/api/settings?key=${key}`);
    console.log("✓ Setting retrieved from database");
    return result;
}

// Alias for compatibility with settings.js
// Returns format: { key: 'system', data: {...} }
async function getSettingByKey(key) {
    const api = await ensureApiIntegration();
    if (!key) {
        throw new Error("Setting key is required");
    }
    try {
        const result = await api.call(`/api/settings?key=${key}`);
        if (result && result.value) {
            // Handle both string and object from JSONB
            let dataValue;
            if (typeof result.value === 'string') {
                // Try to parse JSON string
                try {
                    dataValue = JSON.parse(result.value);
                } catch (e) {
                    dataValue = result.value;
                }
            } else if (typeof result.value === 'object') {
                // Already an object (from JSONB)
                dataValue = result.value;
            } else {
                dataValue = result.value;
            }
            return { key: key, data: dataValue };
        }
        return { key: key, data: null };
    } catch (error) {
        console.warn("API call failed:", error);
        return { key: key, data: null };
    }
}

async function saveSetting(setting) {
    const key = setting.key;
    const value = typeof setting.data === 'object' ? JSON.stringify(setting.data) : setting.value;
    const settingRecord = { key, value, updated_at: new Date().toISOString() };
    
    // Try API first
    if (key) {
        try {
            const api = await ensureApiIntegration();
            await api.updateSettings({ key, value });
            // Update IndexedDB cache
            await updateInStore("settings", settingRecord);
            return setting;
        } catch (error) {
            console.warn("API call failed, using local store:", error);
        }
    }
    
    // Fallback to local store
    await updateInStore("settings", settingRecord);
    return setting;
}

// ===========================
// CLEAR DATA
// ===========================
async function clearAllData() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.deleteDatabase(DB_NAME);
        request.onsuccess = () => {
            console.log("Database deleted");
            dbInitialized = false;
            dbInstance = null;
            initPromise = null;
            resolve(true);
        };
        request.onerror = () => reject(request.error);
    });
}

// ===========================
// EXPORT TO GLOBAL
// ===========================
const Database = {
    ensureDatabaseReady,
    
    // Employees
    getAllEmployees,
    getEmployeeById,
    getEmployeesByAssignment,
    addEmployee,
    updateEmployee,
    deleteEmployee,
    
    // Teacher Loads
    getAllTeacherLoads,
    getTeacherLoadsByEmployee,
    saveTeacherLoad,
    
    // Attendance
    getAllAttendance,
    getAttendanceByEmployee,
    getAttendanceById,
    getAttendanceByPeriod,
    addAttendance,
    updateAttendance,
    deleteAttendance,
    
    // Payroll
    getAllPayroll,
    getPayrollById,
    addPayroll,
    updatePayroll,
    updatePayrollStatus,
    generatePayrollFromAttendance,
    submitForApproval,
    
    // Users
    getAllUsers,
    getUserById,
    getUserByUsername,
    addUser,
    updateUser,
    deleteUser,
    
    // Notifications
    addNotification,
    getNotifications,
    markNotificationRead,
    
// Settings
    getSetting,
    getSettingByKey,
    saveSetting,
    
    // Utility
    clearAllData,
    
    // Events
    Events: {
        on: window.DB_EVENTS.on,
        off: window.DB_EVENTS.off,
        emit: emitEvent
    }
};

window.Database = Database;
window.DB_EVENTS = {
    EMPLOYEE_ADDED: "employee:added",
    EMPLOYEE_UPDATED: "employee:updated",
    EMPLOYEE_DELETED: "employee:deleted",
    ATTENDANCE_ADDED: "attendance:added",
    ATTENDANCE_UPDATED: "attendance:updated",
    ATTENDANCE_DELETED: "attendance:deleted",
    LOADING_ADDED: "loading:added",
    LOADING_UPDATED: "loading:updated",
    PAYROLL_ADDED: "payroll:added",
    PAYROLL_UPDATED: "payroll:updated",
    PAYROLL_DELETED: "payroll:deleted",
    USER_ADDED: "user:added",
    USER_UPDATED: "user:updated",
    USER_DELETED: "user:deleted",
    ANY_CHANGE: "database:any_change"
};

console.log("Database loaded - Standalone Version (No ES6 Modules)");