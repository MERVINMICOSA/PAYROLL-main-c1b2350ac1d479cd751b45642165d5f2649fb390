// Payslip Module
window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        await loadPayslipData();
    } catch (error) {
        console.error("Error initializing payslip:", error);
    }
    
    // Setup button event listeners
    setupButtons();
});

async function loadPayslipData() {
    try {
        // Get payroll ID from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const payrollId = parseInt(urlParams.get('id'));
        
        let employee = null;
        let payData = null;
        
        if (payrollId) {
            // Load specific payroll record
            const payroll = await Database.getAllPayroll();
            payData = payroll.find(p => p.id === payrollId);
            
            if (payData) {
                // Get the employee for this payroll
                const employees = await Database.getAllEmployees();
                employee = employees.find(emp => emp.id === payData.employee_id);
            }
        }
        
        // Fallback: Get first employee and their payroll if no ID provided
        if (!employee || !payData) {
            const employees = await Database.getAllEmployees();
            const payroll = await Database.getAllPayroll();
            
            if (employees.length > 0 && payroll.length > 0) {
                employee = employees[0];
                payData = payroll.find(p => p.employee_id === employee.id) || payroll[0];
            }
        }

        if (employee && payData) {
            // Update the payslip with real data
            const infoBox = document.querySelector('.info-box');
            if (infoBox) {
                infoBox.innerHTML = `
                    <div><span class="label">SCHOOL NAME:</span> <span class="value">PhilTech GMA</span></div>
                    <div><span class="label">Payroll Period:</span> <span class="value">${payData.period}</span></div>
                    <div><span class="label">Employee Name:</span> <span class="value">${employee.full_name}</span></div>
                `;
            }

            // Update salary lines
            const lines = document.querySelectorAll('.line');
            if (lines.length >= 5) {
                // Base Salary
                lines[0].querySelector('.right').textContent = '₱' + parseFloat(employee.base_salary).toLocaleString();
                
                // Overtime Pay (calculate based on OT hours from attendance)
                const attendance = await Database.getAttendanceByEmployee(employee.id);
                const otHours = attendance.length > 0 ? (attendance[0].ot_hours || 0) : 0;
                const otPay = otHours * (employee.base_salary / 176); // Hourly rate
                lines[1].querySelector('.right').textContent = '₱' + otPay.toLocaleString();
                
                // Gross Salary
                const gross = parseFloat(payData.gross_salary);
                lines[2].querySelector('.right').textContent = '₱' + gross.toLocaleString();
                
                // Deductions
                const deductions = parseFloat(payData.total_deduction);
                lines[3].querySelector('.right').textContent = '₱' + deductions.toLocaleString();
                
                // Net Salary
                const net = parseFloat(payData.net_salary);
                lines[4].querySelector('.right').textContent = '₱' + net.toLocaleString();
            }
        }
    } catch (error) {
        console.error("Error loading payslip data:", error);
    }
}

function setupButtons() {
    const exportBtn = document.getElementById("exportBtn");
    const printBtn = document.getElementById("printBtn");
    const backBtn = document.getElementById("backBtn");

    if (exportBtn) {
        exportBtn.addEventListener("click", () => {
            alert("Exporting payslip to PDF...\n\nThis feature will generate a downloadable PDF of the payslip.");
        });
    }

    if (printBtn) {
        printBtn.addEventListener("click", () => {
            window.print();
        });
    }

    if (backBtn) {
        backBtn.addEventListener("click", () => {
            window.location.href = "payroll.html";
        });
    }
}

