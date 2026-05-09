// js/firebase-auth.js - Complete Firebase Authentication
// Self-contained - loads SDK dynamically

let auth = null;
let initialized = false;

// Firebase configuration loaded from server API (no hardcoded API key)
let firebaseConfig = null;

async function fetchFirebaseConfig() {
    if (firebaseConfig) return firebaseConfig;
    try {
        const response = await fetch('/api/config/firebase-config.php');
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Failed to load Firebase config');
        }
        firebaseConfig = result.config;
        return firebaseConfig;
    } catch (error) {
        console.error('Firebase config fetch error:', error);
        throw error;
    }
}

// Load Firebase SDK dynamically
async function loadFirebaseSDK() {
    return new Promise((resolve, reject) => {
        // Check if already loaded
        if (window.firebase && window.firebase.initializeApp && window.firebase.auth) {
            resolve(window.firebase);
            return;
        }
        
        // Load Firebase SDKs
        const script1 = document.createElement('script');
        script1.src = 'https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js';
        script1.onload = () => {
            const script2 = document.createElement('script');
            script2.src = 'https://www.gstatic.com/firebasejs/10.7.0/firebase-auth.js';
            script2.onload = () => {
                setTimeout(() => {
                    if (window.firebase && window.firebase.initializeApp && window.firebase.auth) {
                        resolve(window.firebase);
                    } else {
                        reject(new Error('Firebase SDK loaded but API not available'));
                    }
                }, 100);
            };
            script2.onerror = () => reject(new Error('Failed to load firebase-auth.js'));
            document.head.appendChild(script2);
        };
        script1.onerror = () => reject(new Error('Failed to load firebase-app.js'));
        document.head.appendChild(script1);
    });
}

// Initialize Firebase Auth
async function initFirebaseAuth() {
    if (initialized && auth) return auth;
    
    console.log('Initializing Firebase Auth...');
    
    try {
        const firebase = await loadFirebaseSDK();
        console.log('Firebase SDK loaded');
        
        const config = await fetchFirebaseConfig();
        console.log('Firebase config loaded');
        
        let app;
        if (firebase.apps.length === 0) {
            app = firebase.initializeApp(config);
            console.log('Firebase app initialized');
        } else {
            app = firebase.apps[0];
        }
        
        auth = firebase.auth();
        console.log('Firebase Auth instance created');
        
        initialized = true;
        return auth;
        
    } catch (error) {
        console.error('Firebase init error:', error);
        throw error;
    }
}

// Login with Firebase
async function loginWithFirebase(email, password) {
    try {
        await initFirebaseAuth();
        console.log('Attempting login for:', email);
        
        const userCredential = await auth.signInWithEmailAndPassword(email, password);
        const user = userCredential.user;
        
        console.log('Login successful:', user.email);
        const token = await user.getIdToken();
        
        return {
            success: true,
            user: {
                uid: user.uid,
                email: user.email,
                displayName: user.displayName,
                accessToken: token
            }
        };
    } catch (error) {
        console.error('Login error:', error.code, error.message);
        let message = 'Invalid username or password';
        if (error.code === 'auth/user-not-found') {
            message = 'User not found. Please contact administrator.';
        } else if (error.code === 'auth/wrong-password') {
            message = 'Wrong password. Please try again.';
        } else if (error.code === 'auth/too-many-requests') {
            message = 'Too many attempts. Try again later.';
        }
        return { success: false, error: message };
    }
}

// Logout
async function logoutFirebase() {
    try {
        await initFirebaseAuth();
        await auth.signOut();
        return { success: true };
    } catch (error) {
        console.error('Logout error:', error);
        return { success: false, error: error.message };
    }
}

// Password reset
async function resetPasswordFirebase(email) {
    try {
        await initFirebaseAuth();
        await auth.sendPasswordResetEmail(email);
        return { success: true };
    } catch (error) {
        console.error('Password reset error:', error);
        let message = 'Error sending reset email';
        if (error.code === 'auth/user-not-found') {
            message = 'No account found with this email';
        }
        return { success: false, error: message };
    }
}

// Get current user
async function getCurrentUser() {
    try {
        await initFirebaseAuth();
        return auth.currentUser;
    } catch (error) {
        return null;
    }
}

// Check if logged in
async function isLoggedIn() {
    try {
        await initFirebaseAuth();
        return auth.currentUser !== null;
    } catch (error) {
        return false;
    }
}

// Make functions globally available
window.firebaseAuth = {
    login: loginWithFirebase,
    logout: logoutFirebase,
    resetPassword: resetPasswordFirebase,
    getCurrentUser: getCurrentUser,
    isLoggedIn: isLoggedIn,
    init: initFirebaseAuth
};

console.log('Firebase Auth module loaded - Dynamic SDK loader');
