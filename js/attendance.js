// js/attendance.js - UPDATED VERSION
// Handles attendance functionality with Excel-like tabs

console.log("ATTENDANCE JS LOADED - Excel Version");

// Pagination settings
const ITEMS_PER_PAGE = 10;
let currentPage = 1;
let totalItems = 0;
let allEmployees = [];
let allAttendance = [];

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to attendance and employee changes to auto-refresh
        Database.Events.on(DB_EVENTS.ATTENDANCE_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.ATTENDANCE_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.ATTENDANCE_DELETED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_DELETED, handleDataChange);
        
        // Initialize date tracker and tabs
        if (window.DateTracker) {
            window.DateTracker.init();
        }
        
        if (window.TeacherAssignment) {
            await window.TeacherAssignment.init();
        }
        
        loadAttendance();
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

    // Search functionality
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            searchAttendance(e.target.value);
        });
    }
});

// Handler for database changes - refreshes attendance data
function handleDataChange(data) {
    console.log("Attendance data change detected:", data);
    loadAttendance();
}

async function loadAttendance() {
    try {
        const attendance = await Database.getAllAttendance();
        const employees = await Database.getAllEmployees();

        allEmployees = employees;
        allAttendance = attendance;
        totalItems = employees.length;
        currentPage = 1;
        
        renderAttendance();
        updatePagination();
    } catch (err) {
        console.error("Attendance load error:", err);
    }
}

function renderAttendance() {
    const tbody = document.getElementById("attTbody");
    if (!tbody) return;
    
    tbody.innerHTML = "";

    if (allEmployees.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; padding:20px;">
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

    pageEmployees.forEach(employee => {
        // Find attendance record for this employee
        const empAttendance = allAttendance.find(a => Number(a.employee_id) === Number(employee.id));
        
        const hoursAttended = empAttendance ? (empAttendance.hours_attended || 0) : 0;
        const otHours = empAttendance ? (empAttendance.ot_hours || 0) : 0;
        const payrollPeriod = empAttendance ? empAttendance.payroll_period : "-";
        const attendanceId = empAttendance ? empAttendance.id : 0;

        // Get teacher assignment info
        let assignmentType = 'regular';
        if (window.TeacherAssignment) {
            const teacher = window.TeacherAssignment.getTeacherById(employee.id);
            if (teacher) {
                assignmentType = teacher.assignment;
            }
        }

        tbody.innerHTML += `
            <tr>
                <td class="name">
                    ${employee.full_name}
                    ${assignmentType === 'both' ? '<span class="ml-2 text-xs bg-purple-100 text-purple-600 px-2 py-1 rounded-full">SHS+CLG</span>' : ''}
                </td>
                <td>${hoursAttended}</td>
                <td>${otHours}</td>
                <td class="period">
                    <span>${payrollPeriod}</span>
                    ${empAttendance ? `<span class="cal" onclick="viewAttendance(${attendanceId})" style="cursor:pointer;">📅</span>` : ''}
                </td>
                <td>
                    <div class="action-icons">
                        <button class="icon-btn add-row-btn" onclick="addAttendanceForEmployee(${employee.id})" title="Add Attendance">➕</button>
                        ${empAttendance ? `
                        <button class="icon-btn" onclick="viewAttendanceCalendar(${attendanceId})" title="View Calendar">📊</button>
                        ` : ''}
                    </div>
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
            <button class="page-arrow" onclick="goToPage(1)" disabled>‹</button>
            <div class="page-info">No records</div>
            <button class="page-arrow" onclick="goToPage(1)" disabled>›</button>
        `;
        return;
    }

    pagination.innerHTML = `
        <button class="page-arrow" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>‹</button>
        <div class="page-info">${startItem}–${endItem} OF ${totalItems}</div>
        <button class="page-arrow" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>›</button>
    `;
}

window.goToPage = function(page) {
    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderAttendance();
    updatePagination();
};

window.editAttendance = function(id) {
    window.location.href = `edit-attendance.html?id=${id}`;
};

window.viewAttendance = function(id) {
    window.location.href = `view-attendance.html?id=${id}`;
};

window.viewAttendanceCalendar = function(id) {
    // Switch to calendar view in the Excel-style tabs
    const tabToShow = 'eda'; // Default to EDA tab
    if (window.switchTab) {
        window.switchTab(tabToShow);
        // Scroll to the calendar section
        document.getElementById('attendanceGridContainer').scrollIntoView({ behavior: 'smooth' });
    }
};

window.deleteAttendanceRecord = async function(id) {
    if (!confirm("Are you sure you want to delete this attendance record?")) return;

    try {
        await Database.deleteAttendance(id);
        alert("Attendance record deleted successfully!");
        loadAttendance();
    } catch (error) {
        console.error("Error deleting attendance:", error);
        alert("Error deleting attendance record");
    }
};

// Search function - searches through all employees
window.searchAttendance = function(query) {
    query = query.toLowerCase();
    Database.getAllEmployees()
        .then(employees => {
            Database.getAllAttendance()
                .then(attendance => {
                    const filtered = employees.filter(emp => {
                        const name = emp.full_name.toLowerCase();
                        return name.includes(query);
                    });
                    
                    allEmployees = filtered;
                    allAttendance = attendance;
                    totalItems = filtered.length;
                    currentPage = 1;
                    
                    renderAttendance();
                    updatePagination();
                });
        });
};

// Add attendance for a specific employee
window.addAttendanceForEmployee = function(employeeId) {
    window.location.href = `add-attendance.html?employee_id=${employeeId}`;
};

// ============================================
// EXCEL-STYLE TAB FUNCTIONS
// ============================================

// These functions are now handled by attendance-tabs.js
// But we keep them here for backward compatibility

window.switchTab = function(tabId) {
    if (window.AttendanceTabs && window.AttendanceTabs.switchTab) {
        window.AttendanceTabs.switchTab(tabId);
    } else {
        console.warn('AttendanceTabs not loaded yet');
    }
};

window.saveAllChanges = function() {
    if (window.AttendanceTabs && window.AttendanceTabs.saveAllChanges) {
        window.AttendanceTabs.saveAllChanges();
    }
};

window.exportToExcel = function() {
    if (window.AttendanceTabs && window.AttendanceTabs.exportToExcel) {
        window.AttendanceTabs.exportToExcel();
    }
};

window.calculateOT = function() {
    alert('Overtime calculation feature will be implemented based on Excel formulas');
};

window.applyToAll = function(value) {
    if (window.AttendanceTabs && window.AttendanceTabs.applyToAll) {
        window.AttendanceTabs.applyToAll(value);
    }
};

window.copyPreviousWeek = function() {
    if (window.AttendanceTabs && window.AttendanceTabs.copyPreviousWeek) {
        window.AttendanceTabs.copyPreviousWeek();
    }
};

window.changePeriod = function(direction) {
    if (window.AttendanceTabs && window.AttendanceTabs.changePeriod) {
        window.AttendanceTabs.changePeriod(direction);
    }
};

// Add to js/attendance.js - Undertime Detection

// Undertime detection function
async function checkUndertime(employeeId, date, actualHours) {
    try {
        // Get teacher loads from database
        const loadings = await Database.getAllTeacherLoadings();
        const employee = await Database.getEmployeeById(employeeId);
        
        // Only check for teachers
        if (employee.assignment !== 'shs_only' && employee.assignment !== 'college_only') {
            return { undertime: 0, deduction: 0 };
        }
        
        // Find loading for current semester/year
        const loading = loadings.find(l => 
            l.employee_id === employeeId && 
            l.semester === '1st Sem' && // You might want to make this dynamic
            l.school_year === '2025-2026'
        );
        
        if (!loading) return { undertime: 0, deduction: 0 };
        
        // Get day of week (0 = Sunday, 1 = Monday, etc.)
        const dayOfWeek = new Date(date).getDay();
        const dayMap = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        const requiredHours = loading[dayMap[dayOfWeek]] || 0;
        
        if (actualHours < requiredHours) {
            const undertime = requiredHours - actualHours;
            const hourlyRate = loading.rate || 0;
            const deduction = undertime * hourlyRate;
            
            // Send notification to accountant
            await fetch('https://your-render-app.onrender.com/api/notifications', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'undertime',
                    employeeId,
                    employeeName: employee.full_name,
                    date,
                    undertime,
                    deduction,
                    message: `${employee.full_name} was undertime by ${undertime} hours on ${new Date(date).toLocaleDateString()}`
                })
            });
            
            return { undertime, deduction };
        }
        
        return { undertime: 0, deduction: 0 };
        
    } catch (error) {
        console.error('Error checking undertime:', error);
        return { undertime: 0, deduction: 0 };
    }
}

// Modify your addAttendance function to include undertime check
window.addAttendanceForEmployee = async function(employeeId) {
    const date = prompt('Enter date (YYYY-MM-DD):');
    if (!date) return;
    
    const hours = parseFloat(prompt('Enter hours worked:'));
    if (isNaN(hours)) return;
    
    // Check undertime
    const { undertime, deduction } = await checkUndertime(employeeId, date, hours);
    
    const attendance = {
        employee_id: employeeId,
        date: date,
        hours_attended: hours,
        undertime: undertime,
        deduction: deduction,
        created_at: new Date().toISOString()
    };
    
    await Database.addAttendance(attendance);
    
    if (undertime > 0) {
        alert(`⚠️ Undertime detected: ${undertime} hours (₱${deduction.toFixed(2)} deduction)`);
    }
    
    loadAttendance();
};