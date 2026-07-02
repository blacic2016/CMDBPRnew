<?php
require_once __DIR__ . '/public/../config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();
    
    // 1. Crear categoría Raíz
    $stmt = $pdo->prepare("SELECT id FROM ci_categories WHERE name = 'Ubicaciones'");
    $stmt->execute();
    $rootId = $stmt->fetchColumn();
    
    if (!$rootId) {
        $pdo->exec("INSERT INTO ci_categories (name, icon) VALUES ('Ubicaciones', 'fa-map-marked-alt')");
        $rootId = $pdo->lastInsertId();
    }
    
    $levels = ['País', 'Ciudad', 'Localidad', 'Área', 'Cuarto de Telecomunicaciones'];
    $parentId = $rootId;
    
    foreach ($levels as $levelName) {
        $stmt = $pdo->prepare("SELECT id FROM ci_categories WHERE name = ?");
        $stmt->execute([$levelName]);
        $existingId = $stmt->fetchColumn();
        
        if (!$existingId) {
            $icon = 'fa-map-marker';
            if ($levelName == 'País') $icon = 'fa-globe';
            if ($levelName == 'Ciudad') $icon = 'fa-city';
            if ($levelName == 'Localidad') $icon = 'fa-building';
            if ($levelName == 'Área') $icon = 'fa-door-open';
            if ($levelName == 'Cuarto de Telecomunicaciones') $icon = 'fa-server';
            
            $stmtInsert = $pdo->prepare("INSERT INTO ci_categories (parent_id, name, icon, schema_json) VALUES (?, ?, ?, '{}')");
            $stmtInsert->execute([$parentId, $levelName, $icon]);
            $parentId = $pdo->lastInsertId();
        } else {
            // Asegurar que el parent esté correcto
            $stmtUpdate = $pdo->prepare("UPDATE ci_categories SET parent_id = ? WHERE id = ?");
            $stmtUpdate->execute([$parentId, $existingId]);
            $parentId = $existingId;
        }
    }
    
    echo "Jerarquía geográfica configurada con éxito.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
