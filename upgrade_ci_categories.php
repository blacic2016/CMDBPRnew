<?php
require_once __DIR__ . '/public/../config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM ci_categories LIKE 'requires_parent_instance'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ci_categories ADD COLUMN requires_parent_instance BOOLEAN DEFAULT FALSE");
        echo "Columna 'requires_parent_instance' añadida a ci_categories.\n";
    }

    // Set flag for existing geographical levels (except root and País)
    $geo_children = ['Ciudad', 'Localidad', 'Área', 'Cuarto de Telecomunicaciones'];
    $placeholders = implode(',', array_fill(0, count($geo_children), '?'));
    
    $stmt = $pdo->prepare("UPDATE ci_categories SET requires_parent_instance = TRUE WHERE name IN ($placeholders)");
    $stmt->execute($geo_children);
    
    echo "Flags de dependencia topológica configurados con éxito.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
