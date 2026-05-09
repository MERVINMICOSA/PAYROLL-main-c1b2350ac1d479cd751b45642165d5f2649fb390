<?php
// api/fix-period-settings-table.php
// Create period_settings table with multi-period support

require_once __DIR__ . '/config/database.php';

try {
    $pdo = DatabaseConfig::getInstance();

    echo "Attempting to create/update period_settings table...\n";

    // Create the table if not exists (SERIAL auto-increment, no forced id=1)
    $sql = "
        CREATE TABLE IF NOT EXISTS period_settings (
            id SERIAL PRIMARY KEY,
            current_period_start DATE NOT NULL,
            current_period_end DATE NOT NULL,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($sql);

    echo "✓ Table 'period_settings' created successfully (or already exists)\n";

    // Verify the table exists
    $stmt = $pdo->query("
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'period_settings'
    ");
    $result = $stmt->fetch();

    if ($result) {
        echo "✓ Verification successful: period_settings table exists\n";

        // Check existing records
        $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM period_settings");
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC);
        echo "  → Found " . $count['count'] . " existing period(s)\n";

        echo "\n✓✓✓ DATABASE FIX COMPLETE ✓✓✓\n";
        echo "You can now save multiple periods. Each period will be checked for overlaps.\n";
    } else {
        echo "✗ ERROR: Table still doesn't exist\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
}
?>

