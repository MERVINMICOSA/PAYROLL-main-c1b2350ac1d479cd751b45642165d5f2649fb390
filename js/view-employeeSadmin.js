// View Employee Sadmin Module
console.log("VIEW EMPLOYEE SADMIN JS LOADED");

window.addEventListener("DOMContentLoaded", async () => {
    // Get employee ID from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const employeeId = parseInt(urlParams.get('id'));

    if (!employeeId) {
        alert("Employee ID not provided");
        window.location.href = "employeesSadmin.html";
        return;
    }

    // Load employee data from API
    const response = await fetch(`/api/employee.php?id=${employeeId}`, {
        credentials: 'include'
    });

    if (!response.ok) {
        if (response.status === 401) {
            window.location.href = 'index.html';
            return;
        }
        alert("Employee not found");
        window.location.href = "employeesSadmin.html";
        return;
    }

    const employee = await response.json();

    if (!employee || employee.error) {
        alert("Employee not found");
        window.location.href = "employeesSadmin.html";
        return;
    }

    // Display employee details
    const detailsContainer = document.getElementById("employeeDetails");
    
    const statusClass = employee.status === "Active" ? "status-active" : "status-inactive";
    
    // Format currency helper
    const formatCurrency = (value) => {
        return '₱' + (parseFloat(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    
    // Format date helper
    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };
    
    detailsContainer.innerHTML = `
        <!-- Personal Information -->
        <fieldset class="detail-section">
            <legend>Personal Information</legend>
            <div class="detail-item">
                <span class="detail-label">Full Name:</span>
                <span class="detail-value">${employee.full_name || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email:</span>
                <span class="detail-value">${employee.email || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">${employee.phone || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Birth Date:</span>
                <span class="detail-value">${formatDate(employee.birth_date)}</span>
            </div>
        </fieldset>
        
        <!-- Employment Details -->
        <fieldset class="detail-section">
            <legend>Employment Details</legend>
            <div class="detail-item">
                <span class="detail-label">Employee ID:</span>
                <span class="detail-value">#${employee.id}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Position:</span>
                <span class="detail-value">${employee.position || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Department:</span>
                <span class="detail-value">${employee.department || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Employment Type:</span>
                <span class="detail-value">${employee.employment_type || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Hire Date:</span>
                <span class="detail-value">${formatDate(employee.hire_date)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status:</span>
                <span class="detail-value ${statusClass}">${employee.status || '-'}</span>
            </div>
        </fieldset>
        
        <!-- Compensation -->
        <fieldset class="detail-section">
            <legend>Compensation</legend>
            <div class="detail-item">
                <span class="detail-label">Base Salary:</span>
                <span class="detail-value">${formatCurrency(employee.base_salary)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Hourly Rate:</span>
                <span class="detail-value">${formatCurrency(employee.hourly_rate)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Admin Pay Rate:</span>
                <span class="detail-value">${formatCurrency(employee.admin_pay_rate)}</span>
            </div>
        </fieldset>
        
        <!-- Government Numbers -->
        <fieldset class="detail-section">
            <legend>Government Numbers</legend>
            <div class="detail-item">
                <span class="detail-label">SSS:</span>
                <span class="detail-value">${employee.sss || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">PhilHealth:</span>
                <span class="detail-value">${employee.philhealth || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Pag-IBIG:</span>
                <span class="detail-value">${employee.pagibig || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">TIN:</span>
                <span class="detail-value">${employee.tin || '-'}</span>
            </div>
        </fieldset>
        
        <!-- Emergency Contact -->
        <fieldset class="detail-section">
            <legend>Emergency Contact</legend>
            <div class="detail-item">
                <span class="detail-label">Contact Person:</span>
                <span class="detail-value">${employee.emergency_name || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Relationship:</span>
                <span class="detail-value">${employee.emergency_relation || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">${employee.emergency_phone || '-'}</span>
            </div>
        </fieldset>
    `;

    // Edit button - navigate to edit page with employee ID
    const editBtn = document.getElementById("editBtn");
    if (editBtn) {
        editBtn.addEventListener("click", () => {
            window.location.href = `edit-employeeSadmin.html?id=${employeeId}`;
        });
    }
});