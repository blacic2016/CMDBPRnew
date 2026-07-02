<?php
require_once __DIR__ . '/public/../config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();

    // Obtener IDs de categorías
    $stmt = $pdo->prepare("SELECT id FROM ci_categories WHERE name = ?");
    $stmt->execute(['País']);
    $cat_pais = $stmt->fetchColumn();

    $stmt->execute(['Ciudad']);
    $cat_ciudad = $stmt->fetchColumn();

    if (!$cat_pais || !$cat_ciudad) {
        die("Error: No se encontraron las categorías País o Ciudad.\n");
    }

    $pdo->beginTransaction();

    // 1. Insertar Países
    $stmtInst = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, status, attributes_json) VALUES (?, ?, 'Activo', ?)");
    
    $stmtInst->execute([$cat_pais, 'Ecuador', json_encode(['siglas' => 'EC'])]);
    $id_ecuador = $pdo->lastInsertId();

    $stmtInst->execute([$cat_pais, 'Perú', json_encode(['siglas' => 'PE'])]);
    $stmtInst->execute([$cat_pais, 'Bolivia', json_encode(['siglas' => 'BO'])]);

    // 2. Insertar Ciudades
    $stmtInst->execute([$cat_ciudad, 'Quito', json_encode(['siglas' => 'UIO'])]);
    $id_quito = $pdo->lastInsertId();

    $stmtInst->execute([$cat_ciudad, 'Guayaquil', json_encode(['siglas' => 'GYE'])]);
    $id_guayaquil = $pdo->lastInsertId();

    // 3. Crear Relaciones (Ubicado en / Pertenece a)
    $stmtRel = $pdo->prepare("INSERT INTO ci_relationships (source_type, source_id, target_type, target_id, relation_type, impact) VALUES ('ci_instances', ?, 'ci_instances', ?, 'Contains', 'Sí')");
    
    // Quito está en Ecuador
    $stmtRel->execute([$id_quito, $id_ecuador]);
    // Guayaquil está en Ecuador
    $stmtRel->execute([$id_guayaquil, $id_ecuador]);

    $pdo->commit();
    echo "Instancias y relaciones topológicas sembradas con éxito.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
