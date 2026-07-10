<?php
/**
 * Database installer for PASSWORD module
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

echo "🛠️ Creating PASSWORD module database tables...\n";

try {
    $pdo = getPDO();
    if (!$pdo) {
        throw new Exception("Could not connect to the database.");
    }

    // 1. Create password_vault_settings table
    $sql1 = "CREATE TABLE IF NOT EXISTS `password_vault_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) UNIQUE NOT NULL,
        `setting_value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql1);
    echo "✅ Table 'password_vault_settings' processed.\n";

    // 2. Create password_entries table
    $sql2 = "CREATE TABLE IF NOT EXISTS `password_entries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `url` VARCHAR(1000) NOT NULL,
        `username` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `observations` TEXT NULL,
        `tags` VARCHAR(255) NULL,
        `screenshot_path` VARCHAR(255) NULL,
        `created_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql2);
    echo "✅ Table 'password_entries' processed.\n";

    // 3. Create password_history table
    $sql3 = "CREATE TABLE IF NOT EXISTS `password_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `entry_id` INT NOT NULL,
        `username` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `observations` TEXT NULL,
        `changed_by` INT NOT NULL,
        `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `change_type` VARCHAR(50) NOT NULL DEFAULT 'modification',
        FOREIGN KEY (`entry_id`) REFERENCES `password_entries`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql3);
    echo "✅ Table 'password_history' processed.\n";

    // 4. Create the screenshot upload folder
    $screenshot_dir = __DIR__ . '/../public/uploads/screenshots';
    if (!is_dir($screenshot_dir)) {
        if (mkdir($screenshot_dir, 0777, true)) {
            echo "✅ Created screenshot folder: public/uploads/screenshots\n";
        } else {
            echo "⚠️ Warning: Could not create screenshot folder. Please create it manually.\n";
        }
    } else {
        echo "✅ Screenshot folder already exists.\n";
    }

    echo "🎉 Database installation completed successfully!\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
