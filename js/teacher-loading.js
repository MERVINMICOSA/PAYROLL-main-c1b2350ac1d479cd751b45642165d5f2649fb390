// js/teacher-loading.js - UPDATED with Render integration

console.log("TEACHER LOADING JS LOADED - Phase 3");

import { Database, DB_EVENTS } from './database.js';

let currentLevel = 'shs';
let currentSemester = '1st Sem';
let currentSchoolYear = '2025-2026';
let allEmployees = [];
let allLoadings = [];

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await Database.ensureDatabaseReady();
    
    Database.Events.on(DB_EVENTS.EMPLOYEE_ADDED, loadData);
    Database.Events.on(DB_EVENTS.EMPLOYEE_UPDATED, loadData);
    Database.Events.on(DB_EVENTS.LOADING_ADDED, loadData);
    Database.Events.on(DB_EVENTS.LOADING_UPDATED, loadData);
    
    document.getElementById('semesterSelect')?.addEventListener('change', (e) => {
        currentSemester = e.target.value;
        loadData();
    });
    
    document.getElementById('schoolYearSelect')?.addEventListener('change', (e) => {
        currentSchoolYear = e.target.value;
        loadData();
    });
    
    document.querySelectorAll('.loading-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.loading-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentLevel = tab.dataset.level;
            document.getElementById('currentLevelTitle').textContent = 
                currentLevel === 'shs' ? 'SHS Faculty Loading' : 'College Faculty Loading';
            loadData();
        });
    });
    
    await loadData();
});

async function loadData() {
    try {
        allEmployees = await Database.getAllEmployees();
        allLoadings = await Database.getAllTeacherLoadings();
        renderLoadingGrid();
    } catch (error) {
        console.error('Error loading data:', error);
    }
}

function renderLoadingGrid() {
    const grid = document.getElementById('loadingGrid');
    
    const teachers = allEmployees.filter(emp => 
        emp.assignment === (currentLevel === 'shs' ? 'shs_only' : 'college_only') ||
        emp.assignment === 'both'
    );
    
    if (teachers.length === 0) {
        grid.innerHTML = '<div class="p-8 text-center text-gray-400">No teachers found. Please add employees first.</div>';
        document.getElementById('totalLoadHours').textContent = '0';
        document.getElementById('totalCost').textContent = '0.00';
        return;
    }
    
    let html = '<div class="grid-row">';
    const headers = ['Teacher Name', 'Subject', 'Rate/hr', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Total Hours', 'Total Pay'];
    headers.forEach(header => { html += `<div class="grid-header">${header}</div>`; });
    html += '</div>';
    
    let totalLoadHours = 0, totalCost = 0;
    
    teachers.forEach((teacher) => {
        const loading = allLoadings.find(l => 
            l.employee_id === teacher.id && l.semester === currentSemester && l.school_year === currentSchoolYear
        ) || {
            employee_id: teacher.id, semester: currentSemester, school_year: currentSchoolYear,
            subject: '', rate: teacher.rate_shs || teacher.rate_college || 0,
            mon: 0, tue: 0, wed: 0, thu: 0, fri: 0, sat: 0, sun: 0
        };
        
        const totalHours = (loading.mon || 0) + (loading.tue || 0) + (loading.wed || 0) + 
                          (loading.thu || 0) + (loading.fri || 0) + (loading.sat || 0) + (loading.sun || 0);
        const totalPay = totalHours * (loading.rate || 0);
        
        totalLoadHours += totalHours;
        totalCost += totalPay;
        
        html += '<div class="grid-row">';
        html += `<div class="grid-cell name-cell">${teacher.full_name}</div>`;
        html += `<div class="grid-cell"><input type="text" value="${loading.subject || ''}" data-id="${teacher.id}" data-field="subject" onchange="updateLoadingField(${teacher.id}, 'subject', this.value)"></div>`;
        html += `<div class="grid-cell"><input type="number" step="0.01" value="${loading.rate || 0}" data-id="${teacher.id}" data-field="rate" min="0" onchange="updateLoadingField(${teacher.id}, 'rate', parseFloat(this.value) || 0)"></div>`;
        ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].forEach(day => {
            html += `<div class="grid-cell"><input type="number" step="0.5" value="${loading[day] || 0}" data-id="${teacher.id}" data-field="${day}" min="0" max="24" onchange="updateLoadingField(${teacher.id}, '${day}', parseFloat(this.value) || 0)"></div>`;
        });
        html += `<div class="grid-cell total-cell">${totalHours.toFixed(1)}</div>`;
        html += `<div class="grid-cell total-cell">₱${totalPay.toFixed(2)}</div>`;
        html += '</div>';
    });
    
    grid.innerHTML = html;
    document.getElementById('totalLoadHours').textContent = totalLoadHours.toFixed(1);
    document.getElementById('totalCost').textContent = totalCost.toFixed(2);
}

window.updateLoadingField = async function(employeeId, field, value) {
    try {
        let loading = allLoadings.find(l => 
            l.employee_id === employeeId && l.semester === currentSemester && l.school_year === currentSchoolYear
        );
        
        if (!loading) loading = { employee_id: employeeId, semester: currentSemester, school_year: currentSchoolYear };
        
        loading[field] = value;
        
        if (loading.id) await Database.updateTeacherLoading(loading);
        else await Database.addTeacherLoading(loading);
        
        await loadData();
    } catch (error) {
        console.error('Error updating loading:', error);
        alert('Failed to save changes');
    }
};

window.saveAllLoadings = async function() {
    try {
        const teachers = allEmployees.filter(emp => 
            emp.assignment === (currentLevel === 'shs' ? 'shs_only' : 'college_only') || emp.assignment === 'both'
        );
        
        for (const teacher of teachers) {
            const loading = allLoadings.find(l => 
                l.employee_id === teacher.id && l.semester === currentSemester && l.school_year === currentSchoolYear
            );
            if (loading && !loading.id) await Database.addTeacherLoading(loading);
            else if (loading) await Database.updateTeacherLoading(loading);
        }
        
        alert('All loadings saved successfully!');
        await loadData();
    } catch (error) {
        console.error('Error saving all:', error);
        alert('Failed to save all changes');
    }
};

window.logout = function() {
    localStorage.clear();
    window.location.href = 'index.html';
};