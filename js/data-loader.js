// ============================================
// DATA LOADER - Populate IndexedDB from Server
// ============================================
// Loads test data from the server and caches it in IndexedDB
// Runs automatically on app startup

class DataLoader {
    constructor() {
        this.apiBase = '';
    }

    async loadFromServer(endpoint) {
        try {
            const response = await fetch(`${this.apiBase}${endpoint}`, {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.warn(`Failed to load ${endpoint}:`, error.message);
            return null;
        }
    }

    async populateIndexedDB() {
        console.log("📥 Attempting to load data from server...");

        // Wait for Database to be ready
        if (typeof Database === 'undefined' || !Database.ensureDatabaseReady) {
            console.log("⏳ Database not ready yet, will retry...");
            return;
        }

        try {
            await Database.ensureDatabaseReady();

            // Try to load each data type from the server
            const endpoints = [
                { name: 'employees', store: 'employees', url: '/api/employees' },
                { name: 'payroll', store: 'payroll', url: '/api/payroll' },
                { name: 'attendance', store: 'attendance', url: '/api/attendance/admin-master' },
                { name: 'users', store: 'users', url: '/api/users' }
            ];

            const db = await Database.ensureDatabaseReady();

            for (const endpoint of endpoints) {
                try {
                    const data = await this.loadFromServer(endpoint.url);
                    
                    if (Array.isArray(data) && data.length > 0) {
                        // Cache to IndexedDB
                        const tx = db.transaction([endpoint.store], 'readwrite');
                        const store = tx.objectStore(endpoint.store);
                        
                        // Clear existing data
                        await new Promise((resolve, reject) => {
                            const clearReq = store.clear();
                            clearReq.onsuccess = resolve;
                            clearReq.onerror = reject;
                        });

                        // Add new data
                        for (const record of data) {
                            await new Promise((resolve, reject) => {
                                const addReq = store.add(record);
                                addReq.onsuccess = resolve;
                                addReq.onerror = reject;
                            });
                        }

                        console.log(`✅ Cached ${data.length} ${endpoint.name} records`);
                    }
                } catch (error) {
                    console.log(`⚠️  Could not load ${endpoint.name}: ${error.message}`);
                }
            }

            console.log("✓ Data loading complete");
        } catch (error) {
            console.warn("Data loading failed:", error);
        }
    }

    // Auto-load on startup
    async autoLoad() {
        // Wait a bit for Database to initialize
        setTimeout(() => {
            this.populateIndexedDB();
        }, 1000);
    }
}

// Create global singleton
window.DataLoader = window.DataLoader || new DataLoader();

// Auto-load data when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.DataLoader.autoLoad();
    });
} else {
    window.DataLoader.autoLoad();
}
