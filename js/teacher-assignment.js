// js/teacher-assignment.js
// Handles teacher assignments for SHS, College, or Both

// ============================================
// TEACHER ASSIGNMENT TYPES
// ============================================
const ASSIGNMENT_TYPES = {
    SHS_ONLY: 'shs_only',
    COLLEGE_ONLY: 'college_only',
    BOTH: 'both',
    ADMIN: 'admin',
    GUARD: 'guard',
    SA: 'sa'
};

// ============================================
// TEACHER ASSIGNMENT DATA STRUCTURE
// ============================================
let teacherAssignments = [];

// Default teacher data (matching your Excel)
const DEFAULT_TEACHERS = [
    { id: 1, name: 'Banoy, Roshelle', assignment: 'shs_only', rate_shs: 80, rate_college: 0, subjects_shs: ['Math'], subjects_college: [], status: 'Active' },
    { id: 2, name: 'Beltran, Rachael', assignment: 'shs_only', rate_shs: 85, rate_college: 0, subjects_shs: ['Science'], subjects_college: [], status: 'Active' },
    { id: 3, name: 'Capunitan, Joselito B.', assignment: 'shs_only', rate_shs: 82, rate_college: 0, subjects_shs: ['English'], subjects_college: [], status: 'Active' },
    { id: 4, name: 'Calceña, Mico', assignment: 'college_only', rate_shs: 0, rate_college: 85, subjects_shs: [], subjects_college: ['Physics'], status: 'Active' },
    { id: 5, name: 'Dimapilis, Nerlie', assignment: 'college_only', rate_shs: 0, rate_college: 82, subjects_shs: [], subjects_college: ['Chemistry'], status: 'Active' },
    { id: 6, name: 'Ello, Gerald', assignment: 'college_only', rate_shs: 0, rate_college: 87, subjects_shs: [], subjects_college: ['Biology'], status: 'Active' },
    { id: 7, name: 'Santos, Maria', assignment: 'both', rate_shs: 85, rate_college: 90, subjects_shs: ['Math', 'Science'], subjects_college: ['Calculus'], status: 'Active' },
    { id: 8, name: 'Gordon, Leonora M.', assignment: 'admin', rate_admin: 70, department: 'Administration', position: 'Branch Administrator', status: 'Active' }
];

// ============================================
// INITIALIZATION
// ============================================

// Initialize teacher assignments
async function initTeacherAssignments() {
    try {
        // Try to load from database first
        if (window.Database && Database.getTeacherAssignments) {
            teacherAssignments = await Database.getTeacherAssignments();
        } else {
            // Use default data as fallback
            teacherAssignments = [...DEFAULT_TEACHERS];
        }
        
        console.log('✅ Teacher assignments loaded:', teacherAssignments.length);
        return teacherAssignments;
    } catch (error) {
        console.error('Error loading teacher assignments:', error);
        teacherAssignments = [...DEFAULT_TEACHERS];
        return teacherAssignments;
    }
}

// ============================================
// GETTER FUNCTIONS
// ============================================

// Get teachers by assignment type
function getTeachersByAssignment(type) {
    return teacherAssignments.filter(t => t.assignment === type && t.status === 'Active');
}

// Get all SHS teachers (including those who teach both)
function getSHSTeachers() {
    return teacherAssignments.filter(t => 
        (t.assignment === ASSIGNMENT_TYPES.SHS_ONLY || t.assignment === ASSIGNMENT_TYPES.BOTH) && 
        t.status === 'Active'
    );
}

// Get all College teachers (including those who teach both)
function getCollegeTeachers() {
    return teacherAssignments.filter(t => 
        (t.assignment === ASSIGNMENT_TYPES.COLLEGE_ONLY || t.assignment === ASSIGNMENT_TYPES.BOTH) && 
        t.status === 'Active'
    );
}

// Get all Admin staff
function getAdminStaff() {
    return teacherAssignments.filter(t => t.assignment === ASSIGNMENT_TYPES.ADMIN && t.status === 'Active');
}

// Get all Guard personnel
function getGuardStaff() {
    return teacherAssignments.filter(t => t.assignment === ASSIGNMENT_TYPES.GUARD && t.status === 'Active');
}

// Get all Student Assistants
function getSAStaff() {
    return teacherAssignments.filter(t => t.assignment === ASSIGNMENT_TYPES.SA && t.status === 'Active');
}

// Get teacher by ID
function getTeacherById(id) {
    return teacherAssignments.find(t => t.id === parseInt(id));
}

// Get teacher by name
function getTeacherByName(name) {
    return teacherAssignments.find(t => t.name.toLowerCase().includes(name.toLowerCase()));
}

// ============================================
// ASSIGNMENT MANAGEMENT
// ============================================

// Add new teacher assignment
async function addTeacherAssignment(teacher) {
    // Generate new ID
    const newId = Math.max(...teacherAssignments.map(t => t.id), 0) + 1;
    
    const newTeacher = {
        id: newId,
        ...teacher,
        status: 'Active',
        created_at: new Date().toISOString()
    };
    
    teacherAssignments.push(newTeacher);
    
    // Save to database if available
    if (window.Database && Database.saveTeacherAssignment) {
        await Database.saveTeacherAssignment(newTeacher);
    }
    
    return newTeacher;
}

// Update teacher assignment
async function updateTeacherAssignment(id, updates) {
    const index = teacherAssignments.findIndex(t => t.id === parseInt(id));
    if (index === -1) return null;
    
    teacherAssignments[index] = { ...teacherAssignments[index], ...updates };
    
    // Save to database if available
    if (window.Database && Database.updateTeacherAssignment) {
        await Database.updateTeacherAssignment(teacherAssignments[index]);
    }
    
    return teacherAssignments[index];
}

// Archive teacher (soft delete)
async function archiveTeacher(id) {
    return updateTeacherAssignment(id, { status: 'Archived' });
}

// ============================================
// ASSIGNMENT CHECK FUNCTIONS
// ============================================

// Check if teacher teaches SHS
function teachesSHS(teacherId) {
    const teacher = getTeacherById(teacherId);
    return teacher && (teacher.assignment === ASSIGNMENT_TYPES.SHS_ONLY || teacher.assignment === ASSIGNMENT_TYPES.BOTH);
}

// Check if teacher teaches College
function teachesCollege(teacherId) {
    const teacher = getTeacherById(teacherId);
    return teacher && (teacher.assignment === ASSIGNMENT_TYPES.COLLEGE_ONLY || teacher.assignment === ASSIGNMENT_TYPES.BOTH);
}

// Get appropriate rate for teacher based on assignment type
function getTeacherRate(teacherId, type) {
    const teacher = getTeacherById(teacherId);
    if (!teacher) return 0;
    
    if (type === 'shs') {
        return teacher.rate_shs || 0;
    } else if (type === 'college') {
        return teacher.rate_college || 0;
    } else if (type === 'admin') {
        return teacher.rate_admin || 0;
    }
    return 0;
}

// ============================================
// RENDER ASSIGNMENT UI
// ============================================

// Render teacher assignment form (for edit-employee.html)
function renderAssignmentForm(containerId, teacherId = null) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const teacher = teacherId ? getTeacherById(teacherId) : null;
    
    let html = `
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Teaching Assignment</label>
                <select id="teacherAssignment" class="w-full px-4 py-2 border border-gray-200 rounded-lg" onchange="toggleAssignmentFields()">
                    <option value="shs_only" ${teacher?.assignment === 'shs_only' ? 'selected' : ''}>SHS Only</option>
                    <option value="college_only" ${teacher?.assignment === 'college_only' ? 'selected' : ''}>College Only</option>
                    <option value="both" ${teacher?.assignment === 'both' ? 'selected' : ''}>Both SHS and College</option>
                    <option value="admin" ${teacher?.assignment === 'admin' ? 'selected' : ''}>Admin Staff</option>
                    <option value="guard" ${teacher?.assignment === 'guard' ? 'selected' : ''}>Security Guard</option>
                    <option value="sa" ${teacher?.assignment === 'sa' ? 'selected' : ''}>Student Assistant</option>
                </select>
            </div>
            
            <div id="shsFields" class="space-y-4 ${teacher?.assignment === 'college_only' ? 'hidden' : ''}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">SHS Rate (per hour)</label>
                    <input type="number" id="shsRate" step="0.01" value="${teacher?.rate_shs || 80}" 
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">SHS Subjects</label>
                    <input type="text" id="shsSubjects" value="${teacher?.subjects_shs?.join(', ') || ''}" 
                           placeholder="e.g., Math, Science, English"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg">
                </div>
            </div>
            
            <div id="collegeFields" class="space-y-4 ${teacher?.assignment === 'shs_only' ? 'hidden' : ''}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">College Rate (per hour)</label>
                    <input type="number" id="collegeRate" step="0.01" value="${teacher?.rate_college || 85}" 
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">College Subjects</label>
                    <input type="text" id="collegeSubjects" value="${teacher?.subjects_college?.join(', ') || ''}" 
                           placeholder="e.g., Calculus, Physics"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg">
                </div>
            </div>
            
            <div id="adminFields" class="space-y-4 hidden">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Rate (per hour)</label>
                    <input type="number" id="adminRate" step="0.01" value="${teacher?.rate_admin || 70}" 
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                    <input type="text" id="adminPosition" value="${teacher?.position || ''}" 
                           placeholder="e.g., Branch Administrator"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg">
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
    
    // Show/hide appropriate fields based on initial selection
    toggleAssignmentFields();
}

// Toggle assignment fields based on selected type
function toggleAssignmentFields() {
    const assignment = document.getElementById('teacherAssignment')?.value;
    
    const shsFields = document.getElementById('shsFields');
    const collegeFields = document.getElementById('collegeFields');
    const adminFields = document.getElementById('adminFields');
    
    // Hide all first
    shsFields?.classList.add('hidden');
    collegeFields?.classList.add('hidden');
    adminFields?.classList.add('hidden');
    
    // Show based on selection
    if (assignment === 'shs_only') {
        shsFields?.classList.remove('hidden');
    } else if (assignment === 'college_only') {
        collegeFields?.classList.remove('hidden');
    } else if (assignment === 'both') {
        shsFields?.classList.remove('hidden');
        collegeFields?.classList.remove('hidden');
    } else if (assignment === 'admin') {
        adminFields?.classList.remove('hidden');
    }
}

// ============================================
// EXPORT FUNCTIONS
// ============================================

window.TeacherAssignment = {
    init: initTeacherAssignments,
    types: ASSIGNMENT_TYPES,
    getSHSTeachers,
    getCollegeTeachers,
    getAdminStaff,
    getGuardStaff,
    getSAStaff,
    getTeacherById,
    getTeacherByName,
    addTeacherAssignment,
    updateTeacherAssignment,
    archiveTeacher,
    teachesSHS,
    teachesCollege,
    getTeacherRate,
    renderAssignmentForm,
    toggleAssignmentFields
};