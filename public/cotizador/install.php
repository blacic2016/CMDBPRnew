<?php
/**
 * Installer script for Cotizador Module
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$pdo = getPDO();
if (!$pdo) {
    die("Error connecting to database.\n");
}

echo "1. Creating tables...\n";

// Drop tables first if we want a fresh start, or just create them
// Let's use CREATE TABLE IF NOT EXISTS
$queries = [
    "CREATE TABLE IF NOT EXISTS `cotizador_specialists` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `nombre` VARCHAR(100) NOT NULL,
      `tipo` VARCHAR(20) NOT NULL, -- 'N1', 'N2', 'N3', 'E1', 'E2', 'GP1', 'GP2', 'BOC'
      `rango_salarial` VARCHAR(100) DEFAULT NULL,
      `salario` DECIMAL(10,2) DEFAULT 0.00,
      `utilizable` DECIMAL(5,2) DEFAULT 0.80,
      `costo_hora_manual` DECIMAL(10,2) DEFAULT 0.00,
      `costo_empresa` DECIMAL(10,2) DEFAULT 0.00,
      `horas_laborables` DECIMAL(10,2) DEFAULT 0.00,
      `costo_hora_lab` DECIMAL(10,2) DEFAULT 0.00,
      `pvp_hora_lab` DECIMAL(10,2) DEFAULT 0.00,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "DROP TABLE IF EXISTS `cotizador_pool_servicios`;",
    "CREATE TABLE `cotizador_pool_servicios` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `codigo_unico` VARCHAR(50) NOT NULL UNIQUE,
      `marca_categoria` VARCHAR(100) NOT NULL,
      `actividad` VARCHAR(255) DEFAULT '',
      `detalle` TEXT,
      `n1` TINYINT(1) DEFAULT 0,
      `n2` TINYINT(1) DEFAULT 0,
      `n3` TINYINT(1) DEFAULT 0,
      `e1` TINYINT(1) DEFAULT 0,
      `e2` TINYINT(1) DEFAULT 0,
      `tipo` VARCHAR(50) DEFAULT '',
      `horas_laborables` DECIMAL(10,2) DEFAULT 0.00,
      `horas_no_laborables_50` DECIMAL(10,2) DEFAULT 0.00,
      `horas_no_laborables_100` DECIMAL(10,2) DEFAULT 0.00,
      `observaciones` TEXT,
      `activo` TINYINT(1) DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `cotizador_cotizaciones` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `parent_id` INT DEFAULT NULL,
      `version` INT DEFAULT 1,
      `cliente` VARCHAR(255) NOT NULL,
      `contrato` VARCHAR(255) DEFAULT '',
      `fecha` DATE NOT NULL,
      `estado` VARCHAR(50) DEFAULT 'Borrador',
      `aprobado_por` VARCHAR(100) DEFAULT NULL,
      `aprobado_fecha` DATETIME DEFAULT NULL,
      `margen_global` DECIMAL(5,2) DEFAULT 0.20,
      `risk_percentage` DECIMAL(5,2) DEFAULT 0.10,
      `total_costo` DECIMAL(12,2) DEFAULT 0.00,
      `total_precio` DECIMAL(12,2) DEFAULT 0.00,
      `adicionales_json` LONGTEXT DEFAULT NULL,
      `observaciones` TEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `cotizador_cotizaciones_detalles` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `cotizacion_id` INT NOT NULL,
      `seccion` VARCHAR(50) NOT NULL, -- 'Implementacion', 'Mant Prev', 'Mant Corr', 'Bolsa Horas'
      `codigo_unico` VARCHAR(50) DEFAULT NULL,
      `marca_categoria` VARCHAR(100) NOT NULL,
      `actividad` VARCHAR(255) DEFAULT '',
      `detalle` TEXT,
      `especialista_nivel` VARCHAR(10) NOT NULL,
      `horas_laborables` DECIMAL(10,2) DEFAULT 0.00,
      `horas_no_laborables_50` DECIMAL(10,2) DEFAULT 0.00,
      `horas_no_laborables_100` DECIMAL(10,2) DEFAULT 0.00,
      `costo_hora` DECIMAL(10,2) DEFAULT 0.00,
      `pvp_hora` DECIMAL(10,2) DEFAULT 0.00,
      `costo_total` DECIMAL(12,2) DEFAULT 0.00,
      `pvp_total` DECIMAL(12,2) DEFAULT 0.00,
      `multiplier_type` VARCHAR(50) DEFAULT 'Ninguno',
      `observaciones` TEXT,
      FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizador_cotizaciones`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `cotizador_pool_brands` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL UNIQUE,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `cotizador_specialist_levels` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `code` VARCHAR(50) NOT NULL UNIQUE,
      `min_salary` DECIMAL(10,2) NOT NULL,
      `max_salary` DECIMAL(10,2) NOT NULL,
      `base_type` VARCHAR(20) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `cotizador_equipment_categories` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(50) NOT NULL UNIQUE,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $q) {
    $pdo->exec($q);
}
echo "Tables created successfully.\n";

echo "2. Seeding default specialists...\n";
$stmt_check = $pdo->query("SELECT COUNT(*) FROM `cotizador_specialists`");
if ($stmt_check->fetchColumn() == 0) {
    // Insert initial list of specialists
    $specialists = [
        ['Carlos N1', 'N1', '1200 - 1400', 1200.00, 0.80, 0.00],
        ['Juan N3', 'N3', '2500 - 3500', 3200.00, 0.80, 0.00],
        ['Soporte N2', 'N2', '1500 - 2200', 2000.00, 0.80, 0.00],
        ['Consultor E1', 'E1', 'Tarifa fija', 0.00, 1.00, 70.00],
        ['Consultor E2', 'E2', 'Tarifa fija', 0.00, 1.00, 100.00],
        ['BOC Agent', 'BOC', '1000 - 1300', 900.00, 0.80, 0.00],
        ['PM Junior', 'GP1', '1500 - 1800', 1500.00, 0.80, 0.00],
        ['PM Senior', 'GP2', '2200 - 3000', 2200.00, 1.00, 0.00]
    ];
    
    $stmt_ins = $pdo->prepare("INSERT INTO `cotizador_specialists` (nombre, tipo, rango_salarial, salario, utilizable, costo_hora_manual, costo_empresa, horas_laborables, costo_hora_lab, pvp_hora_lab) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($specialists as $sp) {
        $nombre = $sp[0];
        $tipo = $sp[1];
        $rango_salarial = $sp[2];
        $salario = $sp[3];
        $utilizable = $sp[4];
        $costo_hora_manual = $sp[5];
        
        // Calculations
        if (in_array($tipo, ['N1', 'N2', 'N3', 'BOC'])) {
            $costo_empresa = $salario * 1.48;
            $horas_laborables = 21 * 8 * $utilizable;
            $costo_hora_lab = ($costo_empresa / $horas_laborables) / 0.95;
            $pvp_hora_lab = $costo_hora_lab / 0.80;
        } elseif (in_array($tipo, ['GP1', 'GP2'])) {
            $costo_empresa = $salario * 1.48;
            $horas_laborables = 20 * 8 * $utilizable;
            $costo_hora_lab = $costo_empresa / $horas_laborables;
            $pvp_hora_lab = $costo_hora_lab / 0.80;
        } else {
            // E1, E2
            $costo_empresa = 0;
            $horas_laborables = 0;
            $costo_hora_lab = $costo_hora_manual;
            $pvp_hora_lab = $costo_hora_lab / 0.80;
        }
        
        $stmt_ins->execute([
            $nombre, $tipo, $rango_salarial, $salario, $utilizable, $costo_hora_manual,
            $costo_empresa, $horas_laborables, $costo_hora_lab, $pvp_hora_lab
        ]);
    }
    echo "Specialists seeded.\n";
} else {
    echo "Specialists already exist.\n";
}

echo "2b. Seeding default specialist levels...\n";
$stmt_check_levels = $pdo->query("SELECT COUNT(*) FROM `cotizador_specialist_levels`");
if ($stmt_check_levels->fetchColumn() == 0) {
    $levels = [
        ['N1 Junior', 470.00, 600.00, 'N1'],
        ['N1 Intermedio', 600.00, 900.00, 'N1'],
        ['N1 Avanzado', 900.00, 1300.00, 'N1'],
        ['N2 Junior', 1300.00, 1500.00, 'N2'],
        ['N2 Intermedio', 1500.00, 1700.00, 'N2'],
        ['N2 Avanzado', 1700.00, 2000.00, 'N2'],
        ['N3 Junior', 2000.00, 2500.00, 'N3'],
        ['N3 Intermedio', 2500.00, 3000.00, 'N3'],
        ['N3 Avanzado', 3000.00, 3500.00, 'N3'],
        ['PM Junior (GP1)', 1500.00, 1800.00, 'GP1'],
        ['PM Senior (GP2)', 2200.00, 3000.00, 'GP2'],
        ['BOC Agent', 1000.00, 1300.00, 'BOC']
    ];
    $stmt_ins_lvl = $pdo->prepare("INSERT INTO `cotizador_specialist_levels` (code, min_salary, max_salary, base_type) VALUES (?, ?, ?, ?)");
    foreach ($levels as $l) {
        $stmt_ins_lvl->execute($l);
    }
    echo "Specialist levels seeded.\n";
} else {
    echo "Specialist levels already exist.\n";
}

echo "2c. Seeding default equipment categories...\n";
$stmt_check_eq = $pdo->query("SELECT COUNT(*) FROM `cotizador_equipment_categories`");
if ($stmt_check_eq->fetchColumn() == 0) {
    $eq_cats = ["Core", "Distribución", "Acceso", "WLC", "AP", "Blades", "Chasis UCS-X", "Fabric", "VMware", "Intersight"];
    $stmt_ins_eq = $pdo->prepare("INSERT INTO `cotizador_equipment_categories` (name) VALUES (?)");
    foreach ($eq_cats as $e) {
        $stmt_ins_eq->execute([$e]);
    }
    echo "Equipment categories seeded.\n";
} else {
    echo "Equipment categories already exist.\n";
}

echo "2d. Seeding default pool brands...\n";
$stmt_check_brands = $pdo->query("SELECT COUNT(*) FROM `cotizador_pool_brands`");
if ($stmt_check_brands->fetchColumn() == 0) {
    $brands = ["ARUBA", "AZURE", "CISCO", "DELL", "EQUIPO PIVOTE", "Firewalls", "FORTI", "HP", "HPE", "ROUTER", "SERVIDORES", "SITE SURVEY", "STORAGE", "SWITCHES", "VEEAM", "VMWARE"];
    $stmt_ins_brand = $pdo->prepare("INSERT INTO `cotizador_pool_brands` (name) VALUES (?)");
    foreach ($brands as $b) {
        $stmt_ins_brand->execute([$b]);
    }
    echo "Pool brands seeded.\n";
} else {
    echo "Pool brands already exist.\n";
}

echo "3. Parsing and seeding service pool from Excel...\n";
$stmt_check_pool = $pdo->query("SELECT COUNT(*) FROM `cotizador_pool_servicios`");
if ($stmt_check_pool->fetchColumn() == 0) {
    $excelPath = __DIR__ . '/../../cotizador/1 Actividades Completo - Copia.xlsx';
    if (!file_exists($excelPath)) {
        die("Excel file not found at: $excelPath\n");
    }
    
    $reader = IOFactory::createReaderForFile($excelPath);
    $spreadsheet = $reader->load($excelPath);
    
    $sheetNames = $spreadsheet->getSheetNames();
    $skippedSheets = ['Índice', 'Extras'];
    
    $stmt_ins_pool = $pdo->prepare("INSERT INTO `cotizador_pool_servicios` 
        (codigo_unico, marca_categoria, actividad, detalle, n1, n2, n3, e1, e2, tipo, horas_laborables, horas_no_laborables_50, horas_no_laborables_100, observaciones, activo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        
    $insertedCount = 0;
    $brandCounters = [];
    
    foreach ($sheetNames as $sName) {
        if (in_array($sName, $skippedSheets)) continue;
        
        $sheet = $spreadsheet->getSheetByName($sName);
        $highestRow = $sheet->getHighestRow();
        
        // Find header row and map columns dynamically
        $headerRow = null;
        $colMap = [
            'actividad' => null,
            'detalle' => null,
            'n1' => null,
            'n2' => null,
            'n3' => null,
            'e1' => null,
            'e2' => null,
            'tipo' => null,
            'h_lab' => null,
            'h_50' => null,
            'h_100' => null,
            'obs' => null
        ];
        
        // Scan first 10 rows for a header
        for ($r = 1; $r <= 10; $r++) {
            $hasDetalle = false;
            $rowVals = [];
            for ($c = 1; $c <= 20; $c++) {
                $val = trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue());
                $rowVals[$c] = $val;
                if (stripos($val, 'Detalle') !== false) {
                    $hasDetalle = true;
                }
            }
            if ($hasDetalle) {
                $headerRow = $r;
                foreach ($rowVals as $c => $val) {
                    $valLower = strtolower($val);
                    if (stripos($valLower, 'actividad') !== false) {
                        $colMap['actividad'] = $c;
                    } elseif (stripos($valLower, 'detalle') !== false) {
                        $colMap['detalle'] = $c;
                    } elseif ($valLower === 'n1') {
                        $colMap['n1'] = $c;
                    } elseif ($valLower === 'n2') {
                        $colMap['n2'] = $c;
                    } elseif ($valLower === 'n3') {
                        $colMap['n3'] = $c;
                    } elseif ($valLower === 'e1') {
                        $colMap['e1'] = $c;
                    } elseif ($valLower === 'e2') {
                        $colMap['e2'] = $c;
                    } elseif ($valLower === 'tipo') {
                        $colMap['tipo'] = $c;
                    } elseif (stripos($valLower, 'horas laborables') !== false || stripos($valLower, 'h. lab') !== false || stripos($valLower, 'horas lab') !== false) {
                        $colMap['h_lab'] = $c;
                    } elseif (stripos($valLower, '50%') !== false || stripos($valLower, 'no laborables 50') !== false) {
                        $colMap['h_50'] = $c;
                    } elseif (stripos($valLower, '100%') !== false || stripos($valLower, 'no laborables 100') !== false) {
                        $colMap['h_100'] = $c;
                    } elseif (stripos($valLower, 'observaciones') !== false) {
                        $colMap['obs'] = $c;
                    }
                }
                
                // Handle case where "Horas no Laborables" is present but without explicit 50%/100% in header
                if (!$colMap['h_50'] || !$colMap['h_100']) {
                    foreach ($rowVals as $c => $val) {
                        $valLower = strtolower($val);
                        if (stripos($valLower, 'no laborables') !== false || stripos($valLower, 'no lab') !== false) {
                            if (!$colMap['h_50']) {
                                $colMap['h_50'] = $c;
                            } elseif (!$colMap['h_100'] && $c !== $colMap['h_50']) {
                                $colMap['h_100'] = $c;
                            }
                        }
                    }
                }
                break;
            }
        }
        
        // If no header found, it's not a service sheet. Skip it.
        if ($headerRow === null) {
            continue;
        }
        
        $currentGroup = '';
        
        // Normalize brand prefix for code
        $brandPrefix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sName));
        if ($brandPrefix === '') {
            $brandPrefix = 'serv';
        }
        if (!isset($brandCounters[$brandPrefix])) {
            $brandCounters[$brandPrefix] = 0;
        }
        
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $n1 = ($colMap['n1'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n1'], $row)->getValue())) === 'x') ? 1 : 0;
            $n2 = ($colMap['n2'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n2'], $row)->getValue())) === 'x') ? 1 : 0;
            $n3 = ($colMap['n3'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n3'], $row)->getValue())) === 'x') ? 1 : 0;
            $e1 = ($colMap['e1'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['e1'], $row)->getValue())) === 'x') ? 1 : 0;
            $e2 = ($colMap['e2'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['e2'], $row)->getValue())) === 'x') ? 1 : 0;
            
            $tipo = $colMap['tipo'] ? trim((string)$sheet->getCellByColumnAndRow($colMap['tipo'], $row)->getValue()) : '';
            
            $h_lab = 0.0;
            $h_50 = 0.0;
            $h_100 = 0.0;
            
            if ($colMap['h_lab']) {
                try {
                    $h_lab = floatval($sheet->getCellByColumnAndRow($colMap['h_lab'], $row)->getCalculatedValue());
                } catch (Exception $ex) {
                    $h_lab = floatval($sheet->getCellByColumnAndRow($colMap['h_lab'], $row)->getValue());
                }
            }
            if ($colMap['h_50']) {
                try {
                    $h_50 = floatval($sheet->getCellByColumnAndRow($colMap['h_50'], $row)->getCalculatedValue());
                } catch (Exception $ex) {
                    $h_50 = floatval($sheet->getCellByColumnAndRow($colMap['h_50'], $row)->getValue());
                }
            }
            if ($colMap['h_100']) {
                try {
                    $h_100 = floatval($sheet->getCellByColumnAndRow($colMap['h_100'], $row)->getCalculatedValue());
                } catch (Exception $ex) {
                    $h_100 = floatval($sheet->getCellByColumnAndRow($colMap['h_100'], $row)->getValue());
                }
            }
            
            $obs = $colMap['obs'] ? trim((string)$sheet->getCellByColumnAndRow($colMap['obs'], $row)->getValue()) : '';
            
            // Determine values based on column mappings
            $groupVal = '';
            $detailVal = '';
            
            if ($colMap['actividad']) {
                $groupVal = trim((string)$sheet->getCellByColumnAndRow($colMap['actividad'], $row)->getValue());
            }
            if ($colMap['detalle']) {
                $detailVal = trim((string)$sheet->getCellByColumnAndRow($colMap['detalle'], $row)->getValue());
            }
            
            $hasCheckboxesOrHours = ($n1 || $n2 || $n3 || $e1 || $e2 || $h_lab > 0 || $h_50 > 0 || $h_100 > 0);
            
            if ($colMap['actividad'] !== null) {
                if ($groupVal === '' && $detailVal === '') {
                    continue;
                }
                if ($groupVal !== '') {
                    $currentGroup = $groupVal;
                }
                $detail = $detailVal;
                if ($detail === '') {
                    $detail = $currentGroup;
                }
            } else {
                // JCV001-style sheet: only Detalle column
                $val = $detailVal !== '' ? $detailVal : $groupVal;
                if ($val === '') {
                    continue;
                }
                if ($hasCheckboxesOrHours) {
                    $detail = $val;
                } else {
                    $currentGroup = $val;
                    continue; // Skip inserting the group header row itself as a leaf item
                }
            }
            
            // Generate unique code
            $brandCounters[$brandPrefix]++;
            $codigo_unico = $brandPrefix . '_' . str_pad($brandCounters[$brandPrefix], 3, '0', STR_PAD_LEFT);
            
            $stmt_ins_pool->execute([
                $codigo_unico, $sName, $currentGroup, $detail, $n1, $n2, $n3, $e1, $e2, $tipo, $h_lab, $h_50, $h_100, $obs
            ]);
            $insertedCount++;
        }
        echo "Parsed sheet $sName: loaded rows.\n";
    }
    echo "Finished seeding pool. Total services loaded: $insertedCount\n";
} else {
    echo "Service pool already seeded.\n";
}

echo "4. Adding Cotizador to user_module_permissions if missing...\n";
// Check if module 'cotizador' exists in module list
$stmt_perm = $pdo->prepare("INSERT INTO `user_module_permissions` (user_id, module_name, can_view) 
    SELECT id, 'cotizador', 1 FROM users WHERE id NOT IN (SELECT user_id FROM `user_module_permissions` WHERE module_name = 'cotizador')");
$stmt_perm->execute();

echo "Installation complete!\n";
