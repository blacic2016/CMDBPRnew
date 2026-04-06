<?php
/**
 * CMDB VILASECA - Motor de Importación Excel (Versión Completa 283+ líneas)
 * Ubicación: /var/www/html/Sonda/src/importer.php
 */

// 1. Reporte total de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Límites de recursos máximos para procesar las 9 hojas del archivo EQUIPOS.xlsx
ini_set('memory_limit', '1024M');
set_time_limit(900);

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Seguro: Evita el Fatal Error de re-declaración si ya existe en helpers.php
 */
if (!function_exists('getNextAssetCode')) {
    function getNextAssetCode() {
        return 'AST-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

class Importer
{
    /**
     * Motor principal: Procesa múltiples hojas, crea/reemplaza tablas y gestiona auditoría visual.
     */
    public static function importExcelFile(string $filePath, string $mode = 'add')
    {
        $pdo = getPDO();
        
        // Cargamos el Reader configurado para leer solo datos y evitar bloqueos por fórmulas rotas
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true); 
        $spreadsheet = $reader->load($filePath);
        $summary = [];

        $sheetNames = $spreadsheet->getSheetNames();
        $processedTables = [];

        // --- PROCESAMIENTO DE TODAS LAS HOJAS ---
        foreach ($sheetNames as $index => $sheetName) {
            $sheet = $spreadsheet->getSheet($index);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Convertimos la hoja a array para procesar cabeceras y datos
            $rows = $sheet->toArray(null, true, true, true);
            if (count($rows) < 1) continue;

            // --- LÓGICA DE SANITIZACIÓN DE CABECERAS ---
            $headers = array_map(function($h){ return $h === null ? '' : trim((string)$h); }, $rows[1]);
            $cols = []; $seen = [];
            foreach ($headers as $h) {
                if ($h === '') continue; // Ignorar columnas vacías
                $base = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $h));
                $base = preg_replace('/_+/', '_', $base);
                $base = trim($base, '_');
                if ($base === '') $base = 'col';
                if ($base === 'id' || $base === 'excel_id') $base = 'item_id'; 
                
                $colName = $base; $i = 1;
                while (in_array($colName, $seen, true) || in_array($colName, ['id', '_row_hash', 'estado_actual', 'asset_code', 'created_at', 'updated_at'])) {
                    $colName = $base . '_' . $i;
                    $i++;
                }
                $seen[] = $colName; $cols[] = $colName;
            }

            $tableName = 'sheet_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($sheetName));
            $tableName = preg_replace('/_+/', '_', $tableName);
            $processedTables[] = $tableName;

            // --- REEMPLAZO O CREACIÓN DE TABLA ---
            if ($mode === 'replace') {
                $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`");
            }

            $createSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                _row_hash VARCHAR(64) NOT NULL,
                estado_actual ENUM('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
                asset_code VARCHAR(50) NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($createSql);

            // Sincronización de columnas dinámicas
            $col_check_stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tableName}`");
            $col_check_stmt->execute();
            $all_cols_in_db = $col_check_stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($cols as $col) {
                if (in_array($col, $all_cols_in_db)) continue;
                try {
                    $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$col}` TEXT NULL");
                } catch (Exception $e) { }
            }

            // --- AUTO-REGISTRO EN SHEET_CONFIGS ---
            try {
                $stmtCfg = $pdo->prepare("SELECT COUNT(*) FROM sheet_configs WHERE table_name = ?");
                $stmtCfg->execute([$tableName]);
                if ($stmtCfg->fetchColumn() == 0) {
                    $insCfg = $pdo->prepare("INSERT INTO sheet_configs (sheet_name, table_name) VALUES (?, ?)");
                    $insCfg->execute([$sheetName, $tableName]);
                } else {
                    // Actualizar nombre de la hoja si cambió pero la tabla es la misma
                    $updCfg = $pdo->prepare("UPDATE sheet_configs SET sheet_name = ? WHERE table_name = ?");
                    $updCfg->execute([$sheetName, $tableName]);
                }
            } catch (Exception $e) { }

            // --- CARGA DE CONFIGURACIÓN DE COLUMNAS ÚNICAS ---
            $uniqueCols = [];
            try {
                $stmtUc = $pdo->prepare("SELECT unique_columns FROM sheet_configs WHERE table_name = :t LIMIT 1");
                $stmtUc->execute([':t' => $tableName]);
                $ucJson = $stmtUc->fetchColumn();
                if ($ucJson) {
                    $ucArr = json_decode($ucJson, true);
                    if (is_array($ucArr)) {
                        foreach ($ucArr as $uc) { if (in_array($uc, $cols, true)) $uniqueCols[] = $uc; }
                    }
                }
            } catch (Exception $e) { }

            // --- PROCESAMIENTO FILA POR FILA CON AUDITORÍA ---
            $added = 0; $skipped = 0; $updated = 0; $errors = [];
            $audit_rows = []; 
            $headerKeys = array_keys($rows[1]); 

            for ($r = 2; $r <= $highestRow; $r++) {
                $data = []; $rowHasData = false;
                $row_audit = ["row" => $r, "status" => "Omitida", "detail" => "Fila vacía"];

                $col_count = 0;
                foreach ($headerKeys as $letter) {
                    if (!isset($cols[$col_count])) { $col_count++; continue; }
                    $colName = $cols[$col_count];
                    
                    $val = $sheet->getCellByColumnAndRow($col_count + 1, $r)->getValue();
                    $cleanVal = ($val === null) ? null : trim((string)$val);
                    
                    if ($cleanVal !== '' && $cleanVal !== null) $rowHasData = true;
                    $data[$colName] = $cleanVal;
                    $col_count++;
                }
                
                if (!$rowHasData) continue; 

                // Generar Hash de integridad
                $rowHash = hash('md5', json_encode(array_values($data)));
                $data['_row_hash'] = $rowHash;
                $existsId = null;

                // 1. Prioridad: Columnas Únicas
                if (!empty($uniqueCols)) {
                    $where = []; $paramsU = [];
                    foreach ($uniqueCols as $uc) {
                        $pName = ":u_" . $uc;
                        if ($data[$uc] === null) {
                            $where[] = "`$uc` IS NULL";
                        } else {
                            $where[] = "`$uc` = $pName";
                            $paramsU[$pName] = $data[$uc];
                        }
                    }
                    $sqlU = "SELECT id FROM `{$tableName}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
                    $stmtU = $pdo->prepare($sqlU);
                    $stmtU->execute($paramsU);
                    $existsId = $stmtU->fetchColumn();
                }

                // 2. Backup: Por Hash si no hay únicas
                if (!$existsId) {
                    $stmtH = $pdo->prepare("SELECT id FROM `{$tableName}` WHERE _row_hash = :h LIMIT 1");
                    $stmtH->execute([':h' => $rowHash]);
                    $existsId = $stmtH->fetchColumn();
                }

                // --- DETERMINAR ACCIÓN ---
                if ($existsId) {
                    if ($mode === 'update') {
                        $sets = []; $pUpd = [];
                        foreach ($data as $k => $v) { 
                            if ($k !== 'id') { $sets[] = "`$k` = :$k"; $pUpd[":$k"] = $v; } 
                        }
                        $pUpd[':id'] = $existsId;
                        $pdo->prepare("UPDATE `{$tableName}` SET " . implode(', ', $sets) . " WHERE id = :id")->execute($pUpd);
                        $updated++;
                        $row_audit = ["row" => $r, "status" => "Actualizado", "detail" => "ID: $existsId"];
                    } else {
                        $skipped++;
                        $row_audit = ["row" => $r, "status" => "Omitido", "detail" => "Registro ya existe."];
                    }
                } else {
                    // Generación de asset_code si no viene o es inválido
                    $valAsset = isset($data['asset_code']) ? (string)$data['asset_code'] : '';
                    if ($valAsset === '' || stripos($valAsset, 'ERR-') !== false || stripos($valAsset, '#') !== false) {
                        $data['asset_code'] = getNextAssetCode();
                    }

                    $keys = array_keys($data);
                    $fields = "`" . implode("`,`", $keys) . "`";
                    $places = ":" . implode(", :", $keys);
                    try {
                        $pdo->prepare("INSERT INTO `{$tableName}` ($fields) VALUES ($places)")->execute($data);
                        $added++;
                        $row_audit = ["row" => $r, "status" => "Agregado", "detail" => "Nuevo registro creado"];
                    } catch (Exception $e) { 
                        $errors[] = "Fila $r: " . $e->getMessage(); 
                        $row_audit = ["row" => $r, "status" => "Error", "detail" => $e->getMessage()];
                    }
                }
                $audit_rows[] = $row_audit;
            }

            // --- REGISTRO EN IMPORT_LOGS ---
            $errorStr = substr(implode("\n", $errors), 0, 60000); 
            try {
                $ins = $pdo->prepare("INSERT INTO import_logs (filename, sheet_name, mode, added_count, skipped_count, updated_count, errors) VALUES (:f, :s, :m, :a, :sk, :u, :e)");
                $ins->execute([':f'=>basename($filePath),':s'=>$sheetName,':m'=>$mode,':a'=>$added,':sk'=>$skipped,':u'=>$updated,':e'=>$errorStr]);
            } catch (Exception $e) { }

            $summary[$sheetName] = [
                'added' => $added, 'skipped' => $skipped, 'updated' => $updated, 
                'errors' => $errors, 'audit' => $audit_rows
            ];
        }

        return $summary;
    }

    /**
     * Obtiene metadatos de un archivo Excel (Hojas y sus primeras filas para previsualizar columnas)
     */
    public static function getExcelMetadata(string $filePath)
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $metadata = [];

        foreach ($spreadsheet->getSheetNames() as $index => $sheetName) {
            $sheet = $spreadsheet->getSheet($index);
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Obtener solo las primeras 2 filas para detectar cabeceras y ejemplo
            $rows = [];
            for ($r = 1; $r <= 2; $r++) {
                $rowData = [];
                for ($c = 1; $c <= $highestColumnIndex; $c++) {
                    $val = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                    $rowData[] = ($val === null) ? '' : trim((string)$val);
                }
                if (array_filter($rowData)) $rows[] = $rowData;
            }

            $metadata[] = [
                'sheetName' => $sheetName,
                'columns' => $rows[0] ?? [],
                'sample' => $rows[1] ?? []
            ];
        }
        return $metadata;
    }

    /**
     * Ejecuta una importación mapeada para una sola tabla
     */
    public static function executeMappedImport(string $tableName, string $filePath, string $sheetName, array $mapping)
    {
        $pdo = getPDO();
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) throw new Exception("La hoja '$sheetName' no existe en el archivo.");

        // 1. Recrear la tabla
        $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`");
        $pdo->exec("CREATE TABLE `{$tableName}` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            _row_hash VARCHAR(64) NOT NULL,
            estado_actual ENUM('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
            asset_code VARCHAR(50) NULL UNIQUE,
            zabbix_host_id VARCHAR(100) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dbCols = [];
        foreach ($mapping as $excelIdx => $dbCol) {
            $dbCol = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($dbCol));
            if (in_array($dbCol, ['id', '_row_hash', 'estado_actual', 'asset_code', 'zabbix_host_id', 'created_at', 'updated_at'])) continue;
            $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$dbCol}` TEXT NULL");
            $dbCols[$excelIdx] = $dbCol;
        }

        // 2. Importar
        $highestRow = $sheet->getHighestRow();
        $added = 0; $errors = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $data = []; $rowHasData = false;
            foreach ($dbCols as $excelIdx => $dbCol) {
                $val = $sheet->getCellByColumnAndRow($excelIdx + 1, $r)->getValue();
                $cleanVal = ($val === null) ? null : trim((string)$val);
                if ($cleanVal !== '' && $cleanVal !== null) $rowHasData = true;
                $data[$dbCol] = $cleanVal;
            }
            if (!$rowHasData) continue;

            $data['_row_hash'] = hash('md5', json_encode(array_values($data)));
            $data['asset_code'] = getNextAssetCode();

            $keys = array_keys($data);
            $fields = "`" . implode("`,`", $keys) . "`";
            $places = ":" . implode(", :", $keys);
            try {
                $pdo->prepare("INSERT INTO `{$tableName}` ($fields) VALUES ($places)")->execute($data);
                $added++;
            } catch (Exception $e) { $errors[] = "Fila $r: " . $e->getMessage(); }
        }

        // 3. Registrar en sheet_configs si no existe
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sheet_configs WHERE table_name = ?");
        $stmt_check->execute([$tableName]);
        if ($stmt_check->fetchColumn() == 0) {
            $displayName = ucfirst(str_replace(['sheet_', '_'], ['', ' '], $tableName));
            $pdo->prepare("INSERT INTO sheet_configs (table_name, sheet_name) VALUES (?, ?)")
                ->execute([$tableName, $displayName]);
        }

        return ['success' => true, 'added' => $added, 'errors' => $errors];
    }
}
