// js/notifications.js - Uses render-api.js for Render.com compatibility
import { getNotifications, markNotificationAsRead } from './render-api.js';

let notificationInterval = null;

window.initNotifications = async function() {
    const userRole = localStorage.getItem('userRole');
    if (userRole !== 'accountant' && userRole !== 'superadmin') return;
    
    addNotificationBell();
    await refreshNotifications();
    notificationInterval = setInterval(refreshNotifications, 30000);
};

function addNotificationBell() {
    if (document.getElementById('notificationBell')) return;
    const nav = document.querySelector('.flex.items-center.gap-3');
    if (!nav) return;
    const bell = document.createElement('div');
    bell.id = 'notificationBell';
    bell.className = 'relative cursor-pointer group mr-2';
    bell.innerHTML = `<i class="fa-regular fa-bell text-xl text-white"></i>
        <span id="notificationBadge" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 rounded-full hidden">0</span>
        <div class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl py-2 hidden group-hover:block z-50">
            <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b flex justify-between"><span>Notifications</span><span id="notificationCount" class="bg-[#b0303b] text-white text-xs px-2 py-1 rounded-full">0</span></div>
            <div id="notificationList" class="max-h-96 overflow-y-auto"><div class="px-4 py-3 text-sm text-gray-500 text-center">No new notifications</div></div>
            <div class="border-t px-4 py-2 text-center"><button onclick="window.markAllAsRead()" class="text-xs text-[#b0303b] hover:text-[#8a1f27]">Mark all as read</button></div>
        </div>`;
    nav.insertBefore(bell, nav.children[1]);
}

window.refreshNotifications = async function() {
    try {
        const notifications = await getNotifications(false);
        const count = notifications.length;
        const badge = document.getElementById('notificationBadge');
        const countSpan = document.getElementById('notificationCount');
        const list = document.getElementById('notificationList');
        if (badge) { if (count > 0) { badge.style.display = 'flex'; badge.textContent = count > 9 ? '9+' : count; if (countSpan) countSpan.textContent = count; } else { badge.style.display = 'none'; if (countSpan) countSpan.textContent = '0'; } }
        if (list) {
            if (notifications.length === 0) list.innerHTML = '<div class="px-4 py-8 text-sm text-gray-500 text-center">No new notifications</div>';
            else list.innerHTML = notifications.map(n => `<div class="px-4 py-3 border-b hover:bg-gray-50 ${n.read ? 'opacity-50' : ''}" data-id="${n.id}"><div class="flex gap-3"><div class="flex-shrink-0">${getIcon(n.type)}</div><div class="flex-1"><p class="text-sm text-gray-800">${n.message}</p><p class="text-xs text-gray-400 mt-1">${formatTime(n.timestamp)}</p></div>${!n.read ? '<div class="w-2 h-2 bg-[#b0303b] rounded-full mt-1"></div>' : ''}</div></div>`).join('');
        }
    } catch (error) { console.error('Error refreshing notifications:', error); }
};

function getIcon(type) {
    switch(type) {
        case 'loads_updated': return '<i class="fa-solid fa-chalkboard text-[#b0303b]"></i>';
        case 'undertime': return '<i class="fa-solid fa-clock text-yellow-500"></i>';
        case 'employee_added': return '<i class="fa-solid fa-user-plus text-green-500"></i>';
        default: return '<i class="fa-regular fa-bell text-gray-500"></i>';
    }
}

function formatTime(ts) {
    const diff = Date.now() - new Date(ts).getTime();
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} min ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
    return new Date(ts).toLocaleDateString();
}

window.markAsRead = async function(id) { 
    await markNotificationAsRead(id); 
    await window.refreshNotifications(); 
};

window.markAllAsRead = async function() { 
    const notifications = await getNotifications(false);
    for (const n of notifications) await window.markAsRead(n.id); 
    await window.refreshNotifications(); 
};

window.cleanupNotifications = function() { if (notificationInterval) clearInterval(notificationInterval); };

console.log("Notifications module loaded (uses render-api.js)");