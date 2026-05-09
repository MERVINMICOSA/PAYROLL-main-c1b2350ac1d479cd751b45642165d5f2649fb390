console.log("VIEW EMPLOYEE JS LOADED");

window.addEventListener("DOMContentLoaded", async () => {
    // Initialize database
    await Database.ensureDatabaseReady();

    // Get employee ID from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const employeeId = parseInt(urlParams.get('id'));

    if (!employeeId) {
        alert("Employee ID not provided");
        window.location.href = "employees.html";
        return;
    }

    // Load employee data - try API first, then fallback to IndexedDB
    let employee = null;
    
    // Try to fetch from API
    try {
        const response = await fetch(`/api/employee.php?id=${employeeId}`, {
            credentials: 'include'
        });
        if (response.ok) {
            employee = await response.json();
            console.log("Loaded employee from server API");
        }
    } catch (apiError) {
        console.log("Could not fetch from API, trying local database:", apiError);
    }
    
    // Fallback to IndexedDB if API fails
    if (!employee) {
        employee = await Database.getEmployeeById(employeeId);
        console.log("Loaded employee from local IndexedDB");
    }

    if (!employee) {
        alert("Employee not found");
        window.location.href = "employees.html";
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
    
    // Update header information
    document.getElementById("fullName").textContent = employee.full_name || '-';
    document.getElementById("position").textContent = employee.position || '-';
    document.getElementById("employeeId").textContent = `#${employee.id || '-'}`;
    document.getElementById("status").textContent = employee.status || '-';
    
    // Personal Information
    document.getElementById("viewFullName").textContent = employee.full_name || '-';
    document.getElementById("email").textContent = employee.email || '-';
    document.getElementById("phone").textContent = employee.phone || '-';
    document.getElementById("birthDate").textContent = formatDate(employee.birth_date);
    
    // Employment Details
    document.getElementById("viewPosition").textContent = employee.position || '-';
    document.getElementById("department").textContent = employee.department || '-';
    document.getElementById("employmentType").textContent = employee.employment_type || '-';
    document.getElementById("hireDate").textContent = formatDate(employee.hire_date);
    
    // Compensation
    document.getElementById("baseSalary").textContent = formatCurrency(employee.base_salary);
    document.getElementById("hourlyRate").textContent = formatCurrency(employee.hourly_rate);
    document.getElementById("adminPayRate").textContent = formatCurrency(employee.admin_pay_rate);
    
    // Government Numbers
    document.getElementById("sss").textContent = employee.sss || '-';
    document.getElementById("philhealth").textContent = employee.philhealth || '-';
    document.getElementById("pagibig").textContent = employee.pagibig || '-';
    document.getElementById("tin").textContent = employee.tin || '-';
    
    // Emergency Contact
    document.getElementById("emergencyName").textContent = employee.emergency_name || '-';
    document.getElementById("emergencyRelation").textContent = employee.emergency_relation || '-';
    document.getElementById("emergencyPhone").textContent = employee.emergency_phone || '-';

    // Edit button - navigate to edit page with employee ID
    const editBtn = document.getElementById("editBtn");
    if (editBtn) {
        editBtn.addEventListener("click", () => {
            window.location.href = `edit-employee.html?id=${employeeId}`;
        });
    }
});

