// Edit User Module
console.log("EDIT USER JS LOADED");

window.addEventListener("DOMContentLoaded", async () => {
    // Initialize database
    await Database.ensureDatabaseReady();

    // Get user ID from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const userId = parseInt(urlParams.get('id'));

    if (!userId) {
        alert("User ID not provided");
        window.location.href = "user-mgmt.html";
        return;
    }

    // Load user data
    const user = await Database.getUserById(userId);

    if (!user) {
        alert("User not found");
        window.location.href = "user-mgmt.html";
        return;
    }

    // Populate form with user data
    document.getElementById("userId").value = user.id;
    document.getElementById("username").value = user.username;
    document.getElementById("password").value = user.password;
    document.getElementById("role").value = user.role;
    document.getElementById("status").value = user.status;

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

        const id = parseInt(document.getElementById("userId").value);
        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value.trim();
        const role = document.getElementById("role").value;
        const status = document.getElementById("status").value;

        // Validation
        if (!username || !password || !role || !status) {
            alert("Please fill in all fields");
            return;
        }

        // Check if username already exists (excluding current user)
        const existingUsers = await Database.getAllUsers();
        const usernameExists = existingUsers.some(u => u.username.toLowerCase() === username.toLowerCase() && u.id !== id);
        
        if (usernameExists) {
            alert("Username already exists. Please choose a different username.");
            return;
        }

        // Create updated user object
        const updatedUser = {
            id,
            username,
            password,
            role,
            status
        };

        try {
            // Update in database
            await Database.updateUser(updatedUser);
            alert("User updated successfully!");
            window.location.href = "user-mgmt.html";
        } catch (error) {
            console.error("Error updating user:", error);
            alert("Error updating user. Please try again.");
        }
    });
});

