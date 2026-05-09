<?php
// api/fix-notifications-column.php
// Add missing is_read column to notifications table

require_once __DIR__ . '/config/database.php';

try {
    $pdo = DatabaseConfig::getInstance();
    
    echo "Attempting to add is_read column to notifications table...\n";
    
    // Add the is_read column if it doesn't exist
    $sql = "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE";
    $pdo->exec($sql);
    
    echo "✓ Column 'is_read' added successfully (or already exists)\n";
    
    // Create index if it doesn't exist
    $sql = "CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read)";
    $pdo->exec($sql);
    
    echo "✓ Index created successfully\n";
    
    // Verify the column exists
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='notifications' AND column_name='is_read'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✓ Verification successful: is_read column exists in notifications table\n";
        echo "\n✓✓✓ DATABASE FIX COMPLETE ✓✓✓\n";
    } else {
        echo "✗ ERROR: Column still doesn't exist\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
}
?>
