// User Management Module

// Pagination settings
const ITEMS_PER_PAGE = 6;
let currentPage = 1;
let totalItems = 0;
let allUsers = [];

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to user changes to auto-refresh the list
        Database.Events.on(DB_EVENTS.USER_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.USER_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.USER_DELETED, handleDataChange);
        
        loadUsers();
    } catch (error) {
        console.error("Error initializing user management:", error);
    }
});

// Handler for database changes - refreshes user list
function handleDataChange(data) {
    console.log("User data change detected:", data);
    loadUsers();
}

async function loadUsers() {
    try {
        const users = await Database.getAllUsers();
        
        allUsers = users;
        totalItems = users.length;
        currentPage = 1;
        
        renderUsers();
        updatePagination();
    } catch (error) {
        console.error("Error loading users:", error);
    }
}

function renderUsers() {
    const tbody = document.getElementById("userTableBody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (allUsers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align:center; padding:20px;">
                    No users found.
                </td>
            </tr>
        `;
        return;
    }

    // Calculate pagination
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalItems);
    const pageUsers = allUsers.slice(startIndex, endIndex);

    pageUsers.forEach(user => {
        tbody.innerHTML += `
            <tr>
                <td>${user.username}</td>
                <td>${user.role}</td>
                <td>${user.status}</td>
                <td class="actions">
                    <button onclick="editUser(${user.id})">✏</button>
                    <button onclick="deleteUser(${user.id})">🗑</button>
                    <button onclick="viewUser(${user.id})">👁</button>
                </td>
            </tr>
        `;
    });
}

function updatePagination() {
    const pagination = document.getElementById("pagination");
    if (!pagination) return;

    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    const startItem = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);

    if (totalItems === 0) {
        pagination.innerHTML = `
            <button class="page-btn" onclick="goToPage(1)" disabled>◀</button>
            <span class="page-info">No records</span>
            <button class="page-btn" onclick="goToPage(1)" disabled>▶</button>
        `;
        return;
    }

    pagination.innerHTML = `
        <button class="page-btn" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>◀</button>
        <span class="page-info">${startItem}–${endItem} OF ${totalItems}</span>
        <button class="page-btn" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>▶</button>
    `;
}

window.goToPage = function(page) {
    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderUsers();
    updatePagination();
};

window.editUser = function(id) {
    window.location.href = "edit-user.html?id=" + id;
};

window.deleteUser = async function(id) {
    if (!confirm("Are you sure you want to delete this user?")) return;

    try {
        await Database.deleteUser(id);
        alert("User deleted successfully!");
        loadUsers();
    } catch (error) {
        console.error("Error deleting user:", error);
        alert("Error deleting user");
    }
};

window.viewUser = function(id) {
    window.location.href = "view-user.html?id=" + id;
};

window.addUser = function() {
    window.location.href = "add-user.html";
};
