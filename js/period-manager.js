/**
 * js/period-manager.js
 * Period Management System
 * Handles period creation, validation, filtering, and prevents overlaps
 */

class PeriodManager {
    constructor() {
        this.periods = [];
        this.currentPeriod = null;
        this.apiEndpoint = '/api/period/index.php';
    }

    /**
     * init
     * Loads periods from /api/period/index.php and registers DOM listeners.
     */
    async init() {
        console.log("Initializing Period Manager...");
        await this.loadPeriods();
        this.setupEventListeners();
    }

    /**
     * Load all periods from database
     */
    async loadPeriods() {
        try {
            const response = await fetch(`${this.apiEndpoint}?type=all`);
            if (!response.ok) throw new Error('Failed to load periods');
            
           this.periods = (await response.json()).map(p => ({
    ...p,
    startDate: p.period_start,
    endDate: p.period_end
}));
            console.log(`Loaded ${this.periods.length} periods`);
            return this.periods;
        } catch (error) {
            console.error('Error loading periods:', error);
            return [];
        }
    }

    /**
     * Get active period (current date falls within the period)
     */
    async getActivePeriod() {
        try {
            const response = await fetch(`${this.apiEndpoint}?type=active`);
            if (!response.ok) throw new Error('Failed to get active period');
            
            const data = await response.json();
         this.currentPeriod = data.id
    ? {
        ...data,
        start: data.period_start,
        end: data.period_end
    }
    : null;

// ✅ Save to session so UI gets correct structure
if (this.currentPeriod) {
    sessionStorage.setItem('currentPeriod', JSON.stringify(this.currentPeriod));
}
            return this.currentPeriod;
        } catch (error) {
            console.error('Error getting active period:', error);
            return null;
        }
    }

    /**
     * Create new period with overlap validation
     */
    async createPeriod(startDate, endDate, description = null) {
        try {
            // Validate dates
            if (!this.validateDates(startDate, endDate)) {
                throw new Error('Invalid date format. Use YYYY-MM-DD');
            }

            if (new Date(startDate) >= new Date(endDate)) {
                throw new Error('Start date must be before end date');
            }

            // Check for overlaps
            const overlaps = await this.checkOverlaps(startDate, endDate);
            if (overlaps.length > 0) {
                throw new Error(
                    `Period overlaps with existing period(s): ${overlaps.map(p => 
                        `${p.period_start} to ${p.period_end}`
                    ).join(', ')}`
                );
            }

            // Create period
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    period_start: startDate,
                    period_end: endDate,
                    description: description
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to create period');
            }

            const result = await response.json();
            await this.loadPeriods(); // Refresh period list
            
            return result;
        } catch (error) {
            console.error('Error creating period:', error);
            throw error;
        }
    }

    /**
     * Check for overlapping periods
     */
    async checkOverlaps(startDate, endDate) {
        try {
            const response = await fetch(
                `${this.apiEndpoint}?type=overlaps&start_date=${startDate}&end_date=${endDate}`
            );
            if (!response.ok) throw new Error('Failed to check overlaps');
            
            return await response.json();
        } catch (error) {
            console.error('Error checking overlaps:', error);
            return [];
        }
    }

    /**
     * Get specific period by dates
     */
    async getPeriodByDates(startDate, endDate) {
        try {
            const response = await fetch(
                `${this.apiEndpoint}?type=specific&start_date=${startDate}&end_date=${endDate}`
            );
            if (!response.ok) throw new Error('Failed to get period');
            
            return await response.json();
        } catch (error) {
            console.error('Error getting period:', error);
            return null;
        }
    }

    /**
     * Delete a period
     */
    async deletePeriod(id) {
        try {
            const response = await fetch(`${this.apiEndpoint}?id=${id}`, {
                method: 'DELETE'
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to delete period');
            }

            await this.loadPeriods(); // Refresh period list
            return await response.json();
        } catch (error) {
            console.error('Error deleting period:', error);
            throw error;
        }
    }

    /**
     * Set current period
     */
    setCurrentPeriod(startDate, endDate, dispatchEvent = true) {
        this.currentPeriod = {
            start: startDate,
            end: endDate
        };
        
        // Store in session storage for persistence
        sessionStorage.setItem('currentPeriod', JSON.stringify(this.currentPeriod));
        
        // Dispatch event for other components to listen (skip if called from event handler)
        if (dispatchEvent) {
            this.dispatchPeriodChangedEvent();
        }
    }

    /**
     * Get current period
     */
    getCurrentPeriod() {
        if (!this.currentPeriod) {
            const stored = sessionStorage.getItem('currentPeriod');
            if (stored) {
                this.currentPeriod = JSON.parse(stored);
            }
        }
        return this.currentPeriod;
    }

    /**
     * Clear current period
     */
    clearCurrentPeriod(skipEvent = false) {
        this.currentPeriod = null;
        sessionStorage.removeItem('currentPeriod');
        if (!skipEvent) {
            this.dispatchPeriodChangedEvent();
        }
    }

    /**
     * Get attendance data for current period
     */
    async getAttendanceForCurrentPeriod() {
        const period = this.getCurrentPeriod();
        if (!period || !period.start || !period.end) {
            throw new Error('No period selected');
        }

        try {
            const response = await fetch(
                `/api/attendance/eda.php?period_start=${period.start}&period_end=${period.end}`
            );
            if (!response.ok) throw new Error('Failed to fetch attendance');
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching attendance:', error);
            throw error;
        }
    }

    /**
     * Validate date format
     */
    validateDates(startDate, endDate) {
        const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
        return dateRegex.test(startDate) && dateRegex.test(endDate);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for period changes
        document.addEventListener('periodChanged', (e) => {
    console.log('Period changed:', e.detail);

    const current = this.getCurrentPeriod();

    if (
    !current ||
    current.start !== e.detail.start ||
    current.end !== e.detail.end
) {
    this.setCurrentPeriod(e.detail.start, e.detail.end, false);
}
});
    }

    /**
     * Dispatch period changed event
     */
   dispatchPeriodChangedEvent() {
    const event = new CustomEvent('periodChanged', {
        detail: this.getCurrentPeriod() // must be { start, end }
    });
    document.dispatchEvent(event);
}

    /**
     * Format period for display
     */
    formatPeriodDisplay(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        // Format as MM/DD/YY
        const formatDate = (date) => {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const year = String(date.getFullYear()).slice(-2); // Get last 2 digits of year
            return `${month}/${day}/${year}`;
        };
        
        const startStr = formatDate(start);
        const endStr = formatDate(end);
        
        return `${startStr} → ${endStr}`;
    }

    /**
     * Get sorted periods grouped by year/month
     */
    getGroupedPeriods() {
        const grouped = {};
        
        this.periods.forEach(period => {
            const yearMonth = period.period_start.substring(0, 7); // YYYY-MM
            if (!grouped[yearMonth]) {
                grouped[yearMonth] = [];
            }
            grouped[yearMonth].push(period);
        });
        
        // Sort by date descending
        return Object.entries(grouped)
            .sort(([a], [b]) => b.localeCompare(a))
            .reduce((acc, [key, value]) => {
                acc[key] = value.sort((a, b) => 
                    new Date(b.period_start) - new Date(a.period_start)
                );
                return acc;
            }, {});
    }

    /**
     * Validate period data integrity
     */
    async validatePeriodData(startDate, endDate) {
        try {
            const response = await fetch(
                `/api/attendance/eda.php?period_start=${startDate}&period_end=${endDate}`
            );
            
            if (!response.ok) {
                throw new Error('No attendance data found for this period');
            }
            
            const data = await response.json();
            return {
                valid: true,
                recordCount: Array.isArray(data) ? data.length : 0,
                data: data
            };
        } catch (error) {
            return {
                valid: false,
                error: error.message
            };
        }
    }

    /**
     * Export current period data to JSON for backup
     */
    async exportPeriodData() {
        const period = this.getCurrentPeriod();
        if (!period || !period.start || !period.end) {
            throw new Error('No period selected');
        }

        try {
            // Fetch attendance data for this period
            const attendance = await this.getAttendanceForCurrentPeriod();
            
            const backupData = {
                period_start: period.start,
                period_end: period.end,
                export_date: new Date().toISOString(),
                attendance: attendance || []
            };

            return backupData;
        } catch (error) {
            console.error('Error exporting period data:', error);
            throw error;
        }
    }

    /**
     * Download period data as JSON file
     */
    async downloadPeriodBackup() {
        try {
            const data = await this.exportPeriodData();
            const filename = `attendance_backup_${data.period_start}_to_${data.period_end}.json`;
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            return true;
        } catch (error) {
            console.error('Error downloading backup:', error);
            throw error;
        }
    }

    /**
     * Get all periods for selection
     */
    getAllPeriods() {
        return this.periods || [];
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PeriodManager;
} else {
    window.PeriodManager = PeriodManager;
}
