// Payroll Module

// Pagination settings
const ITEMS_PER_PAGE = 6;
let currentPage = 1;
let totalItems = 0;
let allPayroll = [];
let allEmployees = [];
let currentFilter = 'All';
let currentSearch = '';

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to payroll changes to auto-refresh
        Database.Events.on(DB_EVENTS.PAYROLL_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_DELETED, handleDataChange);
        
        loadPayrollData();
        
        // Setup filter button toggle
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
        
        // Setup search input
        const searchInput = document.getElementById("searchInput");
        if (searchInput) {
            searchInput.addEventListener("input", (e) => {
                currentSearch = e.target.value.toLowerCase();
                currentPage = 1;
                renderPayroll();
                updatePagination();
            });
        }
    } catch (error) {
        console.error("Error initializing payroll:", error);
    }
});

// Handler for database changes - refreshes payroll data
function handleDataChange(data) {
    console.log("Payroll data change detected:", data);
    loadPayrollData();
}

async function loadPayrollData() {
    try {
        const payroll = await Database.getAllPayroll();
        const employees = await Database.getAllEmployees();

        allPayroll = payroll;
        allEmployees = employees;
        currentPage = 1;
        
        applyFilters();
    } catch (error) {
        console.error("Error loading payroll:", error);
    }
}

function applyFilters() {
    let filtered = [...allPayroll];
    
    // Apply search filter
    if (currentSearch) {
        filtered = filtered.filter(row => {
            const employee = allEmployees.find(emp => emp.id == row.employee_id);
            const full_name = employee ? employee.full_name.toLowerCase() : "";
            return full_name.includes(currentSearch);
        });
    }
    
    // Apply period filter
    if (currentFilter !== 'All') {
        filtered = filtered.filter(row => {
            return row.period && row.period.toLowerCase().includes(currentFilter.toLowerCase());
        });
    }
    
    allPayroll = filtered;
    totalItems = filtered.length;
    renderPayroll();
    updatePagination();
}

function renderPayroll() {
    const tbody = document.getElementById("payrollBody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (allPayroll.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:20px;">
                    No payroll records found.
                </td>
            </tr>
        `;
        return;
    }

    // Calculate pagination
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalItems);
    const pagePayroll = allPayroll.slice(startIndex, endIndex);

    pagePayroll.forEach(row => {
        // Find employee name
        const employee = allEmployees.find(emp => emp.id == row.employee_id);
        const full_name = employee ? employee.full_name : "Unknown";

        tbody.innerHTML += `
            <tr>
                <td>${full_name}</td>
                <td class="period">${row.period} <span>📅</span></td>
                <td>₱${parseFloat(row.gross_salary).toLocaleString()}</td>
                <td>₱${parseFloat(row.total_deduction).toLocaleString()}</td>
                <td>₱${parseFloat(row.net_salary).toLocaleString()}</td>
                <td>${row.status}</td>
                <td class="actions">
                    <button onclick="editPayroll(${row.id})">✏</button>
                    <button onclick="deletePayroll(${row.id})">🗑</button>
                    <button onclick="printPayroll(${row.id})">🖨️</button>
                </td>
            </tr>
        `;
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
    renderPayroll();
    updatePagination();
};

// Filter by period
window.filterPayroll = function(period) {
    currentFilter = period;
    currentPage = 1;
    
    // Close dropdown
    const filterDropdown = document.getElementById("filterDropdown");
    if (filterDropdown) {
        filterDropdown.style.display = "none";
    }
    
    // Update filter button text
    const filterBtn = document.getElementById("filterBtn");
    if (filterBtn) {
        filterBtn.innerHTML = period === 'All' ? 'Filter by Date ▼' : `${period} ▼`;
    }
    
    applyFilters();
};

function editPayroll(id) {
    window.location.href = "edit-payrollSadmin.html?id=" + id;
}

function deletePayroll(id) {
    if (!confirm("Are you sure you want to delete this payroll record?")) return;
    alert("Delete payroll functionality - ID: " + id);
}

// Generate Payroll
window.generatePayroll = function() {
    alert("Generate Payroll - This will calculate payroll for all employees based on their attendance.");
};

// Generate Payslip
window.generatePayslip = function() {
    window.location.href = "payslipSadmin.html";
};

// Export
window.exportPayroll = function() {
    alert("Export functionality - Export payroll data to Excel/PDF");
};

// Print Payroll
window.printPayroll = function(id) {
    window.location.href = "payslipSadmin.html?id=" + id;
};
