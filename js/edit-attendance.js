console.log("EDIT ATTENDANCE JS LOADED");

let selectedEmployeeId = null;
let currentSelectedDate = null;

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Get attendance ID and selected date from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const attendanceId = parseInt(urlParams.get('id'));
        currentSelectedDate = urlParams.get('date');

        if (!attendanceId) {
            alert("Attendance ID not provided");
            window.location.href = "attendance.html";
            return;
        }

        // Load existing attendance data
        const attendance = await Database.getAttendanceById(attendanceId);

        if (!attendance) {
            alert("Attendance record not found");
            window.location.href = "attendance.html";
            return;
        }

        // Populate form with existing data
        document.getElementById("attendanceId").value = attendance.id;
        document.getElementById("selectedDate").value = currentSelectedDate || '';
        selectedEmployeeId = attendance.employee_id;
        
        // Load employee name
        const employee = await Database.getEmployeeById(attendance.employee_id);
        if (employee) {
            document.getElementById("employeeName").textContent = employee.full_name;
        }

        // Load existing daily attendance for the selected date
        const dailyAttendance = attendance.daily_attendance || [];
        const existingRecord = dailyAttendance.find(r => r.date === currentSelectedDate);
        
        if (existingRecord) {
            addDailyEntry(existingRecord.date, existingRecord.hours_worked, existingRecord.overtime);
            // Set the pay type radio button
            if (existingRecord.pay_type) {
                const payTypeRadio = document.querySelector(`input[name="payType"][value="${existingRecord.pay_type}"]`);
                if (payTypeRadio) {
                    payTypeRadio.checked = true;
                }
                // Show/hide admin pay rate input based on saved pay type
                toggleAdminPayRate();
                
                // Load admin pay rate if admin pay is selected
                if (existingRecord.pay_type === "admin" && existingRecord.admin_pay_rate) {
                    document.getElementById("adminPayRate").value = existingRecord.admin_pay_rate;
                }
            }
        } else if (currentSelectedDate) {
            addDailyEntry(currentSelectedDate, '', '');
        } else {
            addDailyEntry();
        }
    } catch (error) {
        console.error("Error initializing:", error);
    }

    const form = document.getElementById("attendanceForm");
    if (form) {
        form.addEventListener("submit", function(e) { handleAttendanceFormSubmit(e); });
    }
});

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

// Add daily attendance entry (pre-filled with existing data or empty for new)
window.addDailyEntry = function(date = '', hoursWorked = '', overtime = '') {
    const container = document.getElementById("dailyEntries");
    
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

async function handleAttendanceFormSubmit(e) {
    e.preventDefault();

    const attendanceId = parseInt(document.getElementById("attendanceId").value);
    const selectedDate = document.getElementById("selectedDate").value;

    if (!selectedDate) {
        alert("Please select a date");
        return;
    }

    // Collect daily attendance
    const daily_attendance = collectDailyAttendance();

    // Get existing attendance data
    const existingAttendance = await Database.getAttendanceById(attendanceId);
    let existingDaily = existingAttendance.daily_attendance || [];
    
    // Remove the existing record for this date if it exists
    existingDaily = existingDaily.filter(r => r.date !== selectedDate);
    
    // Add the new/updated record
    if (daily_attendance.length > 0 && daily_attendance[0].date) {
        existingDaily.push(daily_attendance[0]);
    }
    
    // Recalculate total hours worked and overtime from daily_attendance
    let totalHoursWorked = 0;
    let totalOvertime = 0;
    existingDaily.forEach(record => {
        if (record.hours_worked) {
            totalHoursWorked += parseFloat(record.hours_worked);
        }
        if (record.overtime) {
            totalOvertime += parseFloat(record.overtime);
        }
    });
    
    const updatedRecord = {
        id: attendanceId,
        employee_id: selectedEmployeeId,
        payroll_period: existingAttendance.payroll_period,
        days_present: existingDaily.length,
        hours_attended: totalHoursWorked,
        ot_hours: totalOvertime,
        daily_attendance: existingDaily
    };

    // DEBUG: Log the update to diagnose issues
    console.log("=== ATTENDANCE UPDATE DEBUG ===");
    console.log("Original attendance:", JSON.stringify(existingAttendance, null, 2));
    console.log("Updated record being saved:", JSON.stringify(updatedRecord, null, 2));
    console.log("Keys in original:", Object.keys(existingAttendance));
    console.log("Keys in updated:", Object.keys(updatedRecord));

    try {
        await Database.updateAttendance(updatedRecord);
        alert("Attendance updated successfully!");
        // Redirect back to view attendance page with the attendance ID
        window.location.href = `view-attendance.html?id=${attendanceId}`;
    } catch (error) {
        console.error("Error updating attendance:", error);
        alert("Error updating attendance. Please try again.");
    }
}

