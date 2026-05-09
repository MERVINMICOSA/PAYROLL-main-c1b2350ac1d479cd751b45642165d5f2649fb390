console.log("VIEW ATTENDANCE SADMIN JS LOADED");

let currentAttendance = null;
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

window.addEventListener("DOMContentLoaded", async () => {
    // Initialize database
    await Database.ensureDatabaseReady();

    // Get attendance ID from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const attendanceId = parseInt(urlParams.get('id'));

    if (!attendanceId) {
        alert("Attendance ID not provided");
        window.location.href = "attendanceSadmin.html";
        return;
    }

    // Load attendance data
    const attendance = await Database.getAttendanceById(attendanceId);

    if (!attendance) {
        alert("Attendance record not found");
        window.location.href = "attendanceSadmin.html";
        return;
    }

    currentAttendance = attendance;

    // Parse payroll period to get month and year
    const periodMatch = attendance.payroll_period.match(/(\w+)\s+(\d{4})/);
    if (periodMatch) {
        const monthNames = ["January", "February", "March", "April", "May", "June", 
                         "July", "August", "September", "October", "November", "December"];
        currentMonth = monthNames.indexOf(periodMatch[1]);
        currentYear = parseInt(periodMatch[2]);
    }

    // Load employee data
    const employee = await Database.getEmployeeById(attendance.employee_id);
    const full_name = employee ? employee.full_name : "Unknown";
    const position = employee ? employee.position : "Unknown";

    // Calculate total hours worked and overtime from daily attendance
    const dailyAttendance = currentAttendance.daily_attendance || [];
    let totalHoursWorked = 0;
    let totalOvertime = 0;
    
    dailyAttendance.forEach(record => {
        if (record.hours_worked) {
            totalHoursWorked += parseFloat(record.hours_worked);
        }
        if (record.overtime) {
            totalOvertime += parseFloat(record.overtime);
        }
    });

    // Display attendance details
    const detailsContainer = document.getElementById("attendanceDetails");
    
    detailsContainer.innerHTML = `
        <div class="detail-item">
            <span class="detail-label">Attendance ID</span>
            <span class="detail-value">#${attendance.id}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Employee Name</span>
            <span class="detail-value">${full_name}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Position</span>
            <span class="detail-value">${position}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payroll Period</span>
            <span class="detail-value">${attendance.payroll_period}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Hours Attended</span>
            <span class="detail-value">${totalHoursWorked} hours</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Overtime Hours</span>
            <span class="detail-value">${totalOvertime} hours</span>
        </div>
    `;

    // Render calendar
    renderCalendar();

    // Edit button - navigate to edit page with attendance ID and selected date
    const editBtn = document.getElementById("editBtn");
    if (editBtn) {
        editBtn.addEventListener("click", () => {
            if (!currentSelectedDate) {
                alert("Please click on a day in the calendar to select it first, then click Edit.");
                return;
            }
            window.location.href = "edit-attendanceSadmin.html?id=" + attendanceId + "&date=" + currentSelectedDate;
        });
    }
});

// Track currently selected date
let currentSelectedDate = null;

// Calendar navigation
window.changeMonth = function(delta) {
    currentMonth += delta;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    } else if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    renderCalendar();
};

// Render calendar
function renderCalendar() {
    const monthNames = ["January", "February", "March", "April", "May", "June", 
                       "July", "August", "September", "October", "November", "December"];
    const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    
    // Update month display
    document.getElementById("currentMonth").textContent = `${monthNames[currentMonth]} ${currentYear}`;
    
    // Get first day of month and total days
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
    
    let calendarHTML = "";
    
    // Add day headers
    dayNames.forEach(day => {
        calendarHTML += `<div class="calendar-day-header">${day}</div>`;
    });
    
    // Add days from previous month
    for (let i = firstDay - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        calendarHTML += `<div class="calendar-day other-month">${day}</div>`;
    }
    
    // Get daily attendance data
    const dailyAttendance = currentAttendance.daily_attendance || [];
    const attendanceMap = {};
    dailyAttendance.forEach(record => {
        attendanceMap[record.date] = record;
    });
    
    // Add days of current month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const record = attendanceMap[dateStr];
        const dayOfWeek = new Date(currentYear, currentMonth, day).getDay();
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
        
        let classes = "calendar-day";
        
        // Check if day has hours_worked - mark GREEN
        if (record && record.hours_worked) {
            classes += " has-attendance"; // Green - has hours worked
        } 
        // No hours_worked - mark RED
        else {
            classes += " no-attendance"; // Red - no record or no hours
        }
        
        if (isWeekend) classes += " weekend";
        
        calendarHTML += `<div class="${classes}" onclick="showDayDetails('${dateStr}')">${day}</div>`;
    }
    
    // Add days from next month to fill grid
    const totalCells = firstDay + daysInMonth;
    const remainingCells = totalCells > 35 ? 42 - totalCells : 35 - totalCells;
    for (let i = 1; i <= remainingCells; i++) {
        calendarHTML += `<div class="calendar-day other-month">${i}</div>`;
    }
    
    document.getElementById("calendarGrid").innerHTML = calendarHTML;
}

// Show hours worked and overtime for selected day
window.showDayDetails = function(dateStr) {
    // Store the currently selected date
    currentSelectedDate = dateStr;
    
    const dailyAttendance = currentAttendance.daily_attendance || [];
    const record = dailyAttendance.find(r => r.date === dateStr);
    
    // Update selected day styling
    document.querySelectorAll('.calendar-day').forEach(el => el.classList.remove('selected'));
    const dayNum = parseInt(dateStr.split('-')[2]);
    const dayElements = document.querySelectorAll('.calendar-day:not(.other-month)');
    if (dayElements[dayNum - 1]) {
        dayElements[dayNum - 1].classList.add('selected');
    }
    
    const timeDetails = document.getElementById("timeDetails");
    const selectedDate = document.getElementById("selectedDate");
    const hoursWorked = document.getElementById("hoursWorked");
    const overtime = document.getElementById("overtime");
    const payType = document.getElementById("payType");
    const adminPayRateBox = document.getElementById("adminPayRateBox");
    const adminPayRate = document.getElementById("adminPayRate");
    
    // Format date for display
    const date = new Date(dateStr + "T00:00:00");
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    selectedDate.textContent = date.toLocaleDateString('en-US', options);
    
    if (record) {
        timeDetails.classList.add("show");
        hoursWorked.textContent = record.hours_worked !== undefined ? record.hours_worked + " hrs" : "--";
        overtime.textContent = record.overtime !== undefined ? record.overtime + " hrs" : "--";
        
        // Display pay type
        if (record.pay_type) {
            const payTypeDisplay = record.pay_type === "admin" ? "Admin Pay" : "Regular Pay";
            payType.textContent = payTypeDisplay;
            payType.style.color = record.pay_type === "admin" ? "#28a745" : "#333";
        } else {
            payType.textContent = "--";
            payType.style.color = "#333";
        }
        
        // Display admin pay rate if admin pay is selected
        if (record.pay_type === "admin" && record.admin_pay_rate) {
            adminPayRateBox.style.display = "block";
            adminPayRate.textContent = "₱" + record.admin_pay_rate + "/hr";
            adminPayRate.style.color = "#28a745";
        } else {
            adminPayRateBox.style.display = "none";
        }
    } else {
        timeDetails.classList.add("show");
        hoursWorked.textContent = "No Record";
        overtime.textContent = "No Record";
        payType.textContent = "--";
        payType.style.color = "#333";
        adminPayRateBox.style.display = "none";
    }
};

