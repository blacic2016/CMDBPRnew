<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();
    
    // Create ci_attributes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ci_attributes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'string',
            description TEXT,
            is_required TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table ci_attributes created/exists.\n";
    
    // Alter ci_categories
    $stmt = $pdo->query("SHOW COLUMNS FROM ci_categories LIKE 'description'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ci_categories ADD COLUMN description TEXT DEFAULT NULL;");
        echo "Column description added to ci_categories.\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM ci_categories LIKE 'created_at'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ci_categories ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;");
        echo "Column created_at added to ci_categories.\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM ci_categories LIKE 'created_by'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ci_categories ADD COLUMN created_by INT DEFAULT NULL;");
        echo "Column created_by added to ci_categories.\n";
    }

    echo "DB updates finished successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
