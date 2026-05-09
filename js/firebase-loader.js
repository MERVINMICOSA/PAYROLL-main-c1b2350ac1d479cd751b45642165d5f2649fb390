// js/firebase-loader.js - Load Firebase config from server
let firebaseConfig = null;
let firebaseInitialized = false;

async function loadFirebaseConfig() {
    try {
        const response = await fetch('/api/config/firebase-config.php');
        const data = await response.json();
        
        if (!data.success) {
            console.error('Failed to load Firebase config:', data.error);
            return null;
        }
        
        firebaseConfig = data.config;
        console.log('Firebase config loaded from server');
        return firebaseConfig;
    } catch (error) {
        console.error('Error loading Firebase config:', error);
        return null;
    }
}

async function initFirebase() {
    if (firebaseInitialized) return true;
    
    const config = await loadFirebaseConfig();
    if (!config) return false;
    
    // Check if Firebase is already loaded
    if (typeof firebase === 'undefined') {
        console.error('Firebase SDK not loaded');
        return false;
    }
    
    // Initialize Firebase
    if (!firebase.apps.length) {
        firebase.initializeApp(config);
        console.log('Firebase initialized with server config');
    }
    
    firebaseInitialized = true;
    return true;
}

// Export for use in other modules
window.initFirebase = initFirebase;
window.getFirebaseConfig = () => firebaseConfig;