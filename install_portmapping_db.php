<?php
require_once __DIR__ . '/public/../config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();
    $sql = "CREATE TABLE IF NOT EXISTS port_mappings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_component_id INT NOT NULL,
        target_component_id INT NOT NULL,
        cable_type VARCHAR(50) DEFAULT 'UTP Cat6A',
        color_code VARCHAR(20) DEFAULT '#0000FF',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (source_component_id) REFERENCES ci_components(id),
        FOREIGN KEY (target_component_id) REFERENCES ci_components(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table port_mappings created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
