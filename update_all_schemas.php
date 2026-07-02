<?php
require_once __DIR__ . '/public/../config.php';
require_once __DIR__ . '/src/db.php';

try {
    $pdo = getPDO();

    // --- 1. Plantilla Base para HARDWARE (Aplica a Switches, Routers, Servidores) ---
    $hardware_schema = json_encode([
        'properties' => [
            'marca' => ['type' => 'string', 'group' => 'Ficha Técnica', 'description' => 'Fabricante (Ej. Cisco, HP)'],
            'modelo' => ['type' => 'string', 'group' => 'Ficha Técnica'],
            'numero_serie' => ['type' => 'string', 'group' => 'Ficha Técnica'],
            'fin_garantia' => ['type' => 'date', 'group' => 'Mantenimiento', 'format' => 'date']
        ],
        'required' => ['marca', 'modelo', 'numero_serie']
    ]);

    // Plantilla Específica para Servidores (Heredarán lo de Hardware + Esto)
    $server_schema = json_encode([
        'properties' => [
            'ram_gb' => ['type' => 'integer', 'group' => 'Recursos Computacionales'],
            'cpu_cores' => ['type' => 'integer', 'group' => 'Recursos Computacionales'],
            'sistema_operativo' => ['type' => 'string', 'group' => 'Recursos Computacionales']
        ],
        'required' => ['ram_gb']
    ]);

    // Plantilla Específica para Routers (Añadimos Proveedor)
    $router_schema = json_encode([
        'properties' => [
            'proveedor_enlace' => [
                'type' => 'string', 
                'enum' => ['Telefónica', 'Telconet', 'Claro', 'PuntoNet', 'Otro'],
                'group' => 'Conectividad WAN'
            ]
        ]
    ]);

    // --- 2. Plantilla para SOFTWARE ---
    $software_schema = json_encode([
        'properties' => [
            'version' => ['type' => 'string', 'group' => 'Licenciamiento'],
            'tipo_licencia' => [
                'type' => 'string', 
                'enum' => ['Suscripción Anual', 'Perpetua', 'Open Source'], 
                'group' => 'Licenciamiento'
            ],
            'fecha_renovacion' => ['type' => 'date', 'group' => 'Licenciamiento', 'format' => 'date']
        ],
        'required' => ['version', 'tipo_licencia']
    ]);

    // --- 3. Plantilla para PERSONAL (Administradores, Especialistas) ---
    $personal_schema = json_encode([
        'properties' => [
            'correo' => ['type' => 'string', 'group' => 'Datos de Contacto'],
            'telefono' => ['type' => 'string', 'group' => 'Datos de Contacto'],
            'cargo' => ['type' => 'string', 'group' => 'Rol Institucional']
        ],
        'required' => ['correo']
    ]);

    // Ejecutar Actualizaciones
    // HARDWARE
    $pdo->query("UPDATE ci_categories SET schema_json = '$hardware_schema' WHERE name IN ('Hardware CI', 'Switch', 'Switch base', 'Switch base1')");
    $pdo->query("UPDATE ci_categories SET schema_json = '$server_schema' WHERE name = 'Servers'");
    $pdo->query("UPDATE ci_categories SET schema_json = '$router_schema' WHERE name = 'Routers'");
    
    // SOFTWARE
    $pdo->query("UPDATE ci_categories SET schema_json = '$software_schema' WHERE name IN ('Software CIs', 'Operating Systems', 'Databases')");
    
    // PERSONAL
    $pdo->query("UPDATE ci_categories SET schema_json = '$personal_schema' WHERE name IN ('Personal Datacenter', 'System Administrators', 'Support Teams', 'Gestores', 'Especialistas')");

    echo "Plantillas JSON instaladas para Hardware, Software y Personal.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
