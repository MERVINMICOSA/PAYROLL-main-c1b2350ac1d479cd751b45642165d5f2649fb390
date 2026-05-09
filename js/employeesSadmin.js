// Pagination settings
const ITEMS_PER_PAGE = 6;
let currentPage = 1;
let totalItems = 0;
let allEmployees = [];

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to employee changes to auto-refresh the list
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

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!filterBtn.contains(e.target) && !filterDropdown.contains(e.target)) {
                filterDropdown.style.display = "none";
            }
        });
    }
});

// Handler for database changes - refreshes employee list
function handleDataChange(data) {
    console.log("Employee data change detected:", data);
    loadEmployees();
}

function loadEmployees() {
    Database.getAllEmployees()
        .then(employees => {
            allEmployees = employees;
            totalItems = employees.length;
            currentPage = 1;
            renderEmployees();
            updatePagination();
        })
        .catch(error => {
            console.error("Error loading employees:", error);
        });
}

function renderEmployees() {
    const tbody = document.getElementById("employeeBody");
    if (!tbody) return;
    
    tbody.innerHTML = "";

    if (allEmployees.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:20px;">
                    No employees found. Click "Add Employee" to create one.
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
        const row = `
            <tr>
                <td>#${emp.id}</td>
                <td>${emp.full_name}</td>
                <td>${emp.position}</td>
                <td>${emp.employment_type}</td>
                <td>₱${parseFloat(emp.base_salary).toLocaleString()}</td>
                <td>${emp.status}</td>
                <td class="actions">
                    <button onclick="editEmployee(${emp.id})">✏</button>
                    <button onclick="deleteEmployeeRecord(${emp.id})">🗑</button>
                    <button onclick="viewEmployee(${emp.id})">👁</button>
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
    window.location.href = "edit-employeeSadmin.html?id=" + id;
};

window.viewEmployee = function(id) {
    window.location.href = "view-employeeSadmin.html?id=" + id;
};

window.deleteEmployeeRecord = async function(id) {
    if (!confirm("Are you sure you want to delete this employee?")) return;

    try {
        await Database.deleteEmployee(id);
        alert("Employee deleted successfully!");
        loadEmployees();
    } catch (error) {
        console.error("Error deleting employee:", error);
        alert("Error deleting employee");
    }
};

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
