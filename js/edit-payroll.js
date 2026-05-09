console.log("EDIT PAYROLL JS LOADED");

let currentPayroll = null;
let deductionCount = 0;

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Get payroll ID from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const payrollId = parseInt(urlParams.get('id'));

        if (!payrollId) {
            alert("Payroll ID not provided");
            window.location.href = "payroll.html";
            return;
        }

        // Load existing payroll data
        const payroll = await Database.getPayrollById(payrollId);

        if (!payroll) {
            alert("Payroll record not found");
            window.location.href = "payroll.html";
            return;
        }

        currentPayroll = payroll;
        
        // Populate form with existing data
        document.getElementById("payrollId").value = payroll.id;
        
        // Load employee name
        const employee = await Database.getEmployeeById(payroll.employee_id);
        if (employee) {
            document.getElementById("employeeName").textContent = employee.full_name;
        }
        
        document.getElementById("payrollPeriod").textContent = payroll.period;
        document.getElementById("grossSalary").value = payroll.gross_salary;
        
        // Set SSS, Pag-IBIG, Philhealth, Admin Pay Rate if they exist
        if (payroll.sss !== undefined) {
            document.getElementById("sss").value = payroll.sss;
        }
        if (payroll.pagibig !== undefined) {
            document.getElementById("pagibig").value = payroll.pagibig;
        }
        if (payroll.philhealth !== undefined) {
            document.getElementById("philhealth").value = payroll.philhealth;
        }
        if (payroll.admin_pay_rate !== undefined) {
            document.getElementById("adminPayRate").value = payroll.admin_pay_rate;
        }
        
        document.getElementById("totalDeduction").value = payroll.total_deduction;
        document.getElementById("netSalary").value = payroll.net_salary;
        document.getElementById("status").value = payroll.status;
        
        // Load additional deductions if they exist
        if (payroll.additional_deductions && payroll.additional_deductions.length > 0) {
            const deductionsContainer = document.getElementById("additionalDeductions");
            payroll.additional_deductions.forEach(deduction => {
                addDeductionRow(deduction.name, deduction.amount);
            });
        }
        
    } catch (error) {
        console.error("Error initializing:", error);
    }

    const form = document.getElementById("payrollForm");
    if (form) {
        form.addEventListener("submit", function(e) { handlePayrollFormSubmit(e); });
    }
    
    // Auto-calculate net salary when gross or deduction changes
    const grossInput = document.getElementById("grossSalary");
    const sssInput = document.getElementById("sss");
    const pagibigInput = document.getElementById("pagibig");
    const philhealthInput = document.getElementById("philhealth");
    const adminPayRateInput = document.getElementById("adminPayRate");
    
    if (grossInput) {
        grossInput.addEventListener("input", calculateNetSalary);
    }
    if (sssInput) {
        sssInput.addEventListener("input", calculateNetSalary);
    }
    if (pagibigInput) {
        pagibigInput.addEventListener("input", calculateNetSalary);
    }
    if (philhealthInput) {
        philhealthInput.addEventListener("input", calculateNetSalary);
    }
    if (adminPayRateInput) {
        adminPayRateInput.addEventListener("input", calculateNetSalary);
    }
});

// Add deduction row for additional deductions
window.addDeductionRow = function(name = '', amount = 0) {
    const container = document.getElementById("additionalDeductions");
    const rowId = deductionCount++;
    
    const rowHTML = `
        <div class="deduction-row" id="deductionRow${rowId}" style="display: flex; gap: 5px; margin-bottom: 5px;">
            <input type="text" placeholder="Deduction Name" class="deduction-name" value="${name}" style="flex: 2; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
            <input type="number" placeholder="Amount" class="deduction-amount" value="${amount}" min="0" step="0.01" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
            <button type="button" onclick="removeDeductionRow(${rowId})" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">X</button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', rowHTML);
};

// Remove deduction row
window.removeDeductionRow = function(rowId) {
    const row = document.getElementById("deductionRow" + rowId);
    if (row) {
        row.remove();
    }
    calculateNetSalary();
};

function calculateNetSalary() {
    const gross = parseFloat(document.getElementById("grossSalary").value) || 0;
    const adminPayRate = parseFloat(document.getElementById("adminPayRate").value) || 0;
    
    // Get mandatory deductions
    const sss = parseFloat(document.getElementById("sss").value) || 0;
    const pagibig = parseFloat(document.getElementById("pagibig").value) || 0;
    const philhealth = parseFloat(document.getElementById("philhealth").value) || 0;
    
    // Get additional deductions
    const deductionRows = document.querySelectorAll('.deduction-row');
    let additionalDeductions = 0;
    deductionRows.forEach(row => {
        const amount = parseFloat(row.querySelector('.deduction-amount').value) || 0;
        additionalDeductions += amount;
    });
    
    const totalDeduction = sss + pagibig + philhealth + additionalDeductions;
    document.getElementById("totalDeduction").value = totalDeduction.toFixed(2);
    
    // Net salary = Gross + Admin Pay Rate - Total Deductions
    const net = gross + adminPayRate - totalDeduction;
    document.getElementById("netSalary").value = net.toFixed(2);
}

async function handlePayrollFormSubmit(e) {
    e.preventDefault();

    const payrollId = parseInt(document.getElementById("payrollId").value);
    const grossSalary = parseFloat(document.getElementById("grossSalary").value);
    const sss = parseFloat(document.getElementById("sss").value) || 0;
    const pagibig = parseFloat(document.getElementById("pagibig").value) || 0;
    const philhealth = parseFloat(document.getElementById("philhealth").value) || 0;
    const adminPayRate = parseFloat(document.getElementById("adminPayRate").value) || 0;
    const totalDeduction = parseFloat(document.getElementById("totalDeduction").value);
    const netSalary = parseFloat(document.getElementById("netSalary").value);
    const status = document.getElementById("status").value;

    // Collect additional deductions
    const additionalDeductions = [];
    const deductionRows = document.querySelectorAll('.deduction-row');
    deductionRows.forEach(row => {
        const name = row.querySelector('.deduction-name').value;
        const amount = parseFloat(row.querySelector('.deduction-amount').value) || 0;
        if (name && amount > 0) {
            additionalDeductions.push({ name: name, amount: amount });
        }
    });

    const updatedRecord = {
        id: payrollId,
        employee_id: currentPayroll.employee_id,
        period: currentPayroll.period,
        gross_salary: grossSalary,
        sss: sss,
        pagibig: pagibig,
        philhealth: philhealth,
        admin_pay_rate: adminPayRate,
        total_deduction: totalDeduction,
        net_salary: netSalary,
        status: status,
        additional_deductions: additionalDeductions
    };

    // DEBUG: Log the update to diagnose issues
    console.log("=== PAYROLL UPDATE DEBUG ===");
    console.log("Original payroll:", JSON.stringify(currentPayroll, null, 2));
    console.log("Updated record being saved:", JSON.stringify(updatedRecord, null, 2));
    console.log("Keys in original:", Object.keys(currentPayroll));
    console.log("Keys in updated:", Object.keys(updatedRecord));

    try {
        await Database.updatePayroll(updatedRecord);
        alert("Payroll updated successfully!");
        window.location.href = "payroll.html";
    } catch (error) {
        console.error("Error updating payroll:", error);
        alert("Error updating payroll. Please try again.");
    }
}

window.goBack = function() {
    window.location.href = "payroll.html";
};

