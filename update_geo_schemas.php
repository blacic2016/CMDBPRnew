<?php
require_once __DIR__ . '/public/../config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();
    
    // Esquema genérico para Ubicaciones (Añade 'Siglas')
    $schema = json_encode([
        'properties' => [
            'siglas' => [
                'type' => 'string',
                'description' => 'Código o Sigla (Ej. EC, UIO)',
                'group' => 'Datos Geográficos'
            ]
        ],
        'required' => ['siglas']
    ]);

    // Actualizamos las categorías que creamos
    $geo_categories = ['País', 'Ciudad', 'Localidad', 'Área', 'Cuarto de Telecomunicaciones'];
    
    $stmt = $pdo->prepare("UPDATE ci_categories SET schema_json = ? WHERE name = ?");
    
    foreach ($geo_categories as $cat) {
        $stmt->execute([$schema, $cat]);
    }
    
    echo "Esquemas JSON actualizados con 'siglas' para las categorías geográficas.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
