// js/employees.js - UPDATED VERSION with Archive Support
console.log("EMPLOYEES JS LOADED");

// Pagination settings
const ITEMS_PER_PAGE = 8;
let totalItems = 0;
let allEmployees = [];
let showArchived = false;

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to employee changes
        Database.Events.on(DB_EVENTS.EMPLOYEE_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_DELETED, handleDataChange);
        
        loadEmployees();
    } catch (error) {
        console.error("Error initializing:", error);
    }

    // Filter button toggle
    const filterBtn = document.getElementById("filterBtn");
    const filterDropdown = document.getElementById("filterDropdown");

    if (filterBtn && filterDropdown) {
        filterBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            filterDropdown.style.display = 
                filterDropdown.style.display === "block" ? "none" : "block";
        });

        document.addEventListener("click", (e) => {
            if (!filterBtn.contains(e.target) && !filterDropdown.contains(e.target)) {
                filterDropdown.style.display = "none";
            }
        });
    }
    
    // Archive toggle
    const archiveToggle = document.getElementById("archiveToggle");
    if (archiveToggle) {
        archiveToggle.addEventListener("click", toggleArchiveView);
    }
});

// Handler for database changes
function handleDataChange(data) {
    console.log("Employee data change detected:", data);
    loadEmployees();
}

async function loadEmployees() {
    try {
        const employees = await Database.getAllEmployees();
        allEmployees = employees;
        applyFilters();
    } catch (error) {
        console.error("Error loading employees:", error);
    }
}

function applyFilters() {
    // Filter by archive status
    let filtered = allEmployees.filter(emp => {
        if (!showArchived && emp.status === 'Archived') return false;
        if (showArchived && emp.status !== 'Archived') return false;
        return true;
    });
    
    allEmployees = filtered;
    totalItems = filtered.length;
    currentPage = 1;
    renderEmployees();
    updatePagination();
}

function renderEmployees() {
    const tbody = document.getElementById("employeeBody");
    if (!tbody) return;
    
    tbody.innerHTML = "";

    if (allEmployees.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:20px;">
                    No employees found.
                </td>
            </tr>
        `;
        return;
    }

    // Calculate pagination
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalItems);
    const pageEmployees = allEmployees.slice(startIndex, endIndex);

    pageEmployees.forEach(emp => {
        // Determine assignment display
        let assignmentBadge = '';
        let rateDisplay = '';
        
        switch(emp.assignment) {
            case 'shs_only':
                assignmentBadge = '<span class="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">SHS</span>';
                rateDisplay = `₱${emp.rate_shs || 80}/hr`;
                break;
            case 'college_only':
                assignmentBadge = '<span class="bg-purple-100 text-purple-600 text-xs px-2 py-1 rounded-full">College</span>';
                rateDisplay = `₱${emp.rate_college || 85}/hr`;
                break;
            case 'both':
                assignmentBadge = '<span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full">SHS+CLG</span>';
                rateDisplay = `₱${emp.rate_shs || 80}/${emp.rate_college || 85}`;
                break;
            case 'admin':
                assignmentBadge = '<span class="bg-green-100 text-green-600 text-xs px-2 py-1 rounded-full">Admin</span>';
                rateDisplay = `₱${emp.rate_admin || 70}/hr`;
                break;
            case 'guard':
                assignmentBadge = '<span class="bg-yellow-100 text-yellow-600 text-xs px-2 py-1 rounded-full">Guard</span>';
                rateDisplay = `₱${emp.rate_guard || 433}/day`;
                break;
            case 'sa':
                assignmentBadge = '<span class="bg-orange-100 text-orange-600 text-xs px-2 py-1 rounded-full">SA</span>';
                rateDisplay = `₱${emp.rate_sa || 100}/hr`;
                break;
            default:
                assignmentBadge = '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">Regular</span>';
                rateDisplay = `₱${emp.base_salary || 0}`;
        }
        
        const row = `
            <tr class="${emp.status === 'Archived' ? 'opacity-60' : ''}">
                <td>#${emp.id}</td>
                <td>${emp.full_name}</td>
                <td>${emp.position || '—'}</td>
                <td>${assignmentBadge}</td>
                <td>${rateDisplay}</td>
                <td><span class="status-${emp.status === 'Active' ? 'active' : 'archived'}">${emp.status || 'Active'}</span></td>
                <td class="actions">
                    ${emp.status === 'Active' ? `
                        <button onclick="editEmployee(${emp.id})" title="Edit">✏️</button>
                        <button onclick="viewEmployee(${emp.id})" title="View">👁️</button>
                        <button onclick="archiveEmployee(${emp.id})" title="Archive">📦</button>
                    ` : `
                        <button onclick="restoreEmployee(${emp.id})" title="Restore">↩️</button>
                        <button onclick="permanentDelete(${emp.id})" title="Delete Permanently">🗑️</button>
                    `}
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function updatePagination() {
    const pagination = document.getElementById("pagination");
    if (!pagination) return;

    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    const startItem = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);

    if (totalItems === 0) {
        pagination.innerHTML = `
            <button class="page-btn" onclick="goToPage(1)" disabled>◀</button>
            <span class="page-info">No records</span>
            <button class="page-btn" onclick="goToPage(1)" disabled>▶</button>
        `;
        return;
    }

    pagination.innerHTML = `
        <button class="page-btn" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>◀</button>
        <span class="page-info">${startItem}–${endItem} OF ${totalItems}</span>
        <button class="page-btn" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>▶</button>
    `;
}

window.goToPage = function(page) {
    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderEmployees();
    updatePagination();
};

window.editEmployee = function(id) {
    window.location.href = "edit-employee.html?id=" + id;
};

window.viewEmployee = function(id) {
    window.location.href = "view-employee.html?id=" + id;
};

// ============================================
// ARCHIVE FUNCTIONS
// ============================================

function toggleArchiveView() {
    showArchived = !showArchived;
    const toggleBtn = document.getElementById('archiveToggle');
    const toggleText = toggleBtn?.querySelector('span');
    
    if (showArchived) {
        toggleBtn?.classList.add('active');
        if (toggleText) toggleText.textContent = 'Show Archived';
    } else {
        toggleBtn?.classList.remove('active');
        if (toggleText) toggleText.textContent = 'Show Active';
    }
    
    loadEmployees();
}

async function archiveEmployee(id) {
    if (!confirm("Archive this employee? They will be moved to archive and won't appear in active lists.")) return;

    try {
        const employee = await Database.getEmployeeById(id);
        employee.status = 'Archived';
        employee.archived_at = new Date().toISOString();
        
        await Database.updateEmployee(employee);
        alert("Employee archived successfully!");
        loadEmployees();
    } catch (error) {
        console.error("Error archiving employee:", error);
        alert("Error archiving employee");
    }
}

async function restoreEmployee(id) {
    if (!confirm("Restore this employee to active status?")) return;

    try {
        const employee = await Database.getEmployeeById(id);
        employee.status = 'Active';
        delete employee.archived_at;
        
        await Database.updateEmployee(employee);
        alert("Employee restored successfully!");
        loadEmployees();
    } catch (error) {
        console.error("Error restoring employee:", error);
        alert("Error restoring employee");
    }
}

async function permanentDelete(id) {
    if (!confirm("⚠️ PERMANENT DELETE: This action cannot be undone. Delete this employee permanently?")) return;

    try {
        await Database.deleteEmployee(id);
        alert("Employee permanently deleted.");
        loadEmployees();
    } catch (error) {
        console.error("Error deleting employee:", error);
        alert("Error deleting employee");
    }
}

// Filter function
window.filterType = function(type) {
    Database.getAllEmployees()
        .then(employees => {
            const filtered = type === 'All' 
                ? employees 
                : employees.filter(emp => emp.employment_type === type);
            
            allEmployees = filtered;
            totalItems = filtered.length;
            currentPage = 1;
            renderEmployees();
            updatePagination();
        });
};