<?php
// ============================================
// SECURE DATABASE — backward-compatible alias
// ============================================
// All logic now lives in the unified Database class.
// This file exists so existing code using `new SecureDatabase()`
// continues to work without modification.
// ============================================

require_once __DIR__ . '/Database.php';

class SecureDatabase extends Database {
    // Inherits everything from Database
}

