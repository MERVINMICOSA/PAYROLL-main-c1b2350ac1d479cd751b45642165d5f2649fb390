<?php
/**
 * setup-test-data.php
 * Comprehensive test data setup for Payroll System
 * Creates employees, attendance, and payroll records for testing
 * 
 * Usage: Access via browser: /api/setup-test-data.php
 * Or CLI: php api/setup-test-data.php
 */

header("Content-Type: application/json");

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/SecureDatabase.php';

$response = [
    'status' => 'success',
    'summary' => [],
    'details' => []
];

try {
    $db = new SecureDatabase();
    
    // ============================================
    // 1. SET UP PERIOD SETTINGS
    // ============================================
    $periodStart = date('Y-m-01'); // First day of current month
    $periodEnd = date('Y-m-t');    // Last day of current month
    
    $db->delete('period_settings', '1=1'); // Clear old periods
    $db->insert('period_settings', [
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd
    ]);
    
    $response['summary'][] = "✅ Period settings configured: {$periodStart} to {$periodEnd}";
    
    // ============================================
    // 2. CREATE TEST EMPLOYEES
    // ============================================
    $db->delete('employees', '1=1'); // Clear old employees
    
    $employees = [
        // SHS Teachers
        [
            'full_name' => 'Maria Santos',
            'position' => 'Teacher III',
            'department' => 'Science Department',
            'employment_type' => 'Regular',
            'base_salary' => 25000,
            'hourly_rate' => 320,
            'admin_pay_rate' => 0,
            'email' => 'maria.santos@philtech.edu',
            'phone' => '09171234567',
            'hire_date' => '2022-06-01',
            'assignment' => 'shs',
            'rate_shs' => 80,
            'rate_college' => 0,
            'rate_admin' => 0,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-0',
            'philhealth' => 'PH-1234567890',
            'pagibig' => '123456789012',
            'tin' => '123-456-789-000',
            'emergency_name' => 'Juan Santos',
            'emergency_relation' => 'Spouse',
            'emergency_phone' => '09175555555',
            'status' => 'Active'
        ],
        [
            'full_name' => 'Juan dela Cruz',
            'position' => 'Teacher II',
            'department' => 'Math Department',
            'employment_type' => 'Regular',
            'base_salary' => 22000,
            'hourly_rate' => 280,
            'admin_pay_rate' => 0,
            'email' => 'juan.delacruz@philtech.edu',
            'phone' => '09187654321',
            'hire_date' => '2021-08-15',
            'assignment' => 'shs',
            'rate_shs' => 80,
            'rate_college' => 0,
            'rate_admin' => 0,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-1',
            'philhealth' => 'PH-1234567891',
            'pagibig' => '123456789013',
            'tin' => '123-456-789-001',
            'emergency_name' => 'Rosa dela Cruz',
            'emergency_relation' => 'Mother',
            'emergency_phone' => '09176666666',
            'status' => 'Active'
        ],
        [
            'full_name' => 'Angela Rodriguez',
            'position' => 'Teacher II',
            'department' => 'English Department',
            'employment_type' => 'Regular',
            'base_salary' => 21500,
            'hourly_rate' => 275,
            'admin_pay_rate' => 0,
            'email' => 'angela.rodriguez@philtech.edu',
            'phone' => '09199876543',
            'hire_date' => '2023-01-10',
            'assignment' => 'shs',
            'rate_shs' => 80,
            'rate_college' => 0,
            'rate_admin' => 0,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-2',
            'philhealth' => 'PH-1234567892',
            'pagibig' => '123456789014',
            'tin' => '123-456-789-002',
            'emergency_name' => 'Carlos Rodriguez',
            'emergency_relation' => 'Father',
            'emergency_phone' => '09177777777',
            'status' => 'Active'
        ],
        // College Teachers
        [
            'full_name' => 'Dr. Ramon Fernandez',
            'position' => 'Associate Professor',
            'department' => 'College of Engineering',
            'employment_type' => 'Regular',
            'base_salary' => 35000,
            'hourly_rate' => 450,
            'admin_pay_rate' => 0,
            'email' => 'ramon.fernandez@philtech.edu',
            'phone' => '09158887777',
            'hire_date' => '2020-07-01',
            'assignment' => 'college',
            'rate_shs' => 0,
            'rate_college' => 85,
            'rate_admin' => 0,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-3',
            'philhealth' => 'PH-1234567893',
            'pagibig' => '123456789015',
            'tin' => '123-456-789-003',
            'emergency_name' => 'Elena Fernandez',
            'emergency_relation' => 'Spouse',
            'emergency_phone' => '09158888888',
            'status' => 'Active'
        ],
        [
            'full_name' => 'Prof. Lucia Reyes',
            'position' => 'Instructor',
            'department' => 'College of Business',
            'employment_type' => 'Regular',
            'base_salary' => 28000,
            'hourly_rate' => 360,
            'admin_pay_rate' => 0,
            'email' => 'lucia.reyes@philtech.edu',
            'phone' => '09167778888',
            'hire_date' => '2022-01-15',
            'assignment' => 'college',
            'rate_shs' => 0,
            'rate_college' => 85,
            'rate_admin' => 0,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-4',
            'philhealth' => 'PH-1234567894',
            'pagibig' => '123456789016',
            'tin' => '123-456-789-004',
            'emergency_name' => 'Marco Reyes',
            'emergency_relation' => 'Brother',
            'emergency_phone' => '09169999999',
            'status' => 'Active'
        ],
        // Administrative Staff
        [
            'full_name' => 'Linda Torres',
            'position' => 'Administrative Assistant',
            'department' => 'Administration',
            'employment_type' => 'Regular',
            'base_salary' => 18000,
            'hourly_rate' => 0,
            'admin_pay_rate' => 250,
            'email' => 'linda.torres@philtech.edu',
            'phone' => '09175555666',
            'hire_date' => '2021-03-01',
            'assignment' => 'admin',
            'rate_shs' => 0,
            'rate_college' => 0,
            'rate_admin' => 70,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-5',
            'philhealth' => 'PH-1234567895',
            'pagibig' => '123456789017',
            'tin' => '123-456-789-005',
            'emergency_name' => 'Ricardo Torres',
            'emergency_relation' => 'Son',
            'emergency_phone' => '09176666777',
            'status' => 'Active'
        ],
        [
            'full_name' => 'Carlos Mercado',
            'position' => 'HR Specialist',
            'department' => 'Human Resources',
            'employment_type' => 'Regular',
            'base_salary' => 20000,
            'hourly_rate' => 0,
            'admin_pay_rate' => 280,
            'email' => 'carlos.mercado@philtech.edu',
            'phone' => '09187776666',
            'hire_date' => '2020-10-15',
            'assignment' => 'admin',
            'rate_shs' => 0,
            'rate_college' => 0,
            'rate_admin' => 70,
            'rate_guard' => 0,
            'rate_sa' => 0,
            'sss' => '12-3456789-6',
            'philhealth' => 'PH-1234567896',
            'pagibig' => '123456789018',
            'tin' => '123-456-789-006',
            'emergency_name' => 'Sophia Mercado',
            'emergency_relation' => 'Daughter',
            'emergency_phone' => '09178887777',
            'status' => 'Active'
        ],
        // Security Guards
        [
            'full_name' => 'Roberto Santos',
            'position' => 'Security Guard',
            'department' => 'Security',
            'employment_type' => 'Regular',
            'base_salary' => 12000,
            'hourly_rate' => 0,
            'admin_pay_rate' => 0,
            'email' => 'roberto.santos@philtech.edu',
            'phone' => '09165556666',
            'hire_date' => '2019-05-01',
            'assignment' => 'guard',
            'rate_shs' => 0,
            'rate_college' => 0,
            'rate_admin' => 0,
            'rate_guard' => 433,
            'rate_sa' => 0,
            'sss' => '12-3456789-7',
            'philhealth' => 'PH-1234567897',
            'pagibig' => '123456789019',
            'tin' => '123-456-789-007',
            'emergency_name' => 'Milagros Santos',
            'emergency_relation' => 'Spouse',
            'emergency_phone' => '09167778889',
            'status' => 'Active'
        ],
        [
            'full_name' => 'Anthony Lim',
            'position' => 'Security Guard',
            'department' => 'Security',
            'employment_type' => 'Regular',
            'base_salary' => 12000,
            'hourly_rate' => 0,
            'admin_pay_rate' => 0,
            'email' => 'anthony.lim@philtech.edu',
            'phone' => '09197778888',
            'hire_date' => '2021-02-15',
            'assignment' => 'guard',
            'rate_shs' => 0,
            'rate_college' => 0,
            'rate_admin' => 0,
            'rate_guard' => 433,
            'rate_sa' => 0,
            'sss' => '12-3456789-8',
            'philhealth' => 'PH-1234567898',
            'pagibig' => '123456789020',
            'tin' => '123-456-789-008',
            'emergency_name' => 'Patricia Lim',
            'emergency_relation' => 'Mother',
            'emergency_phone' => '09168889999',
            'status' => 'Active'
        ],
        // Student Assistant
        [
            'full_name' => 'Vincent Garcia',
            'position' => 'Student Assistant',
            'department' => 'Student Services',
            'employment_type' => 'Contractual',
            'base_salary' => 0,
            'hourly_rate' => 0,
            'admin_pay_rate' => 0,
            'email' => 'vincent.garcia@philtech.edu',
            'phone' => '09169990000',
            'hire_date' => '2024-06-01',
            'assignment' => 'sa',
            'rate_shs' => 0,
            'rate_college' => 0,
            'rate_admin' => 0,
            'rate_guard' => 0,
            'rate_sa' => 100,
            'sss' => '12-3456789-9',
            'philhealth' => 'PH-1234567899',
            'pagibig' => '123456789021',
            'tin' => '123-456-789-009',
            'emergency_name' => 'Mercedes Garcia',
            'emergency_relation' => 'Grandmother',
            'emergency_phone' => '09171110000',
            'status' => 'Active'
        ]
    ];
    
    $employeeIds = [];
    foreach ($employees as $emp) {
        $id = $db->addEmployee($emp);
        $employeeIds[] = $id;
    }
    
    $response['summary'][] = "✅ Created " . count($employeeIds) . " test employees";
    
    // ============================================
    // 3. CREATE TEACHER LOADS (for SHS and College)
    // ============================================
    $db->delete('teacher_loads', '1=1'); // Clear old loads
    
    $teacherLoads = [
        // SHS Teachers
        [
            'employee_id' => $employeeIds[0], // Maria Santos
            'semester' => '1st Sem',
            'school_year' => '2024-2025',
            'subject' => 'Biology',
            'rate' => 80,
            'mon' => 2, 'tue' => 2, 'wed' => 2, 'thu' => 2, 'fri' => 2, 'sat' => 0, 'sun' => 0
        ],
        [
            'employee_id' => $employeeIds[1], // Juan dela Cruz
            'semester' => '1st Sem',
            'school_year' => '2024-2025',
            'subject' => 'Mathematics',
            'rate' => 80,
            'mon' => 3, 'tue' => 3, 'wed' => 3, 'thu' => 3, 'fri' => 3, 'sat' => 0, 'sun' => 0
        ],
        [
            'employee_id' => $employeeIds[2], // Angela Rodriguez
            'semester' => '1st Sem',
            'school_year' => '2024-2025',
            'subject' => 'English',
            'rate' => 80,
            'mon' => 2, 'tue' => 2, 'wed' => 2, 'thu' => 2, 'fri' => 2, 'sat' => 0, 'sun' => 0
        ],
        // College Teachers
        [
            'employee_id' => $employeeIds[3], // Dr. Ramon Fernandez
            'semester' => '1st Sem',
            'school_year' => '2024-2025',
            'subject' => 'Engineering Systems',
            'rate' => 85,
            'mon' => 3, 'tue' => 3, 'wed' => 3, 'thu' => 3, 'fri' => 3, 'sat' => 0, 'sun' => 0
        ],
        [
            'employee_id' => $employeeIds[4], // Prof. Lucia Reyes
            'semester' => '1st Sem',
            'school_year' => '2024-2025',
            'subject' => 'Business Management',
            'rate' => 85,
            'mon' => 2, 'tue' => 2, 'wed' => 2, 'thu' => 2, 'fri' => 2, 'sat' => 0, 'sun' => 0
        ]
    ];
    
    foreach ($teacherLoads as $load) {
        $db->insert('teacher_loads', $load);
    }
    
    $response['summary'][] = "✅ Created " . count($teacherLoads) . " teacher loads";
    
    // ============================================
    // 4. CREATE ATTENDANCE RECORDS
    // ============================================
    $db->delete('attendance', '1=1'); // Clear old attendance
    
    $attendanceRecords = 0;
    $daysInPeriod = (int)date('d', strtotime($periodEnd));
    
    // Create attendance for each employee for each day in the period
    foreach ($employeeIds as $empIndex => $empId) {
        $employee = $employees[$empIndex];
        
        for ($day = 1; $day <= $daysInPeriod; $day++) {
            $date = $employee['hire_date'] <= date('Y-m-d', strtotime("$periodStart + {$day} days")) ? 
                    date('Y-m-d', strtotime("$periodStart + {$day} days")) : 
                    date('Y-m-d');
            
            $dayOfWeek = date('N', strtotime($date)); // 1=Mon, 7=Sun
            
            // Skip weekends for most employees
            if ($dayOfWeek > 5) continue;
            
            // Realistic hours based on assignment
            $hoursWorked = match($employee['assignment']) {
                'shs', 'college' => 8,
                'admin' => 8,
                'guard' => 12,
                'sa' => 4,
                default => 8
            };
            
            // Random lates/undertime (realistic)
            $lates = rand(0, 2) === 0 ? (mt_rand(1, 4) / 2) : 0;
            $overtime = rand(0, 5) === 0 ? rand(1, 3) : 0;
            
            $hoursWorked = max(0, $hoursWorked - $lates);
            
            $attendanceData = [
                'employee_id' => $empId,
                'tab_type' => 'eda',
                'date' => $date,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payroll_period' => date('F Y', strtotime($periodStart)),
                'hours_worked' => $hoursWorked,
                'overtime' => $overtime,
                'lates' => $lates,
                'absences' => rand(0, 10) === 0 ? 1 : 0,
                'pay_type' => 'regular',
                'admin_pay_rate' => $employee['admin_pay_rate'],
                'notes' => ''
            ];
            
            // Set daily hours
            $attendanceData[strtolower(date('D', strtotime($date)))] = 1;
            
            $db->addAttendance($attendanceData);
            $attendanceRecords++;
        }
    }
    
    $response['summary'][] = "✅ Created " . $attendanceRecords . " attendance records";
    
    // ============================================
    // 5. GENERATE PAYROLL FROM ATTENDANCE
    // ============================================
    $generated = $db->generatePayrollFromAttendance($periodStart, $periodEnd, date('F Y', strtotime($periodStart)));
    
    $response['summary'][] = "✅ Generated " . count($generated) . " payroll records";
    
    // ============================================
    // 6. ADD NOTIFICATIONS
    // ============================================
    $db->delete('notifications', '1=1'); // Clear old notifications
    
    // Get accountant user ID
    $accountant = $db->fetchOne("SELECT id FROM users WHERE username = ?", ['accountant']);
    
    if ($accountant) {
        // Minimal notification payload - database may not have read/title columns yet
        // Run api/run-schema.php to update the database schema
        $notifications = [
            [
                'user_id' => $accountant['id'],
                'type' => 'payroll_generated',
                'message' => 'Payroll for ' . date('F Y', strtotime($periodStart)) . ' has been generated from attendance records.',
                'data' => json_encode(['period_start' => $periodStart, 'period_end' => $periodEnd, 'count' => count($generated)])
            ],
            [
                'user_id' => $accountant['id'],
                'type' => 'test_data_loaded',
                'message' => 'Test employees, attendance, and payroll records have been loaded successfully.',
                'data' => json_encode(['employees' => count($employeeIds), 'attendance' => $attendanceRecords])
            ]
        ];
        
        foreach ($notifications as $notif) {
            $db->insert('notifications', $notif);
        }
        
        $response['summary'][] = "✅ Created notifications";
    }
    
    // ============================================
    // 7. FINAL SUMMARY
    // ============================================
    $response['details'] = [
        'period' => [
            'start' => $periodStart,
            'end' => $periodEnd
        ],
        'employees' => [
            'total' => count($employeeIds),
            'by_assignment' => [
                'shs_teachers' => 3,
                'college_instructors' => 2,
                'admin_staff' => 2,
                'security_guards' => 2,
                'student_assistants' => 1
            ]
        ],
        'attendance_records' => $attendanceRecords,
        'payroll_records' => count($generated),
        'teacher_loads' => count($teacherLoads),
        'default_credentials' => [
            'accountant' => 'admin123',
            'superadmin' => 'superadmin123',
            'oic' => 'oic123'
        ]
    ];
    
    $response['summary'][] = "✅ All test data loaded successfully!";

} catch (Exception $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
