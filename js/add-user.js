// Add User Module
console.log("ADD USER JS LOADED");

window.addEventListener("DOMContentLoaded", async () => {
    // Initialize database
    await Database.ensureDatabaseReady();

    // BACK BUTTON
    window.goBack = function() {
        window.location.href = "user-mgmt.html";
    };

    // ROLE DROPDOWN
    const roleInput = document.getElementById("role");
    const roleDropdownMenu = document.getElementById("roleDropdownMenu");

    if (roleInput && roleDropdownMenu) {
        roleInput.addEventListener("click", () => {
            roleDropdownMenu.style.display =
                roleDropdownMenu.style.display === "block" ? "none" : "block";
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!roleInput.contains(e.target) && !roleDropdownMenu.contains(e.target)) {
                roleDropdownMenu.style.display = "none";
            }
        });
    }

    window.selectRole = function(role) {
        document.getElementById("role").value = role;
        roleDropdownMenu.style.display = "none";
    };

    // STATUS DROPDOWN
    const statusInput = document.getElementById("status");
    const statusDropdownMenu = document.getElementById("statusDropdownMenu");

    if (statusInput && statusDropdownMenu) {
        statusInput.addEventListener("click", () => {
            statusDropdownMenu.style.display =
                statusDropdownMenu.style.display === "block" ? "none" : "block";
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!statusInput.contains(e.target) && !statusDropdownMenu.contains(e.target)) {
                statusDropdownMenu.style.display = "none";
            }
        });
    }

    window.selectStatus = function(status) {
        document.getElementById("status").value = status;
        statusDropdownMenu.style.display = "none";
    };

    // FORM SUBMIT
    const form = document.getElementById("userForm");

    if (!form) {
        console.error("Form not found");
        return;
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        console.log("FORM SUBMITTED");

        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value.trim();
        const role = document.getElementById("role").value;
        const status = document.getElementById("status").value;

        // Validation
        if (!username || !password || !role || !status) {
            alert("Please fill in all fields");
            return;
        }

        // Check if username already exists
        const existingUsers = await Database.getAllUsers();
        const usernameExists = existingUsers.some(u => u.username.toLowerCase() === username.toLowerCase());
        
        if (usernameExists) {
            alert("Username already exists. Please choose a different username.");
            return;
        }

        // Generate new ID
        const newId = existingUsers.length > 0 ? Math.max(...existingUsers.map(u => u.id)) + 1 : 1;

        // Create new user
        const newUser = {
            id: newId,
            username,
            password,
            role,
            status
        };

        try {
            // Save to database
            await Database.addUser(newUser);
            alert("User added successfully!");
            window.location.href = "user-mgmt.html";
        } catch (error) {
            console.error("Error adding user:", error);
            alert("Error adding user. Please try again.");
        }
    });
});

