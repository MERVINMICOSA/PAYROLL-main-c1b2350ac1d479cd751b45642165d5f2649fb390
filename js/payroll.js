// js/payroll.js - UPDATED VERSION with Period Sync from Attendance
console.log("PAYROLL JS LOADED - Period Sync Version");

// Pagination settings
const ITEMS_PER_PAGE = 10;
let currentPage = 1;
let totalItems = 0;
let allPayroll = [];
let allEmployees = [];
let currentFilter = 'All';
let currentSearch = '';
let currentSelectedPeriod = null;

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to payroll changes to auto-refresh
        Database.Events.on(DB_EVENTS.PAYROLL_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_DELETED, handleDataChange);
        
        // First, sync period from Attendance tab (localStorage)
        syncPeriodFromAttendance();
        
        // Then load payroll data
        await loadPayrollData();
        
        // Setup period dropdown change listener
        const periodSelect = document.getElementById('payrollPeriodSelect');
        if (periodSelect) {
            periodSelect.addEventListener('change', onPeriodSelectChange);
        }
        
        // Setup search input
        const searchInput = document.getElementById("searchInput");
        if (searchInput) {
            searchInput.addEventListener("input", (e) => {
                currentSearch = e.target.value.toLowerCase();
                currentPage = 1;
                applyFilters();
            });
        }
        
        // Setup status filter
        const statusFilter = document.getElementById("statusFilter");
        if (statusFilter) {
            statusFilter.addEventListener("change", filterByStatus);
        }
        
    } catch (error) {
        console.error("Error initializing payroll:", error);
    }
});

// ============================================
// PERIOD SYNC FUNCTIONS
// ============================================

// Sync period from Attendance tab (localStorage)
function syncPeriodFromAttendance() {
    const savedStart = localStorage.getItem('payrollPeriodStart');
    const savedEnd = localStorage.getItem('payrollPeriodEnd');
    
    if (savedStart && savedEnd) {
        const periodLabel = formatPeriodLabel(savedStart, savedEnd);
        currentSelectedPeriod = {
            start: savedStart,
            end: savedEnd,
            label: periodLabel
        };
        
        // Update display
        const activePeriodText = document.getElementById('activePeriodText');
        const activePeriodDisplay = document.getElementById('activePeriodDisplay');
        if (activePeriodText) activePeriodText.textContent = periodLabel;
        if (activePeriodDisplay) activePeriodDisplay.classList.remove('hidden');
        
        // Update dropdown
        updatePeriodDropdown(periodLabel);
        
        console.log("Period synced from Attendance:", periodLabel);
    } else {
        // No period set in Attendance
        const activePeriodDisplay = document.getElementById('activePeriodDisplay');
        if (activePeriodDisplay) activePeriodDisplay.classList.add('hidden');
    }
}

// Format period label from start and end dates
function formatPeriodLabel(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const startStr = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    const endStr = end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    return `${startStr} - ${endStr}`;
}

// Update period dropdown with the period from Attendance
function updatePeriodDropdown(periodLabel) {
    const select = document.getElementById('payrollPeriodSelect');
    if (!select) return;
    
    // Check if this period already exists in dropdown
    let exists = false;
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === periodLabel) {
            exists = true;
            select.options[i].selected = true;
            break;
        }
    }
    
    if (!exists) {
        // Add custom period option
        const option = document.createElement('option');
        option.value = periodLabel;
        option.textContent = periodLabel;
        option.selected = true;
        option.setAttribute('data-custom', 'true');
        select.appendChild(option);
    }
    
    // Enable buttons since period is selected
    updateButtonsState(true);
}

// Handle period dropdown change
function onPeriodSelectChange(event) {
    const selectedValue = event.target.value;
    
    if (selectedValue === "") {
        // No period selected - disable buttons
        updateButtonsState(false);
        currentSelectedPeriod = null;
    } else {
        // Period selected - enable buttons
        updateButtonsState(true);
        
        // Try to parse as custom period (from Attendance)
        if (selectedValue.includes(' - ')) {
            // This is a custom period from Attendance
            const savedStart = localStorage.getItem('payrollPeriodStart');
            const savedEnd = localStorage.getItem('payrollPeriodEnd');
            if (savedStart && savedEnd) {
                currentSelectedPeriod = {
                    start: savedStart,
                    end: savedEnd,
                    label: selectedValue
                };
            } else {
                currentSelectedPeriod = { label: selectedValue, isMonth: true };
            }
        } else {
            // This is a month-based period
            currentSelectedPeriod = { label: selectedValue, isMonth: true };
        }
    }
}

// Enable/disable buttons based on period selection
function updateButtonsState(enabled) {
    const generateBtn = document.querySelector('.generate-btn');
    const submitBtn = document.querySelector('.submit-btn');
    
    if (generateBtn) {
        generateBtn.disabled = !enabled;
        generateBtn.style.opacity = enabled ? '1' : '0.5';
        generateBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
    }
    
    if (submitBtn) {
        submitBtn.disabled = !enabled;
        submitBtn.style.opacity = enabled ? '1' : '0.5';
        submitBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
    }
}

// Sync button (manual sync from Attendance)
window.syncPeriodFromAttendance = function() {
    syncPeriodFromAttendance();
    alert(`Period synced: ${currentSelectedPeriod?.label || 'No period set in Attendance tab'}`);
};

// ============================================
// PAYROLL GENERATION & SUBMISSION
// ============================================

// Generate Payroll from Attendance
window.generatePayroll = async function() {
    if (!currentSelectedPeriod) {
        alert('Please select a period first, or set a period in the Attendance tab');
        return;
    }
    
    let startDate, endDate, periodLabel;
    
    // Get dates based on period type
    if (currentSelectedPeriod.start && currentSelectedPeriod.end) {
        // Custom period from Attendance
        startDate = currentSelectedPeriod.start;
        endDate = currentSelectedPeriod.end;
        periodLabel = currentSelectedPeriod.label;
    } else {
        // Month-based period
        const period = currentSelectedPeriod.label;
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
        const parts = period.split(' ');
        const month = parts[0];
        const year = parseInt(parts[1]);
        const monthIndex = monthNames.indexOf(month);
        
        if (monthIndex === -1) {
            alert('Invalid period selected');
            return;
        }
        
        startDate = new Date(year, monthIndex, 1).toISOString().split('T')[0];
        endDate = new Date(year, monthIndex + 1, 0).toISOString().split('T')[0];
        periodLabel = period;
    }
    
    if (!confirm(`Generate payroll for ${periodLabel} from attendance records? This will update existing records.`)) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;
    
    try {
        const result = await Database.generatePayrollFromAttendance(startDate, endDate);
        alert(`Generated/Updated ${result.length} payroll records for ${periodLabel}`);
        await loadPayrollData();
    } catch (error) {
        console.error('Error generating payroll:', error);
        alert('Error generating payroll: ' + error.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
};

// Submit Payroll for Approval
window.submitForApproval = async function() {
    if (!currentSelectedPeriod) {
        alert('Please select a period first');
        return;
    }
    
    let periodLabel = currentSelectedPeriod.label;
    
    if (!confirm(`Submit all pending payroll for ${periodLabel} to Superadmin for approval?`)) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    btn.disabled = true;
    
    try {
        // Get all pending payroll for this period
        const pendingPayroll = allPayroll.filter(p => 
            (p.period === periodLabel || p.period === currentSelectedPeriod.label) && 
            (p.status === 'Pending' || !p.approved)
        );
        
        if (pendingPayroll.length === 0) {
            alert('No pending payroll records found for this period.');
            return;
        }
        
        // Update each pending payroll to "Pending Approval" (they already are)
        // This triggers notifications to superadmin
        for (const payroll of pendingPayroll) {
            await Database.updatePayrollStatus(payroll.id, 'Pending', null, 'accountant');
        }
        
        alert(`Submitted ${pendingPayroll.length} payroll records for approval. Superadmin has been notified.`);
        await loadPayrollData();
    } catch (error) {
        console.error('Error submitting for approval:', error);
        alert('Error submitting: ' + error.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
};

// ============================================
// DATA LOADING FUNCTIONS
// ============================================

// Handler for database changes
function handleDataChange(data) {
    console.log("Payroll data change detected:", data);
    loadPayrollData();
}

async function loadPayrollData() {
    try {
        allPayroll = await Database.getAllPayroll();
        allEmployees = await Database.getAllEmployees();
        
        updateStats();
        applyFilters();
        
        // Update buttons state based on period selection
        if (currentSelectedPeriod) {
            updateButtonsState(true);
        } else {
            updateButtonsState(false);
        }
        
    } catch (error) {
        console.error("Error loading payroll:", error);
    }
}

function updateStats() {
    const totalEmployees = allEmployees.length;
    const totalPayroll = allPayroll.reduce((sum, p) => sum + (p.net_salary || 0), 0);
    const pendingCount = allPayroll.filter(p => p.status === 'Pending' || !p.approved).length;
    const approvedCount = allPayroll.filter(p => p.status === 'Approved' || p.approved).length;
    
    const totalEmployeesEl = document.getElementById('totalEmployees');
    const totalPayrollEl = document.getElementById('totalPayroll');
    const pendingCountEl = document.getElementById('pendingCount');
    const approvedCountEl = document.getElementById('approvedCount');
    const summaryEmployeesEl = document.getElementById('summaryEmployees');
    const summaryGrossEl = document.getElementById('summaryGross');
    const summaryDeductionsEl = document.getElementById('summaryDeductions');
    const summaryNetEl = document.getElementById('summaryNet');
    
    if (totalEmployeesEl) totalEmployeesEl.textContent = totalEmployees;
    if (totalPayrollEl) totalPayrollEl.textContent = '₱' + totalPayroll.toLocaleString();
    if (pendingCountEl) pendingCountEl.textContent = pendingCount;
    if (approvedCountEl) approvedCountEl.textContent = approvedCount;
    
    // Summary card
    const grossTotal = allPayroll.reduce((sum, p) => sum + (p.gross_salary || 0), 0);
    const deductionsTotal = allPayroll.reduce((sum, p) => sum + (p.total_deduction || 0), 0);
    
    if (summaryEmployeesEl) summaryEmployeesEl.textContent = totalEmployees;
    if (summaryGrossEl) summaryGrossEl.textContent = '₱' + grossTotal.toLocaleString();
    if (summaryDeductionsEl) summaryDeductionsEl.textContent = '₱' + deductionsTotal.toLocaleString();
    if (summaryNetEl) summaryNetEl.textContent = '₱' + totalPayroll.toLocaleString();
    
    // Update chart
    updateChart();
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
    
    renderTable(filtered);
}

function filterByStatus(e) {
    const status = e.target.value;
    if (status === 'all') {
        currentFilter = 'All';
    } else {
        currentFilter = status;
    }
    currentPage = 1;
    applyFilters();
}

function renderTable(records) {
    const tbody = document.getElementById("payrollTableBody");
    if (!tbody) return;

    // Calculate pagination
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, records.length);
    const pageRecords = records.slice(startIndex, endIndex);
    
    totalItems = records.length;

    if (records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:20px;">
                    No payroll records found.
                </td>
            </tr>
        `;
        updatePagination();
        return;
    }

    tbody.innerHTML = pageRecords.map(row => {
        const employee = allEmployees.find(emp => emp.id == row.employee_id);
        const full_name = employee ? employee.full_name : "Unknown";

        let statusBadge = '';
        if (row.status === 'Pending') statusBadge = '<span class="status-pending">Pending</span>';
        else if (row.status === 'Approved') statusBadge = '<span class="status-approved">Approved</span>';
        else if (row.status === 'Rejected') statusBadge = '<span class="status-rejected">Rejected</span>';
        else statusBadge = '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs">Draft</span>';
        
        let actionBtn = '';
        if (row.status === 'Approved') {
            actionBtn = `<button onclick="downloadPayslip(${row.id})" class="download-btn text-sm"><i class="fa-solid fa-download"></i> Payslip</button>`;
        } else if (row.status === 'Pending') {
            actionBtn = '<span class="text-gray-400 text-xs">Awaiting Approval</span>';
        } else {
            actionBtn = '<span class="text-red-400 text-xs">Rejected</span>';
        }
        
        return `
            <tr class="border-b hover:bg-gray-50">
                <td class="py-3 px-6 font-medium">${escapeHtml(full_name)}</td>
                <td class="py-3">${row.period || 'N/A'}</td>
                <td class="py-3">₱${(row.gross_salary || 0).toLocaleString()}</td>
                <td class="py-3">₱${(row.total_deduction || 0).toLocaleString()}</td>
                <td class="py-3 font-medium text-[#b0303b]">₱${(row.net_salary || 0).toLocaleString()}</td>
                <td class="py-3">${statusBadge}</td>
                <td class="py-3 text-center">${actionBtn}</td>
            </tr>
        `;
    }).join('');

    updatePagination();
}

function updatePagination() {
    const pagination = document.getElementById("paginationControls");
    if (!pagination) return;

    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    const startItem = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);

    const pageInfo = document.getElementById("paginationInfo");
    if (pageInfo) {
        if (totalItems === 0) {
            pageInfo.textContent = "Showing 0-0 of 0";
        } else {
            pageInfo.textContent = `Showing ${startItem}–${endItem} of ${totalItems}`;
        }
    }

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';
    html += `<button class="w-8 h-8 rounded-lg border ${currentPage === 1 ? 'bg-gray-100 text-gray-400' : 'hover:bg-[#b0303b] hover:text-white'}" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left text-xs"></i></button>`;
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button class="w-8 h-8 rounded-lg ${i === currentPage ? 'bg-[#b0303b] text-white' : 'border hover:bg-[#d98989] hover:text-white'}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<span class="w-8 h-8 flex items-center justify-center">...</span>`;
        }
    }
    
    html += `<button class="w-8 h-8 rounded-lg border ${currentPage === totalPages ? 'bg-gray-100 text-gray-400' : 'hover:bg-[#b0303b] hover:text-white'}" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}><i class="fa-solid fa-chevron-right text-xs"></i></button>`;
    
    pagination.innerHTML = html;
}

window.changePage = function(page) {
    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    applyFilters();
};

// ============================================
// PAYSLIP FUNCTIONS
// ============================================

window.downloadPayslip = function(id) {
    const payroll = allPayroll.find(p => p.id === id);
    if (!payroll) return;
    
    if (payroll.status !== 'Approved') {
        alert('This payroll is not yet approved. Payslip cannot be generated.');
        return;
    }
    
    const employee = allEmployees.find(e => e.id === payroll.employee_id);
    
    const payslipData = {
        id: payroll.id,
        employee_id: payroll.employee_id,
        employee_name: employee?.full_name || 'Unknown',
        position: employee?.position || 'Employee',
        period: payroll.period,
        gross: payroll.gross_salary || 0,
        sss: payroll.sss || 0,
        philhealth: payroll.philhealth || 0,
        pagibig: payroll.pagibig || 0,
        total_deductions: payroll.total_deduction || 0,
        net: payroll.net_salary || 0
    };
    
    const payslips = JSON.parse(sessionStorage.getItem('payslips') || '{}');
    payslips[id] = payslipData;
    sessionStorage.setItem('payslips', JSON.stringify(payslips));
    
    window.open(`payslip.html?id=${id}`, '_blank');
};

// ============================================
// CHART FUNCTIONS
// ============================================

function updateChart() {
    const canvas = document.getElementById('payrollChart');
    if (!canvas) return;
    
    // Destroy existing chart if it exists
    if (window.payrollChartInstance) {
        window.payrollChartInstance.destroy();
    }
    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthlyData = new Array(12).fill(0);
    
    allPayroll.forEach(p => {
        if (p.period) {
            const monthIndex = months.findIndex(m => p.period.includes(m));
            if (monthIndex !== -1) {
                monthlyData[monthIndex] += p.net_salary || 0;
            }
        }
    });
    
    window.payrollChartInstance = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Net Payroll',
                data: monthlyData,
                backgroundColor: '#b0303b',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₱' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

window.logout = function() {
    localStorage.clear();
    window.location.href = 'index.html';
};