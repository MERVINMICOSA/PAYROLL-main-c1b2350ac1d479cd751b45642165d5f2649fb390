console.log("ADD EMPLOYEE JS LOADED");

window.addEventListener("DOMContentLoaded", async () => {
    // Initialize database
    await Database.ensureDatabaseReady();

    // BACK BUTTON
    window.goBack = function() {
        window.location.href = "employeesSadmin.html";
    };

    // DROPDOWN
    const empTypeInput = document.getElementById("employmentType");
    const dropdownMenu = document.getElementById("dropdownMenu");

    if (empTypeInput && dropdownMenu) {
        empTypeInput.addEventListener("click", () => {
            dropdownMenu.style.display =
                dropdownMenu.style.display === "block" ? "none" : "block";
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!empTypeInput.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.style.display = "none";
            }
        });
    }

    window.selectType = function(type) {
        document.getElementById("employmentType").value = type;
        dropdownMenu.style.display = "none";
    };

    // FORM SUBMIT
    const form = document.getElementById("employeeForm");

    if (!form) {
        console.error("Form not found");
        return;
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        console.log("FORM SUBMITTED");

        const full_name = document.getElementById("fullName").value.trim();
        const position = document.getElementById("position").value.trim();
        const employment_type = document.getElementById("employmentType").value;
        const base_salary = document.getElementById("salary").value;
        const email = document.getElementById("email").value.trim();

        // Validation
        if (!full_name || !position || !employment_type || !base_salary || !email) {
            alert("Please fill in all fields");
            return;
        }

        // Generate new ID
        const employees = await Database.getAllEmployees();
        const newId = employees.length > 0 ? Math.max(...employees.map(e => e.id)) + 1 : 1001;

        // Create new employee
        const newEmployee = {
            id: newId,
            full_name,
            position,
            employment_type,
            base_salary: parseFloat(base_salary),
            email,
            status: "Active"
        };

        try {
            // Save to database
            await Database.addEmployee(newEmployee);
            alert("Employee added successfully!");
            window.location.href = "employeesSadmin.html";
        } catch (error) {
            console.error("Error adding employee:", error);
            alert("Error adding employee. Please try again.");
        }
    });
});

