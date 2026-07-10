<?php
/**
 * Scratch script to add secondary credential columns
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

echo "Adding secondary credential columns to PASSWORD module tables...\n";

try {
    $pdo = getPDO();
    if (!$pdo) {
        throw new Exception("Could not connect to the database.");
    }

    // Add columns to password_entries
    try {
        $pdo->exec("ALTER TABLE `password_entries` ADD COLUMN `username_sec` VARCHAR(255) NULL AFTER `password` ");
        echo "✅ Added 'username_sec' to 'password_entries'.\n";
    } catch (Exception $e) {
        echo "ℹ️ 'username_sec' already exists or could not be added: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `password_entries` ADD COLUMN `password_sec` VARCHAR(255) NULL AFTER `username_sec` ");
        echo "✅ Added 'password_sec' to 'password_entries'.\n";
    } catch (Exception $e) {
        echo "ℹ️ 'password_sec' already exists or could not be added: " . $e->getMessage() . "\n";
    }

    // Add columns to password_history
    try {
        $pdo->exec("ALTER TABLE `password_history` ADD COLUMN `username_sec` VARCHAR(255) NULL AFTER `password` ");
        echo "✅ Added 'username_sec' to 'password_history'.\n";
    } catch (Exception $e) {
        echo "ℹ️ 'username_sec' already exists in history or could not be added: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `password_history` ADD COLUMN `password_sec` VARCHAR(255) NULL AFTER `username_sec` ");
        echo "✅ Added 'password_sec' to 'password_history'.\n";
    } catch (Exception $e) {
        echo "ℹ️ 'password_sec' already exists in history or could not be added: " . $e->getMessage() . "\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}
