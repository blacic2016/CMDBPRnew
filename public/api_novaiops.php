<?php
/**
 * API Backend for NovaIOPS Dashboard - CMDB VILASECA
 */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Only run the request handler if this script is accessed directly.
if (basename($_SERVER['SCRIPT_NAME']) === 'api_novaiops.php') {
    // Ensure user is logged in
    require_login();

    // Check if user has permission
    if (!has_role('SUPER_ADMIN') && !has_module_access('novaiops_dashboard')) {
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para acceder al módulo NovaIOPS.']);
        exit();
    }

    $pdo = getPDO();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'No se pudo establecer conexión con la base de datos.']);
        exit();
    }

    // Auto-initialize metadata and history tables if they do not exist
    init_novaiops_system_tables($pdo);

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    try {
        switch ($action) {
            case 'get_history':
                $stmt = $pdo->query("SELECT h.*, u.username as uploader_name 
                                      FROM novaiops_upload_history h
                                      LEFT JOIN users u ON h.uploaded_by = u.id
                                      ORDER BY h.uploaded_at DESC");
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'history' => $history]);
                break;

            case 'get_data':
                // Check if tables exist
                $tablesExist = check_tables_exist($pdo);
                if (!$tablesExist) {
                    echo json_encode([
                        'success' => true, 
                        'initialized' => false, 
                        'message' => 'La base de datos está vacía. Por favor suba un archivo Excel para inicializar los datos.',
                        'tareas' => [],
                        'seguimientos' => [],
                        'informacion' => [],
                        'columns' => []
                    ]);
                    exit();
                }

                // Fetch metadata columns to preserve original labels and ordering
                $meta_columns = [];
                $stmt = $pdo->query("SELECT * FROM novaiops_meta_columns ORDER BY table_name, display_order ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $meta_columns[$row['table_name']][] = [
                        'column_name' => $row['column_name'],
                        'original_label' => $row['original_label']
                    ];
                }

                // Fetch data
                $tareas = $pdo->query("SELECT * FROM novaiops_reporte_tareas ORDER BY id_internal DESC")->fetchAll(PDO::FETCH_ASSOC);
                $seguimientos = $pdo->query("SELECT * FROM novaiops_seguimientos ORDER BY id_internal DESC")->fetchAll(PDO::FETCH_ASSOC);
                $informacion = $pdo->query("SELECT * FROM novaiops_informacion_reportes ORDER BY id_internal DESC")->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'initialized' => true,
                    'columns' => $meta_columns,
                    'tareas' => $tareas,
                    'seguimientos' => $seguimientos,
                    'informacion' => $informacion
                ]);
                break;

            case 'check_stage':
                echo json_encode([
                    'success' => true,
                    'has_stage' => isset($_SESSION['novaiops_stage_file']) && is_file($_SESSION['novaiops_stage_file']),
                    'filename' => $_SESSION['novaiops_stage_filename'] ?? ''
                ]);
                exit();

            case 'upload_temp':
                if (!isset($_FILES['file'])) {
                    echo json_encode(['success' => false, 'error' => 'No se subió ningún archivo.']);
                    exit();
                }

                $file = $_FILES['file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'error' => 'Error al subir el archivo (Código ' . $file['error'] . ')']);
                    exit();
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['xlsx', 'xls'])) {
                    echo json_encode(['success' => false, 'error' => 'Solo se admiten archivos Excel (.xlsx, .xls)']);
                    exit();
                }

                // Create upload folder if not exists
                $uploadDir = __DIR__ . '/../public/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Clean up previous staging file if it exists in session
                if (isset($_SESSION['novaiops_stage_file']) && is_file($_SESSION['novaiops_stage_file'])) {
                    @unlink($_SESSION['novaiops_stage_file']);
                }

                $destination = $uploadDir . '/stage_' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo subido en el servidor.']);
                    exit();
                }

                $_SESSION['novaiops_stage_file'] = $destination;
                $_SESSION['novaiops_stage_filename'] = $file['name'];
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Archivo cargado temporalmente. Listo para análisis.', 
                    'filename' => $file['name']
                ]);
                break;

            case 'import_default_temp':
                // Look for the Excel file in cotizador/novaiops
                $dir = __DIR__ . '/../cotizador/novaiops';
                $files = glob($dir . '/reporte_tareas*.xlsx');
                if (empty($files)) {
                    echo json_encode(['success' => false, 'error' => 'No se encontró ningún archivo reporte_tareas en ' . $dir]);
                    exit();
                }
                
                $filePath = $files[0];
                $filename = basename($filePath);
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                // Create upload folder if not exists
                $uploadDir = __DIR__ . '/../public/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Clean up previous staging file
                if (isset($_SESSION['novaiops_stage_file']) && is_file($_SESSION['novaiops_stage_file'])) {
                    @unlink($_SESSION['novaiops_stage_file']);
                }

                $destination = $uploadDir . '/stage_default_' . uniqid() . '.' . $ext;
                if (!copy($filePath, $destination)) {
                    echo json_encode(['success' => false, 'error' => 'No se pudo precargar el archivo predeterminado.']);
                    exit();
                }

                $_SESSION['novaiops_stage_file'] = $destination;
                $_SESSION['novaiops_stage_filename'] = $filename;

                echo json_encode([
                    'success' => true,
                    'message' => 'Archivo predeterminado cargado en staging. Listo para análisis.',
                    'filename' => $filename
                ]);
                break;

            case 'analyze_stage':
                if (!isset($_SESSION['novaiops_stage_file']) || !is_file($_SESSION['novaiops_stage_file'])) {
                    echo json_encode(['success' => false, 'error' => 'No hay ningún archivo cargado para analizar.']);
                    exit();
                }

                $analysis = analyze_excel_file($pdo, $_SESSION['novaiops_stage_file']);
                echo json_encode([
                    'success' => true,
                    'filename' => $_SESSION['novaiops_stage_filename'] ?? 'Archivo',
                    'analysis' => $analysis
                ]);
                break;

            case 'confirm_stage':
                if (!isset($_SESSION['novaiops_stage_file']) || !is_file($_SESSION['novaiops_stage_file'])) {
                    echo json_encode(['success' => false, 'error' => 'No hay ningún archivo cargado para confirmar.']);
                    exit();
                }

                $filePath = $_SESSION['novaiops_stage_file'];
                $filename = $_SESSION['novaiops_stage_filename'] ?? 'Reporte';
                
                $result = process_excel_file($pdo, $filePath, $filename, current_user_id());
                
                // Clean up temp file
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
                unset($_SESSION['novaiops_stage_file'], $_SESSION['novaiops_stage_filename']);

                echo json_encode($result);
                break;

            case 'cancel_stage':
                if (isset($_SESSION['novaiops_stage_file']) && is_file($_SESSION['novaiops_stage_file'])) {
                    @unlink($_SESSION['novaiops_stage_file']);
                }
                unset($_SESSION['novaiops_stage_file'], $_SESSION['novaiops_stage_filename']);
                echo json_encode(['success' => true, 'message' => 'Carga cancelada con éxito.']);
                break;

            case 'import_default':
                // Legacy support if needed
                $dir = __DIR__ . '/../cotizador/novaiops';
                $files = glob($dir . '/reporte_tareas*.xlsx');
                if (empty($files)) {
                    echo json_encode(['success' => false, 'error' => 'No se encontró ningún archivo reporte_tareas en ' . $dir]);
                    exit();
                }
                $filePath = $files[0];
                $filename = basename($filePath);
                $result = process_excel_file($pdo, $filePath, $filename, current_user_id());
                echo json_encode($result);
                break;

            case 'upload':
                // Legacy support if needed
                if (!isset($_FILES['file'])) {
                    echo json_encode(['success' => false, 'error' => 'No se subió ningún archivo.']);
                    exit();
                }
                $file = $_FILES['file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'error' => 'Error al subir el archivo (Código ' . $file['error'] . ')']);
                    exit();
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['xlsx', 'xls'])) {
                    echo json_encode(['success' => false, 'error' => 'Solo se admiten archivos Excel (.xlsx, .xls)']);
                    exit();
                }
                $uploadDir = __DIR__ . '/../public/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $destination = $uploadDir . '/' . uniqid() . '_' . basename($file['name']);
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo subido en el servidor.']);
                    exit();
                }
                $result = process_excel_file($pdo, $destination, $file['name'], current_user_id());
                echo json_encode($result);
                break;

            case 'reset_database':
                // Destructive action: drop the tables to allow fresh reload
                $pdo->exec("DROP TABLE IF EXISTS novaiops_reporte_tareas");
                $pdo->exec("DROP TABLE IF EXISTS novaiops_seguimientos");
                $pdo->exec("DROP TABLE IF EXISTS novaiops_informacion_reportes");
                $pdo->exec("DELETE FROM novaiops_meta_columns");
                $pdo->exec("DELETE FROM novaiops_upload_history");
                echo json_encode(['success' => true, 'message' => 'Base de datos de NovaIOPS reiniciada con éxito.']);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
}


/**
 * Initialize system tables
 */
function init_novaiops_system_tables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `novaiops_upload_history` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `filename` VARCHAR(255) NOT NULL,
      `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `uploaded_by` INT,
      `inserted_tareas` INT DEFAULT 0,
      `inserted_seguimientos` INT DEFAULT 0,
      `inserted_informacion` INT DEFAULT 0,
      `repeated_tareas` INT DEFAULT 0,
      `repeated_seguimientos` INT DEFAULT 0,
      `repeated_informacion` INT DEFAULT 0,
      `status` VARCHAR(50) DEFAULT 'success',
      `error_msg` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add repeated count columns dynamically if they are missing
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM novaiops_upload_history LIKE 'repeated_tareas'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE novaiops_upload_history ADD COLUMN repeated_tareas INT DEFAULT 0 AFTER inserted_tareas");
            $pdo->exec("ALTER TABLE novaiops_upload_history ADD COLUMN repeated_seguimientos INT DEFAULT 0 AFTER inserted_seguimientos");
            $pdo->exec("ALTER TABLE novaiops_upload_history ADD COLUMN repeated_informacion INT DEFAULT 0 AFTER inserted_informacion");
        }
    } catch (Exception $e) {
        // Safe to ignore or log
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `novaiops_meta_columns` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `table_name` VARCHAR(100) NOT NULL,
      `column_name` VARCHAR(100) NOT NULL,
      `original_label` VARCHAR(255) NOT NULL,
      `display_order` INT NOT NULL,
      UNIQUE KEY `idx_table_col` (`table_name`, `column_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

/**
 * Check if data tables exist
 */
function check_tables_exist($pdo) {
    try {
        $stmt1 = $pdo->query("SHOW TABLES LIKE 'novaiops_reporte_tareas'");
        $stmt2 = $pdo->query("SHOW TABLES LIKE 'novaiops_seguimientos'");
        $stmt3 = $pdo->query("SHOW TABLES LIKE 'novaiops_informacion_reportes'");
        return ($stmt1->rowCount() > 0 && $stmt2->rowCount() > 0 && $stmt3->rowCount() > 0);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sanitize column names for SQL
 */
function sanitize_column_name($name) {
    $name = mb_strtolower($name, 'UTF-8');
    // Replace accents
    $name = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', '°'],
        ['a', 'e', 'i', 'o', 'u', 'n', 'u', ''],
        $name
    );
    // Replace non-alphanumeric with underscore
    $name = preg_replace('/[^a-z0-9_]/u', '_', $name);
    $name = trim($name, '_');
    $name = preg_replace('/_+/', '_', $name);
    // If starts with number, prepend col_
    if (preg_match('/^[0-9]/', $name)) {
        $name = 'col_' . $name;
    }
    return empty($name) ? 'col_empty' : $name;
}

/**
 * Analyze staged Excel file and return column counts / validation status
 */
function analyze_excel_file($pdo, $filePath) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $sheetNames = $spreadsheet->getSheetNames();
    
    $sheetTareasName = null;
    $sheetSeguimientosName = null;
    $sheetInformacionName = null;
    
    foreach ($sheetNames as $name) {
        $norm = mb_strtolower(trim($name), 'UTF-8');
        if ($norm === 'reporte tareas') {
            $sheetTareasName = $name;
        } elseif ($norm === 'seguimientos') {
            $sheetSeguimientosName = $name;
        } elseif ($norm === 'informacion reportes' || $norm === 'información reportes') {
            $sheetInformacionName = $name;
        }
    }
    
    if (!$sheetTareasName || !$sheetSeguimientosName || !$sheetInformacionName) {
        throw new Exception('El archivo Excel debe contener las pestañas "Reporte tareas", "Seguimientos" e "Información reportes". Hojas encontradas: ' . implode(', ', $sheetNames));
    }
    
    $sheetConfigs = [
        'Reporte tareas' => [
            'sheet_name' => $sheetTareasName,
            'table_name' => 'novaiops_reporte_tareas',
            'key_col' => 'id_tarea'
        ],
        'Seguimientos' => [
            'sheet_name' => $sheetSeguimientosName,
            'table_name' => 'novaiops_seguimientos',
            'key_col' => null
        ],
        'Información reportes' => [
            'sheet_name' => $sheetInformacionName,
            'table_name' => 'novaiops_informacion_reportes',
            'key_col' => null
        ]
    ];
    
    $analysisResult = [];
    
    foreach ($sheetConfigs as $label => $cfg) {
        $sheet = $spreadsheet->getSheetByName($cfg['sheet_name']);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $colLimit = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Read Headers
        $headers = [];
        for ($col = 1; $col <= $colLimit; $col++) {
            $val = trim($sheet->getCellByColumnAndRow($col, 1)->getValue() ?? '');
            if ($val !== '') {
                $headers[$col] = $val;
            }
        }
        
        if (empty($headers)) {
            throw new Exception("La hoja '{$cfg['sheet_name']}' está vacía o no tiene cabeceras.");
        }
        
        $sanitizedHeaders = [];
        foreach ($headers as $col => $name) {
            $sanitizedHeaders[$col] = sanitize_column_name($name);
        }
        
        // Check if table exists
        $tableExists = false;
        $checkTable = $pdo->prepare("SHOW TABLES LIKE ?");
        $checkTable->execute([$cfg['table_name']]);
        if ($checkTable->rowCount() > 0) {
            $tableExists = true;
        }
        
        // Validate columns if table exists
        if ($tableExists) {
            $stmt = $pdo->prepare("SELECT column_name, original_label FROM novaiops_meta_columns WHERE table_name = ? ORDER BY display_order ASC");
            $stmt->execute([$cfg['table_name']]);
            $dbCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dbColNames = array_column($dbCols, 'column_name');
            $excelColNames = array_values($sanitizedHeaders);
            
            if (count($dbColNames) !== count($excelColNames)) {
                throw new Exception("Discrepancia en la hoja '{$cfg['sheet_name']}': El número de columnas no coincide. Esperadas: " . count($dbColNames) . " (" . implode(', ', array_column($dbCols, 'original_label')) . "), Encontradas: " . count($excelColNames) . " (" . implode(', ', array_values($headers)) . ").");
            }
            
            for ($i = 0; $i < count($excelColNames); $i++) {
                if ($dbColNames[$i] !== $excelColNames[$i]) {
                    throw new Exception("Discrepancia en la hoja '{$cfg['sheet_name']}': Las columnas no coinciden. Columna esperada en posición " . ($i + 1) . ": '{$dbCols[$i]['original_label']}' ({$dbColNames[$i]}), Columna encontrada: '{$headers[array_keys($headers)[$i]]}' ({$excelColNames[$i]}).");
                }
            }
        }
        
        // Load existing keys / hashes for counting new vs repeated
        $existingKeys = [];
        $existingHashes = [];
        
        if ($tableExists) {
            if ($cfg['key_col'] !== null) {
                $stmt = $pdo->query("SELECT `{$cfg['key_col']}` FROM `{$cfg['table_name']}` WHERE `{$cfg['key_col']}` IS NOT NULL");
                while ($val = $stmt->fetchColumn()) {
                    $existingKeys[trim($val)] = true;
                }
            } else {
                $dbRows = $pdo->query("SELECT * FROM `{$cfg['table_name']}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($dbRows as $dbRow) {
                    unset($dbRow['id_internal'], $dbRow['upload_id']);
                    $hashStr = implode('|', array_map('trim', $dbRow));
                    $existingHashes[md5($hashStr)] = true;
                }
            }
        }
        
        // Count new vs repeated
        $newCount = 0;
        $repeatedCount = 0;
        
        $parsedKeysThisSheet = [];
        $parsedHashesThisSheet = [];
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowValues = [];
            $rowIsEmpty = true;
            
            foreach ($headers as $col => $name) {
                try {
                    $val = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
                } catch (Exception $ex) {
                    $val = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                }
                if ($val !== null) {
                    $val = (string)$val;
                    if (trim($val) !== '') {
                        $rowIsEmpty = false;
                    }
                } else {
                    $val = '';
                }
                $rowValues[$sanitizedHeaders[$col]] = $val;
            }
            
            if ($rowIsEmpty) {
                continue;
            }
            
            if ($cfg['key_col'] !== null) {
                $keyVal = trim($rowValues[$cfg['key_col']] ?? '');
                if ($keyVal !== '') {
                    if (isset($existingKeys[$keyVal]) || isset($parsedKeysThisSheet[$keyVal])) {
                        $repeatedCount++;
                    } else {
                        $newCount++;
                        $parsedKeysThisSheet[$keyVal] = true;
                    }
                }
            } else {
                $hashStr = implode('|', array_map('trim', $rowValues));
                $rowHash = md5($hashStr);
                if (isset($existingHashes[$rowHash]) || isset($parsedHashesThisSheet[$rowHash])) {
                    $repeatedCount++;
                } else {
                    $newCount++;
                    $parsedHashesThisSheet[$rowHash] = true;
                }
            }
        }
        
        $analysisResult[$label] = [
            'total' => $newCount + $repeatedCount,
            'new' => $newCount,
            'repeated' => $repeatedCount
        ];
    }
    
    return $analysisResult;
}

/**
 * Process Excel file and insert new rows, returning result
 */
function process_excel_file($pdo, $filePath, $originalFilename, $userId) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $sheetNames = $spreadsheet->getSheetNames();
    
    $sheetTareasName = null;
    $sheetSeguimientosName = null;
    $sheetInformacionName = null;
    
    foreach ($sheetNames as $name) {
        $norm = mb_strtolower(trim($name), 'UTF-8');
        if ($norm === 'reporte tareas') {
            $sheetTareasName = $name;
        } elseif ($norm === 'seguimientos') {
            $sheetSeguimientosName = $name;
        } elseif ($norm === 'informacion reportes' || $norm === 'información reportes') {
            $sheetInformacionName = $name;
        }
    }
    
    if (!$sheetTareasName || !$sheetSeguimientosName || !$sheetInformacionName) {
        return [
            'success' => false, 
            'error' => 'El archivo Excel debe contener las pestañas "Reporte tareas", "Seguimientos" e "Información reportes". Hojas encontradas: ' . implode(', ', $sheetNames)
        ];
    }
    
    $sheetConfigs = [
        'Reporte tareas' => [
            'sheet_name' => $sheetTareasName,
            'table_name' => 'novaiops_reporte_tareas',
            'key_col' => 'id_tarea',
            'original_key_label' => 'ID tarea'
        ],
        'Seguimientos' => [
            'sheet_name' => $sheetSeguimientosName,
            'table_name' => 'novaiops_seguimientos',
            'key_col' => null
        ],
        'Información reportes' => [
            'sheet_name' => $sheetInformacionName,
            'table_name' => 'novaiops_informacion_reportes',
            'key_col' => null
        ]
    ];
    
    $insertedCounts = [];
    $repeatedCounts = [];
    
    // Step 1: Pre-validate and create tables if they do not exist (this must happen outside transactions to avoid implicit commit in MySQL)
    try {
        foreach ($sheetConfigs as $label => $cfg) {
            $sheet = $spreadsheet->getSheetByName($cfg['sheet_name']);
            $highestColumn = $sheet->getHighestColumn();
            $colLimit = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            // Read Headers
            $headers = [];
            for ($col = 1; $col <= $colLimit; $col++) {
                $val = trim($sheet->getCellByColumnAndRow($col, 1)->getValue() ?? '');
                if ($val !== '') {
                    $headers[$col] = $val;
                }
            }
            
            if (empty($headers)) {
                throw new Exception("La hoja '{$cfg['sheet_name']}' está vacía o no tiene cabeceras.");
            }
            
            $sanitizedHeaders = [];
            foreach ($headers as $col => $name) {
                $sanitizedHeaders[$col] = sanitize_column_name($name);
            }
            
            // Check if table exists
            $tableExists = false;
            $checkTable = $pdo->prepare("SHOW TABLES LIKE ?");
            $checkTable->execute([$cfg['table_name']]);
            if ($checkTable->rowCount() > 0) {
                $tableExists = true;
            }
            
            // Validate columns if table exists
            if ($tableExists) {
                $stmt = $pdo->prepare("SELECT column_name, original_label FROM novaiops_meta_columns WHERE table_name = ? ORDER BY display_order ASC");
                $stmt->execute([$cfg['table_name']]);
                $dbCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $dbColNames = array_column($dbCols, 'column_name');
                $excelColNames = array_values($sanitizedHeaders);
                
                if (count($dbColNames) !== count($excelColNames)) {
                    throw new Exception("Discrepancia en la hoja '{$cfg['sheet_name']}': El número de columnas no coincide. Esperadas: " . count($dbColNames) . ", Encontradas: " . count($excelColNames));
                }
                
                for ($i = 0; $i < count($excelColNames); $i++) {
                    if ($dbColNames[$i] !== $excelColNames[$i]) {
                        throw new Exception("Discrepancia en la hoja '{$cfg['sheet_name']}': Las columnas no coinciden. Esperada: '{$dbCols[$i]['original_label']}', Encontrada: '{$headers[array_keys($headers)[$i]]}'.");
                    }
                }
            } else {
                // Create table
                $sql = "CREATE TABLE `{$cfg['table_name']}` (\n";
                $sql .= "  `id_internal` INT AUTO_INCREMENT PRIMARY KEY,\n";
                foreach ($sanitizedHeaders as $col => $colName) {
                    $sql .= "  `{$colName}` TEXT,\n";
                }
                $sql .= "  `upload_id` INT DEFAULT 0\n";
                $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                
                $pdo->exec($sql);
                
                // Insert metadata
                $insertMeta = $pdo->prepare("INSERT INTO novaiops_meta_columns (table_name, column_name, original_label, display_order) VALUES (?, ?, ?, ?)");
                $order = 1;
                foreach ($headers as $col => $name) {
                    $insertMeta->execute([$cfg['table_name'], $sanitizedHeaders[$col], $name, $order++]);
                }
            }
        }
    } catch (Exception $e) {
        // Log failed upload in history
        try {
            $stmtHist = $pdo->prepare("INSERT INTO novaiops_upload_history (filename, uploaded_by, status, error_msg) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([
                $originalFilename,
                $userId,
                'failed',
                $e->getMessage()
            ]);
        } catch (Exception $ex) {}
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Step 2: Now start transaction and insert data
    $pdo->beginTransaction();
    
    try {
        foreach ($sheetConfigs as $label => $cfg) {
            $sheet = $spreadsheet->getSheetByName($cfg['sheet_name']);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $colLimit = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            // Read Headers
            $headers = [];
            for ($col = 1; $col <= $colLimit; $col++) {
                $val = trim($sheet->getCellByColumnAndRow($col, 1)->getValue() ?? '');
                if ($val !== '') {
                    $headers[$col] = $val;
                }
            }
            
            $sanitizedHeaders = [];
            foreach ($headers as $col => $name) {
                $sanitizedHeaders[$col] = sanitize_column_name($name);
            }
            
            // Load existing data for deduplication
            $existingKeys = [];
            $existingHashes = [];
            
            $stmt = $pdo->query("SELECT `column_name` FROM novaiops_meta_columns WHERE table_name = '{$cfg['table_name']}'");
            $metaColsExists = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($cfg['key_col'] !== null && in_array($cfg['key_col'], $metaColsExists)) {
                $stmtKey = $pdo->query("SELECT `{$cfg['key_col']}` FROM `{$cfg['table_name']}` WHERE `{$cfg['key_col']}` IS NOT NULL");
                while ($val = $stmtKey->fetchColumn()) {
                    $existingKeys[trim($val)] = true;
                }
            } else {
                $dbRows = $pdo->query("SELECT * FROM `{$cfg['table_name']}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($dbRows as $dbRow) {
                    unset($dbRow['id_internal'], $dbRow['upload_id']);
                    $hashStr = implode('|', array_map('trim', $dbRow));
                    $existingHashes[md5($hashStr)] = true;
                }
            }
            
            // Insert new rows
            $insertSql = "INSERT INTO `{$cfg['table_name']}` (" . implode(', ', array_values($sanitizedHeaders)) . ", upload_id) VALUES (" . implode(', ', array_fill(0, count($sanitizedHeaders), '?')) . ", ?)";
            $insertStmt = $pdo->prepare($insertSql);
            
            $inserted = 0;
            $repeated = 0;
            
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowValues = [];
                $rowIsEmpty = true;
                
                foreach ($headers as $col => $name) {
                    try {
                        $val = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
                    } catch (Exception $ex) {
                        $val = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                    }
                    
                    if ($val !== null) {
                        $val = (string)$val;
                        if (trim($val) !== '') {
                            $rowIsEmpty = false;
                        }
                    } else {
                        $val = '';
                    }
                    $rowValues[$sanitizedHeaders[$col]] = $val;
                }
                
                if ($rowIsEmpty) {
                    continue;
                }
                
                // Deduplicate check
                if ($cfg['key_col'] !== null && in_array($cfg['key_col'], $metaColsExists)) {
                    $keyVal = trim($rowValues[$cfg['key_col']] ?? '');
                    if ($keyVal !== '' && isset($existingKeys[$keyVal])) {
                        $repeated++;
                        continue; // Skip already existing task
                    }
                    if ($keyVal !== '') {
                        $existingKeys[$keyVal] = true;
                    }
                } else {
                    $hashStr = implode('|', array_map('trim', $rowValues));
                    $rowHash = md5($hashStr);
                    if (isset($existingHashes[$rowHash])) {
                        $repeated++;
                        continue; // Skip duplicate row
                    }
                    $existingHashes[$rowHash] = true;
                }
                
                $params = array_values($rowValues);
                $params[] = 0; // Temp upload ID
                
                $insertStmt->execute($params);
                $inserted++;
            }
            
            $insertedCounts[$label] = $inserted;
            $repeatedCounts[$label] = $repeated;
        }
        
        // Insert history record
        $stmtHist = $pdo->prepare("INSERT INTO novaiops_upload_history (filename, uploaded_by, inserted_tareas, inserted_seguimientos, inserted_informacion, repeated_tareas, repeated_seguimientos, repeated_informacion, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtHist->execute([
            $originalFilename,
            $userId,
            $insertedCounts['Reporte tareas'] ?? 0,
            $insertedCounts['Seguimientos'] ?? 0,
            $insertedCounts['Información reportes'] ?? 0,
            $repeatedCounts['Reporte tareas'] ?? 0,
            $repeatedCounts['Seguimientos'] ?? 0,
            $repeatedCounts['Información reportes'] ?? 0,
            'success'
        ]);
        $uploadId = $pdo->lastInsertId();
        
        // Link the inserted rows to this upload ID
        foreach ($sheetConfigs as $label => $cfg) {
            $updateUploadId = $pdo->prepare("UPDATE `{$cfg['table_name']}` SET upload_id = ? WHERE upload_id = 0");
            $updateUploadId->execute([$uploadId]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Archivo procesado e importado con éxito.',
            'tareas_nuevas' => $insertedCounts['Reporte tareas'],
            'seguimientos_nuevos' => $insertedCounts['Seguimientos'],
            'informacion_nuevos' => $insertedCounts['Información reportes'],
            'tareas_repetidas' => $repeatedCounts['Reporte tareas'],
            'seguimientos_repetidos' => $repeatedCounts['Seguimientos'],
            'informacion_repetidas' => $repeatedCounts['Información reportes'],
            'upload_id' => $uploadId
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log failed upload in history
        try {
            $stmtHist = $pdo->prepare("INSERT INTO novaiops_upload_history (filename, uploaded_by, status, error_msg) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([
                $originalFilename,
                $userId,
                'failed',
                $e->getMessage()
            ]);
        } catch (Exception $ex) {}
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
