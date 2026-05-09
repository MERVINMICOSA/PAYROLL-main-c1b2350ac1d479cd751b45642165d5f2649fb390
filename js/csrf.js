// ============================================
// CSRF PROTECTION MODULE - SIMPLIFIED FOR LOGIN
// ============================================

class CSRFProtection {
    constructor() {
        this.tokenKey = 'csrf_token';
        this.tokenLength = 32;
        this.initialized = false;
    }

    // Generate secure random token
    generateToken() {
        const array = new Uint8Array(this.tokenLength);
        crypto.getRandomValues(array);
        const token = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
        sessionStorage.setItem(this.tokenKey, token);
        return token;
    }

    // Get current token
    getToken() {
        let token = sessionStorage.getItem(this.tokenKey);
        if (!token) {
            token = this.generateToken();
        }
        return token;
    }

    // Validate token (simplified - no expiry for now)
    validateToken(token) {
        const storedToken = this.getToken();
        if (!storedToken || storedToken !== token) {
            return false;
        }
        return true;
    }

    // Add CSRF token to forms (without auto-validation on submit)
    addTokenToForm(form) {
        if (form.hasAttribute('data-csrf-protected')) return;
        
        let tokenInput = form.querySelector('input[name="_csrf"], input[name="csrf"]');
        if (!tokenInput) {
            tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_csrf';
            form.appendChild(tokenInput);
        }
        
        tokenInput.value = this.getToken();
        form.setAttribute('data-csrf-protected', 'true');
        
        this.forms = this.forms || new Set();
        this.forms.add(form);
    }

    // Add token to all forms
    protectAllForms() {
        document.querySelectorAll('form').forEach(form => {
            this.addTokenToForm(form);
        });
    }

    // Initialize
    init() {
        if (this.initialized) return;
        
        // Generate initial token
        this.getToken();
        
        // Protect existing forms
        this.protectAllForms();
        
        // Watch for dynamically added forms
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'FORM') {
                            this.addTokenToForm(node);
                        }
                        if (node.querySelectorAll) {
                            node.querySelectorAll('form').forEach(form => {
                                this.addTokenToForm(form);
                            });
                        }
                    }
                });
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        this.initialized = true;
        console.log('CSRF Protection initialized (simplified mode)');
    }
}

// Initialize CSRF protection
const CSRF = new CSRFProtection();

// Wait for DOM to be ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CSRF.init());
} else {
    CSRF.init();
}

// Make available globally
window.CSRF = CSRF;