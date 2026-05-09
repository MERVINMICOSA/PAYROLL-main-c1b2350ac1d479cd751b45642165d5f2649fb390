// Dashboard initialization
window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
        
        // Subscribe to database changes to auto-refresh dashboard
        Database.Events.on(DB_EVENTS.EMPLOYEE_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.EMPLOYEE_DELETED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_ADDED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_UPDATED, handleDataChange);
        Database.Events.on(DB_EVENTS.PAYROLL_DELETED, handleDataChange);
        
        await loadDashboardStats();
        initCharts();
    } catch (error) {
        console.error("Error initializing dashboard:", error);
        // Still init charts even if DB fails
        initCharts();
    }
});

// Handler for database changes - refreshes dashboard data
function handleDataChange(data) {
    console.log("Database change detected in dashboard:", data);
    loadDashboardStats();
    // Also refresh charts if they exist
    const pieChartEl = document.getElementById("pieChart");
    if (pieChartEl) {
        initCharts();
    }
}

async function loadDashboardStats() {
    try {
        const employees = await Database.getAllEmployees();
        const payroll = await Database.getAllPayroll();
        
        // Calculate total employees
        const totalEmployees = employees.length;
        
        // Calculate total payroll this month
        const currentMonthPayroll = payroll.reduce((sum, p) => sum + (p.net_salary || 0), 0);
        
        // Count pending payroll
        const pendingPayroll = payroll.filter(p => p.status === "Pending").length;
        
        // Calculate total deductions
        const totalDeductions = payroll.reduce((sum, p) => sum + (p.total_deduction || 0), 0);
        
        // Update the DOM elements
        const elements = {
            'totalEmployees': totalEmployees,
            'payrollThisMonth': currentMonthPayroll,
            'pendingPayroll': pendingPayroll,
            'totalDeductions': totalDeductions
        };
        
        // Update each stat if element exists
        const statCards = document.querySelectorAll('.card');
        if (statCards.length >= 4) {
            // Total Employees
            const empNum = statCards[0].querySelector('.big-number');
            if (empNum) empNum.textContent = totalEmployees;
            
            // Payroll this month
            const payNum = statCards[1].querySelector('.big-number');
            if (payNum) payNum.textContent = '₱' + currentMonthPayroll.toLocaleString();
            
            // Pending Payroll
            const pendNum = statCards[2].querySelector('.big-number');
            if (pendNum) pendNum.textContent = pendingPayroll;
            
            // Total Deductions
            const dedNum = statCards[3].querySelector('.big-number');
            if (dedNum) dedNum.textContent = '₱' + totalDeductions.toLocaleString();
        }
        
    } catch (error) {
        console.error("Error loading dashboard stats:", error);
    }
}

function initCharts() {
    // Check if Chart is loaded
    if (typeof Chart === 'undefined') {
        console.warn("Chart.js not loaded");
        return;
    }
    
    const barChartEl = document.getElementById("barChart");
    const pieChartEl = document.getElementById("pieChart");
    
    if (barChartEl) {
        /* BAR CHART - Payroll Trend */
        new Chart(barChartEl, {
            type: "bar",
            data: {
                labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug"],
                datasets: [{
                    data: [45000, 52000, 48000, 61000, 65000, 60800, 55000, 72000],
                    backgroundColor: "#b0303b",
                    borderRadius: 15
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { display: false }, ticks: { display: false } }
                }
            }
        });
    }
    
    if (pieChartEl) {
        /* PIE CHART - Employee Type */
        // Get employee type counts from database
        Database.getAllEmployees().then(employees => {
            const regular = employees.filter(e => e.employment_type === 'Regular').length;
            const contractual = employees.filter(e => e.employment_type === 'Contractual').length;
            
            new Chart(pieChartEl, {
                type: "pie",
                data: {
                    labels: ["Regular", "Contractual"],
                    datasets: [{
                        data: [regular || 5, contractual || 3],
                        backgroundColor: ["#ffffff", "#5c0f18"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: "right",
                            labels: { color: "white" }
                        }
                    }
                }
            });
        }).catch(() => {
            // Fallback if database fails
            new Chart(pieChartEl, {
                type: "pie",
                data: {
                    labels: ["Regular", "Contractual"],
                    datasets: [{
                        data: [5, 3],
                        backgroundColor: ["#ffffff", "#5c0f18"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: "right",
                            labels: { color: "white" }
                        }
                    }
                }
            });
        });
    }
}

