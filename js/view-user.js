// View User Module
console.log("VIEW USER JS LOADED");

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

    // BACK BUTTON
    window.goBack = function() {
        window.location.href = "user-mgmt.html";
    };

    // Display user details
    const detailsContainer = document.getElementById("userDetails");
    
    const statusClass = user.status === "Active" ? "status-active" : "status-inactive";
    
    detailsContainer.innerHTML = `
        <div class="detail-item">
            <span class="detail-label">User ID</span>
            <span class="detail-value">#${user.id}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Status</span>
            <span class="detail-value ${statusClass}">${user.status}</span>
        </div>
        <div class="detail-item full-width">
            <span class="detail-label">Username</span>
            <span class="detail-value">${user.username}</span>
        </div>
        <div class="detail-item full-width">
            <span class="detail-label">Password</span>
            <span class="detail-value">••••••••</span>
        </div>
        <div class="detail-item full-width">
            <span class="detail-label">Role</span>
            <span class="detail-value">${user.role}</span>
        </div>
    `;

    // Edit button - navigate to edit page with user ID
    const editBtn = document.getElementById("editBtn");
    if (editBtn) {
        editBtn.addEventListener("click", () => {
            window.location.href = `edit-user.html?id=${userId}`;
        });
    }
});

