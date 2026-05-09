// js/firebase-config.js
// Firebase configuration for employee storage

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js";
import { 
    getFirestore, 
    collection, 
    addDoc, 
    getDocs, 
    doc, 
    updateDoc, 
    deleteDoc, 
    query, 
    where,
    getDoc
} from "https://www.gstatic.com/firebasejs/10.7.0/firebase-firestore.js";

// Fetch Firebase configuration from server API (no hardcoded API key)
let app = null;
let db = null;

async function initFirebaseConfig() {
    if (app) return true;
    try {
        const response = await fetch('/api/config/firebase-config.php');
        const result = await response.json();
        if (!result.success) {
            console.warn('Firebase config error:', result.error);
            return false;
        }
        app = initializeApp(result.config);
        db = getFirestore(app);
        return true;
    } catch (error) {
        console.error('Firebase init error:', error);
        return false;
    }
}

// Try to auto-initialize
initFirebaseConfig();

// Export db — consumers should ensure initFirebaseConfig() resolved before use
// or check that db is not null. This module is kept for backward compatibility.

// Export Firebase functions
export { 
    db, 
    initFirebaseConfig,
    collection, 
    addDoc, 
    getDocs, 
    doc, 
    updateDoc, 
    deleteDoc, 
    query, 
    where,
    getDoc
};
