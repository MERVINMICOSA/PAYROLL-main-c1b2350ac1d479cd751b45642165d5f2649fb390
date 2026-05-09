// ============================================
// XSS PROTECTION HELPER
// ============================================
// Prevents XSS attacks in JavaScript
// ============================================

class XSSProtection {
    
    // Escape HTML to prevent XSS
    static escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/\//g, '&#x2F;');
    }
    
    // Escape JavaScript string
    static escapeJs(str) {
        if (!str) return '';
        return str
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '\\"')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '\\r');
    }
    
    // Escape URL parameter
    static escapeUrl(str) {
        if (!str) return '';
        return encodeURIComponent(str);
    }
    
    // Safe innerHTML replacement (use textContent when possible)
    static safeSetHTML(element, html) {
        if (!element) return;
        // Sanitize HTML (remove scripts)
        const sanitized = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        element.innerHTML = sanitized;
    }
    
    // Safe text content setter
    static safeSetText(element, text) {
        if (!element) return;
        element.textContent = text;
    }
    
    // Safe attribute setter
    static safeSetAttribute(element, attr, value) {
        if (!element) return;
        // Remove dangerous attributes
        const dangerous = ['onclick', 'onload', 'onerror', 'onmouseover', 'onfocus'];
        if (dangerous.includes(attr.toLowerCase())) {
            console.warn(`Blocked setting dangerous attribute: ${attr}`);
            return;
        }
        element.setAttribute(attr, value);
    }
    
    // Validate URL (prevent javascript: protocol)
    static validateURL(url) {
        if (!url) return false;
        const protocol = url.toLowerCase().split(':')[0];
        const dangerous = ['javascript', 'data', 'vbscript', 'file'];
        return !dangerous.includes(protocol);
    }
    
    // Sanitize user input for display
    static sanitizeForDisplay(input) {
        if (typeof input !== 'string') return input;
        return this.escapeHtml(input.trim());
    }
    
    // Sanitize user input for JSON
    static sanitizeForJSON(input) {
        if (typeof input !== 'string') return input;
        return input.replace(/[\u0000-\u001F\u007F-\u009F]/g, '');
    }
}

// Override console.log for development safety (optional)
if (window.console) {
    const originalLog = console.log;
    console.log = function(...args) {
        // Sanitize arguments before logging
        const sanitized = args.map(arg => {
            if (typeof arg === 'string') {
                return XSSProtection.sanitizeForDisplay(arg);
            }
            return arg;
        });
        originalLog.apply(console, sanitized);
    };
}

// Make available globally
window.XSSProtection = XSSProtection;