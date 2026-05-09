// Settings Module
let selectedSetting = 'system';

// Default settings values
const defaultSettings = {
    system: {
        currency: '₱',
        dateFormat: 'MM/DD/YYYY',
        payPeriod: 'monthly',
        overtimeRate: '1.25'
    },
    tax: {
        taxMethod: 'philippines',
        taxRate: '20',
        minimumTax: '0'
    },
    deduction: {
        sssRate: '4.5',
        sssMax: '900',
        philhealthRate: '3',
        philhealthMax: '300',
        pagibigRate: '2',
        pagibigMax: '100'
    },
    company: {
        companyName: '',
        companyAddress: '',
        companyPhone: '',
        companyEmail: '',
        companyTIN: ''
    }
};

window.addEventListener("DOMContentLoaded", async () => {
    try {
        await Database.ensureDatabaseReady();
    } catch (error) {
        console.error("Error initializing settings:", error);
    }
    
    setupSettings();
    await loadSettings();
});

// Initialize settings
function setupSettings() {
    // Set default tab
    selectSetting('system');
}

// Load settings from database
async function loadSettings() {
    try {
        // Load each category
        const systemSettings = await Database.getSettingByKey('system');
        const taxSettings = await Database.getSettingByKey('tax');
        const deductionSettings = await Database.getSettingByKey('deduction');
        const companySettings = await Database.getSettingByKey('company');

        // Apply loaded settings or defaults
        if (systemSettings && systemSettings.data) {
            applySettingsToForm('system', systemSettings.data);
        } else {
            applySettingsToForm('system', defaultSettings.system);
        }

        if (taxSettings && taxSettings.data) {
            applySettingsToForm('tax', taxSettings.data);
        } else {
            applySettingsToForm('tax', defaultSettings.tax);
        }

        if (deductionSettings && deductionSettings.data) {
            applySettingsToForm('deduction', deductionSettings.data);
        } else {
            applySettingsToForm('deduction', defaultSettings.deduction);
        }

        if (companySettings && companySettings.data) {
            applySettingsToForm('company', companySettings.data);
        } else {
            applySettingsToForm('company', defaultSettings.company);
        }
    } catch (error) {
        console.error("Error loading settings:", error);
        // Apply defaults on error
        applySettingsToForm('system', defaultSettings.system);
        applySettingsToForm('tax', defaultSettings.tax);
        applySettingsToForm('deduction', defaultSettings.deduction);
        applySettingsToForm('company', defaultSettings.company);
    }
}

// Apply settings to form fields
function applySettingsToForm(category, data) {
    for (const [key, value] of Object.entries(data)) {
        const element = document.getElementById(key);
        if (element) {
            element.value = value;
        }
    }
}

// Get settings from form
function getSettingsFromForm(category) {
    const data = {};
    const form = document.getElementById('form-' + category);
    if (form) {
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            data[input.id] = input.value;
        });
    }
    return data;
}

// Handle setting selection
window.selectSetting = function(key) {
    selectedSetting = key;
    
    // Update tab highlighting
    const tabs = document.querySelectorAll('.setting-item');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    const activeTab = document.getElementById('tab-' + key);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Show corresponding form
    const forms = document.querySelectorAll('.settings-form');
    forms.forEach(form => form.style.display = 'none');
    
    const activeForm = document.getElementById('form-' + key);
    if (activeForm) {
        activeForm.style.display = 'block';
    }
};

// Save settings
window.saveSettings = async function() {
    try {
        const data = getSettingsFromForm(selectedSetting);
        
        // Save to database
        const setting = {
            key: selectedSetting,
            data: data,
            updatedAt: new Date().toISOString()
        };
        
        await Database.saveSetting(setting);
        
        alert(selectedSetting.toUpperCase() + ' settings saved successfully!');
    } catch (error) {
        console.error("Error saving settings:", error);
        alert("Failed to save settings. Please try again.");
    }
};
