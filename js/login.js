// js/login.js — Session-based login helper
// Use this instead of inline login logic for consistency.

document.addEventListener('DOMContentLoaded', () => {
    // If already logged in (session exists), redirect to dashboard
    checkSession().then(user => {
        if (user && window.location.pathname.includes('index.html')) {
            const dashboards = {
                'superadmin': 'dashboardSadmin.html',
                'accountant': 'dashboard.html',
                'oic': 'dashboard-oic.html',
                'teacher': 'teacher-dashboard.html',
                'guard': 'dashboard-guard.html',
                'sa': 'dashboard-sa.html',
                'admin_staff': 'dashboard-staff.html'
            };
            window.location.href = dashboards[user.role] || 'dashboard.html';
        }
    });

    // Load remembered username
    const rememberedUser = localStorage.getItem('rememberedUser');
    if (rememberedUser) {
        const usernameInput = document.getElementById('username');
        if (usernameInput) usernameInput.value = rememberedUser;
        const rememberCheckbox = document.getElementById('rememberMe');
        if (rememberCheckbox) rememberCheckbox.checked = true;
    }

    // Toggle password visibility
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
        });
    }

    // Attach login handler to form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe')?.checked || false;

            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalText = submitBtn?.textContent || 'Sign In';
            if (submitBtn) {
                submitBtn.textContent = 'Logging in...';
                submitBtn.disabled = true;
            }

            const result = await handleLogin(username, password, rememberMe);

            if (submitBtn) {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }

            if (result.success) {
                window.location.href = result.redirect;
            } else {
                const errorDiv = document.getElementById('loginError');
                if (errorDiv) {
                    errorDiv.textContent = result.message;
                    errorDiv.style.display = 'block';
                } else {
                    alert(result.message);
                }
            }
        });
    }
});

async function checkSession() {
    try {
        const response = await fetch('/api/auth/session.php', {
            method: 'GET',
            credentials: 'include'
        });
        if (response.ok) {
            const data = await response.json();
            return data.user || null;
        }
    } catch (e) {
        console.error('Session check error:', e);
    }
    return null;
}

async function handleLogin(username, password, rememberMe) {
    try {
        const response = await fetch('/api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
            if (rememberMe) {
                localStorage.setItem('rememberedUser', username);
            } else {
                localStorage.removeItem('rememberedUser');
            }

            // Set session state for cross-tab sync (ACTIVE means logged in)
            try {
                localStorage.setItem('payroll_session_state', 'active');
                localStorage.setItem('payroll_session_time', Date.now().toString());
            } catch (e) {
                // Storage might be disabled
                console.warn('Session storage not available');
            }

            const dashboards = {
                'superadmin': 'dashboardSadmin.html',
                'accountant': 'dashboard.html',
                'oic': 'dashboard-oic.html',
                'teacher': 'teacher-dashboard.html',
                'guard': 'dashboard-guard.html',
                'sa': 'dashboard-sa.html',
                'admin_staff': 'dashboard-staff.html'
            };

            const redirectUrl = dashboards[data.user.role] || 'dashboard.html';
            return { success: true, redirect: redirectUrl };
        } else {
            return { success: false, message: data.error || 'Invalid username or password' };
        }
    } catch (error) {
        console.error('Login error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}
