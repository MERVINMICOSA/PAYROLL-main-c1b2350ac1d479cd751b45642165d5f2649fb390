let selectedEmployeeId = null;

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Get employee_id from URL parameter and convert to number
        const urlParams = new URLSearchParams(window.location.search);
        const employeeId = parseInt(urlParams.get('employee_id'));
        
        if (employeeId) {
            await autoSelectEmployee(employeeId);
        } else {
            // No employee specified, redirect back
            alert("Please select an employee from the attendance page");
            window.location.href = "attendanceSadmin.html";
            return;
        }
        
        // Add one empty daily entry by default with current date
        const today = new Date().toISOString().split('T')[0];
        addDailyEntry(today, '', '');
    } catch (error) {
        console.error("Error initializing:", error);
    }

    const form = document.getElementById("attendanceForm");
    if (form) {
        form.addEventListener("submit", saveAttendance);
    }
});

// Auto-select employee from URL parameter
async function autoSelectEmployee(employeeId) {
    try {
        const employee = await Database.getEmployeeById(employeeId);
        if (employee) {
            selectedEmployeeId = employee.id;
            document.getElementById("employeeName").textContent = employee.full_name;
        } else {
            alert("Employee not found");
            window.location.href = "attendanceSadmin.html";
        }
    } catch (error) {
        console.error("Error auto-selecting employee:", error);
    }
}

// Toggle admin pay rate input visibility based on pay type selection
window.toggleAdminPayRate = function() {
    const payType = document.querySelector('input[name="payType"]:checked').value;
    const adminPayRateContainer = document.getElementById("adminPayRateContainer");
    const adminPayRateInput = document.getElementById("adminPayRate");
    
    if (payType === "admin") {
        adminPayRateContainer.style.display = "block";
        adminPayRateInput.required = true;
    } else {
        adminPayRateContainer.style.display = "none";
        adminPayRateInput.required = false;
        adminPayRateInput.value = "";
    }
};

// Add daily attendance entry (pre-filled with date, hours worked, overtime)
window.addDailyEntry = function(date = '', hoursWorked = '', overtime = '') {
    const container = document.getElementById("dailyEntries");
    
    // If there's already an entry, don't add another one
    if (document.getElementById("dailyEntry0")) {
        return;
    }
    
    const entryHTML = `
        <div class="daily-entry" id="dailyEntry0">
            <input type="date" id="entryDate0" value="${date}" required>
            <input type="number" id="entryHoursWorked0" value="${hoursWorked}" placeholder="Hours Worked" min="0" step="0.5" required>
            <input type="number" id="entryOvertime0" value="${overtime}" placeholder="Overtime" min="0" step="0.5" required>
        </div>
    `;
    
    container.innerHTML = entryHTML;
};

// Collect daily attendance data
function collectDailyAttendance() {
    const dateEl = document.getElementById("entryDate0");
    const hoursWorkedEl = document.getElementById("entryHoursWorked0");
    const overtimeEl = document.getElementById("entryOvertime0");
    
    if (dateEl && dateEl.value) {
        const payType = document.querySelector('input[name="payType"]:checked').value;
        const adminPayRateEl = document.getElementById("adminPayRate");
        
        const record = {
            date: dateEl.value,
            hours_worked: parseFloat(hoursWorkedEl.value) || 8,
            overtime: parseFloat(overtimeEl.value) || 0,
            pay_type: payType
        };
        
        // Add admin pay rate if admin pay is selected
        if (payType === "admin" && adminPayRateEl && adminPayRateEl.value) {
            record.admin_pay_rate = parseFloat(adminPayRateEl.value);
        }
        
        return [record];
    }
    
    return [];
}

async function saveAttendance(e) {
    e.preventDefault();

    if (!selectedEmployeeId) {
        alert("Please select an employee");
        return;
    }

    // Collect daily attendance
    const daily_attendance = collectDailyAttendance();
    
    if (daily_attendance.length === 0 || !daily_attendance[0].date) {
        alert("Please enter attendance details");
        return;
    }

    // Calculate total hours and OT from daily attendance
    let totalHours = 0;
    let totalOT = 0;
    daily_attendance.forEach(record => {
        totalHours += record.hours_worked || 0;
        totalOT += record.overtime || 0;
    });

    // Get pay type from radio buttons
    const payType = document.querySelector('input[name="payType"]:checked').value;

    // Get the date from the daily entry
    const selectedDate = daily_attendance[0].date;
    
    // Generate payroll period (e.g., "January 2025")
    const dateObj = new Date(selectedDate);
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    const payrollPeriod = `${monthNames[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

    try {
        // Check if attendance record already exists for this employee
        const allAttendance = await Database.getAllAttendance();
        const existingRecord = allAttendance.find(a => Number(a.employee_id) === Number(selectedEmployeeId));
        
        if (existingRecord) {
            // Update existing record
            let existingDaily = existingRecord.daily_attendance || [];
            
            // Remove the existing record for this date if it exists
            existingDaily = existingDaily.filter(r => r.date !== selectedDate);
            
            // Add the new record
            existingDaily.push(daily_attendance[0]);
            
            // Recalculate totals
            let totalHoursUpdated = 0;
            let totalOTUpdated = 0;
            existingDaily.forEach(record => {
                totalHoursUpdated += record.hours_worked || 0;
                totalOTUpdated += record.overtime || 0;
            });
            
            const updatedRecord = {
                id: existingRecord.id,
                employee_id: selectedEmployeeId,
                payroll_period: payrollPeriod,
                days_present: existingDaily.length,
                hours_attended: totalHoursUpdated,
                ot_hours: totalOTUpdated,
                daily_attendance: existingDaily
            };
            
            await Database.updateAttendance(updatedRecord);
            alert("Attendance saved successfully!");
        } else {
            // Create new attendance record
            // Get existing attendance to generate new ID
            const newId = allAttendance.length > 0 ? Math.max(...allAttendance.map(a => a.id)) + 1 : 1;

            const newRecord = {
                id: newId,
                employee_id: selectedEmployeeId,
                payroll_period: payrollPeriod,
                days_present: 1,
                hours_attended: totalHours,
                ot_hours: totalOT,
                daily_attendance: daily_attendance
            };

            await Database.addAttendance(newRecord);
            alert("Attendance saved successfully!");
        }
        
        window.location.href = "attendanceSadmin.html";
    } catch (error) {
        console.error("Error saving attendance:", error);
        alert("Error saving attendance. Please try again.");
    }
}

