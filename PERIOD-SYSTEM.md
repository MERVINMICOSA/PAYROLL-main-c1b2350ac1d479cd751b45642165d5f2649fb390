# Period Management System - Implementation Guide

## Overview
The period management system ensures that attendance data for each employee is organized within non-overlapping periods, preventing data conflicts and ensuring data integrity.

## Key Features

✅ **No Overlapping Periods** - Database-level constraints prevent creation of overlapping periods
✅ **Period-Specific Attendance Data** - Attendance records are linked to specific periods
✅ **Period Filtering** - Load attendance data for specific periods only
✅ **Validation** - All period operations are validated before execution
✅ **Data Integrity** - Cannot delete periods with associated attendance records

## Database Schema

### Attendance Periods Table
```sql
CREATE TABLE attendance_periods (
    id SERIAL PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    description VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(period_start, period_end),
    CONSTRAINT valid_period CHECK (period_start < period_end)
);
```

### Key Constraints
- `UNIQUE(period_start, period_end)` - Prevents duplicate periods
- `CHECK (period_start < period_end)` - Ensures valid date ranges
- Overlap prevention is enforced at API level

## API Endpoints

### Get All Periods
```
GET /api/period/index.php?type=all
Response: Array of all periods
```

### Get Active Period
Returns the period containing today's date
```
GET /api/period/index.php?type=active
Response: Current active period or error
```

### Get Specific Period
```
GET /api/period/index.php?type=specific&start_date=2024-01-01&end_date=2024-01-31
Response: Period details or error
```

### Check for Overlaps
```
GET /api/period/index.php?type=overlaps&start_date=2024-01-01&end_date=2024-01-31
Response: Array of conflicting periods (empty if none)
```

### Create Period (with Validation)
```
POST /api/period/index.php
Content-Type: application/json

{
    "period_start": "2024-01-01",
    "period_end": "2024-01-31",
    "description": "January 2024"
}

Response: 
{
    "success": true,
    "id": 1,
    "message": "Period created successfully..."
}

Errors:
- 400: Invalid date format or dates invalid
- 409: Period overlaps with existing period(s)
```

### Update Period
```
PUT /api/period/index.php
Content-Type: application/json

{
    "id": 1,
    "period_start": "2024-01-01",
    "period_end": "2024-01-31",
    "description": "January 2024 (Updated)"
}
```

### Delete Period
```
DELETE /api/period/index.php?id=1

Errors:
- 409: Cannot delete if attendance records exist
```

## JavaScript Usage

### Initialize Period Manager
```javascript
const periodManager = new PeriodManager();
await periodManager.init();
```

### Load All Periods
```javascript
const periods = await periodManager.loadPeriods();
console.log(`Loaded ${periods.length} periods`);
```

### Get Active Period
```javascript
const activePeriod = await periodManager.getActivePeriod();
if (activePeriod) {
    console.log(`Active period: ${activePeriod.period_start} to ${activePeriod.period_end}`);
}
```

### Create New Period (with Overlap Validation)
```javascript
try {
    const result = await periodManager.createPeriod(
        '2024-02-01',
        '2024-02-29',
        'February 2024'
    );
    console.log('Period created:', result);
} catch (error) {
    console.error('Failed to create period:', error.message);
}
```

### Set Current Period for Filtering
```javascript
// This period will be used for all attendance queries
periodManager.setCurrentPeriod('2024-01-01', '2024-01-31');
```

### Get Attendance for Current Period
```javascript
try {
    const attendance = await periodManager.getAttendanceForCurrentPeriod();
    console.log(`Found ${attendance.length} attendance records`);
} catch (error) {
    console.error('Failed to fetch attendance:', error.message);
}
```

### Check for Overlaps Before Creating Period
```javascript
const overlaps = await periodManager.checkOverlaps('2024-01-01', '2024-01-31');
if (overlaps.length > 0) {
    console.log('Period overlaps with:', overlaps);
} else {
    console.log('Period is clear, safe to create');
}
```

### Format Period for Display
```javascript
const formatted = periodManager.formatPeriodDisplay('2024-01-01', '2024-01-31');
console.log(formatted); // "Jan 1, 2024 → Jan 31, 2024"
```

### Get Grouped Periods
```javascript
const grouped = periodManager.getGroupedPeriods();
// Returns periods grouped by year-month:
// {
//   "2024-02": [...periods],
//   "2024-01": [...periods],
//   ...
// }
```

## Attendance Filtering by Period

### Database Methods

#### Get All Attendance for Period
```php
$db = new SecureDatabase();
$attendance = $db->getAttendanceByPeriod('2024-01-01', '2024-01-31');
```

#### Get Employee Attendance for Period
```php
$attendance = $db->getEmployeeAttendanceByPeriod(
    $employeeId,
    '2024-01-01',
    '2024-01-31'
);
```

#### Get Attendance Summary for Period
```php
$summary = $db->getAttendanceSummaryByPeriod('2024-01-01', '2024-01-31');
// Returns:
// [
//   {
//     'id': 1,
//     'full_name': 'John Doe',
//     'assignment': 'shs',
//     'days_recorded': 20,
//     'total_hours': 160,
//     'total_overtime': 8,
//     'total_lates': 30,
//     'total_absences': 0
//   },
//   ...
// ]
```

## Attendance Tab Implementation

### How It Works

1. **Period Selection**
   - User selects or creates a period using "Add Period" button
   - System validates for overlaps
   - Period is stored in `attendance_periods` table

2. **Loading Period Data**
   - Click "Load Period" button
   - Select from available periods
   - System fetches attendance data WHERE `period_start` = selected AND `period_end` = selected
   - Only attendance for that specific period is displayed

3. **Tab-Specific Filtering**
   - Each tab filters attendance by `assignment` (shs, college, admin, guard, sa)
   - AND by selected `period_start` and `period_end`
   - Results include all employees with data for that period

4. **Data Entry**
   - When entering attendance data, it must include:
     - `period_start`: Start date of the period
     - `period_end`: End date of the period
     - `employee_id`: Which employee
     - Daily records (mon, tue, wed, etc.)

5. **Payroll Generation**
   - Can only generate payroll for complete periods
   - Uses attendance data WHERE period matches
   - Prevents accidental double-generation across period boundaries

## Example: Complete Period Workflow

```javascript
// 1. Initialize
const pm = new PeriodManager();
await pm.init();

// 2. Create a new period
try {
    await pm.createPeriod('2024-01-01', '2024-01-31', 'January 2024');
    console.log('Period created');
} catch (error) {
    console.log('Cannot create:', error.message);
    // Period might overlap!
}

// 3. Set as current
pm.setCurrentPeriod('2024-01-01', '2024-01-31');

// 4. Load attendance for this period
const attendance = await pm.getAttendanceForCurrentPeriod();

// 5. Filter by employee assignment
const adminStaff = attendance.filter(a => a.assignment === 'admin');

// 6. Calculate totals
const totalHours = adminStaff.reduce((sum, a) => sum + a.hours_worked, 0);

console.log(`Admin staff worked ${totalHours} hours this period`);
```

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "Period overlaps..." | Dates conflict with existing period | Choose different dates or delete old period |
| "Invalid date format" | Wrong date format | Use YYYY-MM-DD format |
| "Cannot delete period" | Attendance records exist | Delete attendance records first or archive period |
| "start_date must be before end_date" | Reversed dates | Ensure start < end |

## Testing

### Test Overlap Prevention
```javascript
// This should succeed
await pm.createPeriod('2024-01-01', '2024-01-31');

// This should fail (overlaps)
try {
    await pm.createPeriod('2024-01-15', '2024-02-15');
} catch (e) {
    console.log('Correctly prevented:', e.message);
}

// This should succeed (no overlap)
await pm.createPeriod('2024-02-01', '2024-02-29');
```

### Test Data Filtering
```javascript
// Setup two periods
await pm.createPeriod('2024-01-01', '2024-01-31');
await pm.createPeriod('2024-02-01', '2024-02-29');

// Load January data
pm.setCurrentPeriod('2024-01-01', '2024-01-31');
let jan = await pm.getAttendanceForCurrentPeriod();

// Load February data
pm.setCurrentPeriod('2024-02-01', '2024-02-29');
let feb = await pm.getAttendanceForCurrentPeriod();

// Should be different datasets
console.assert(jan.length !== feb.length, 'Periods filtered correctly');
```

## Integration with Existing System

### Attendance Tab Updates
- Add period selection interface
- Filter all queries by selected period
- Validate period exists before loading data
- Show current period in header

### Payroll System Updates
- When generating payroll, specify period
- Only include attendance from that period
- Prevent payroll generation across period boundaries

### Reports
- All reports should accept period filter
- Display period range in report header
- Summary statistics per period

## Migration from Old System

If migrating existing attendance data:

1. Identify date ranges in attendance data
2. Create corresponding `attendance_periods` records
3. Ensure no overlaps
4. Update attendance records with `period_start` and `period_end` if empty
5. Verify data integrity

```sql
-- Example: Create periods from existing attendance data
INSERT INTO attendance_periods (period_start, period_end, description, status)
SELECT DATE_TRUNC('month', date)::DATE as period_start,
       DATE_TRUNC('month', date)::DATE + INTERVAL '1 month' - INTERVAL '1 day' as period_end,
       TO_CHAR(DATE_TRUNC('month', date), 'Month YYYY') as description,
       'active'
FROM attendance
GROUP BY DATE_TRUNC('month', date)
ON CONFLICT DO NOTHING;
```

## Performance Optimization

### Indexes
The system includes these indexes for fast queries:
- `idx_attendance_periods_dates` - Speeds up overlap checks
- `idx_attendance_periods_status` - Speeds up active period queries
- `idx_attendance_period` - Speeds up period filtering in attendance table

### Query Optimization
- Overlap checks use date range logic (efficient)
- Period queries use indexed columns
- Bulk operations batch-process records

## Troubleshooting

### Period Not Showing in List
```javascript
// Reload periods
await pm.loadPeriods();
console.log(pm.periods); // Check if loaded
```

### Attendance Data Not Filtering
```javascript
// Verify current period is set
console.log('Current period:', pm.getCurrentPeriod());

// Verify period exists in database
const period = await pm.getPeriodByDates('2024-01-01', '2024-01-31');
console.log('Period exists:', period);
```

### Cannot Create Period
```javascript
// Check for overlaps
const overlaps = await pm.checkOverlaps('2024-01-01', '2024-01-31');
console.log('Conflicting periods:', overlaps);

// Check date format
console.log('Date format valid:', pm.validateDates('2024-01-01', '2024-01-31'));
```

## Support

For issues or questions about the period management system, check:
1. API responses and error messages
2. Browser console logs
3. Database logs
4. Attendance records for the period
