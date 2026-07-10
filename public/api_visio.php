<?php
/**
 * API para el Módulo de Modelos Visio (VSDX) - CMDB VILASECA
 */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

// Validar login
require_login();
if (!has_module_access('diagrams')) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../src/db.php';
$pdo = getPDO();

// Auto-creación de tablas de Visio si no existen
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagrams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        xml_content LONGTEXT NOT NULL,
        filename_original VARCHAR(255) NULL,
        ci_instance_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_visio_ci (ci_instance_id),
        CONSTRAINT fk_visio_ci FOREIGN KEY (ci_instance_id) REFERENCES ci_instances(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagram_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        diagram_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        xml_content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_visio_history_diagram FOREIGN KEY (diagram_id) REFERENCES visio_diagrams(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
    // Si la creación con llave foránea falla por alguna razón del motor, creamos sin restricción dura
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagrams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            xml_content LONGTEXT NOT NULL,
            filename_original VARCHAR(255) NULL,
            ci_instance_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagram_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            diagram_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            xml_content LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (PDOException $ex) {
        // Fallback final
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Listar diagramas con info de CIs si están asociados
            $stmt = $pdo->query("
                SELECT vd.id, vd.title, vd.description, vd.filename_original, vd.updated_at, vd.ci_instance_id,
                       ci.hostname AS ci_hostname, ci.ci_unique AS ci_unique, cat.name AS category_name
                FROM visio_diagrams vd
                LEFT JOIN ci_instances ci ON vd.ci_instance_id = ci.id
                LEFT JOIN ci_categories cat ON ci.category_id = cat.id
                ORDER BY vd.updated_at DESC
            ");
            $diagrams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'diagrams' => $diagrams]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de diagrama inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM visio_diagrams WHERE id = ?");
            $stmt->execute([$id]);
            $diagram = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$diagram) {
                echo json_encode(['success' => false, 'error' => 'Diagrama no encontrado.']);
                exit();
            }
            echo json_encode(['success' => true, 'diagram' => $diagram]);
            break;

        case 'save':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $xml = $_POST['xml_content'] ?? '';
            $filename_original = trim($_POST['filename_original'] ?? '');
            $ci_instance_id = $_POST['ci_instance_id'] !== '' ? (int)$_POST['ci_instance_id'] : null;

            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'El título es requerido.']);
                exit();
            }
            if (empty($xml)) {
                echo json_encode(['success' => false, 'error' => 'El contenido del diagrama es requerido.']);
                exit();
            }

            if ($id > 0) {
                // Actualizar
                $stmt = $pdo->prepare("
                    UPDATE visio_diagrams 
                    SET title = ?, description = ?, xml_content = ?, filename_original = ?, ci_instance_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $xml, $filename_original, $ci_instance_id, $id]);
                $diagramId = $id;
                $msg = 'Diagrama actualizado correctamente.';
            } else {
                // Crear
                $stmt = $pdo->prepare("
                    INSERT INTO visio_diagrams (title, description, xml_content, filename_original, ci_instance_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $xml, $filename_original, $ci_instance_id]);
                $diagramId = $pdo->lastInsertId();
                $msg = 'Diagrama creado y guardado correctamente.';
            }

            // Registrar en el historial de versiones
            $stmtHistory = $pdo->prepare("
                INSERT INTO visio_diagram_history (diagram_id, title, description, xml_content) 
                VALUES (?, ?, ?, ?)
            ");
            $stmtHistory->execute([$diagramId, $title, $description, $xml]);

            echo json_encode(['success' => true, 'message' => $msg, 'id' => $diagramId]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM visio_diagrams WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Diagrama eliminado correctamente.']);
            break;

        case 'history':
            $diagramId = (int)($_GET['diagram_id'] ?? 0);
            if ($diagramId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de diagrama inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, title, description, created_at FROM visio_diagram_history WHERE diagram_id = ? ORDER BY created_at DESC");
            $stmt->execute([$diagramId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        case 'get_history':
            $historyId = (int)($_GET['id'] ?? 0);
            if ($historyId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de historial inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM visio_diagram_history WHERE id = ?");
            $stmt->execute([$historyId]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$version) {
                echo json_encode(['success' => false, 'error' => 'Versión histórica no encontrada.']);
                exit();
            }
            echo json_encode(['success' => true, 'version' => $version]);
            break;

        case 'list_cis':
            // Listar CIs disponibles para asociar
            $stmt = $pdo->query("
                SELECT ci.id, ci.hostname, ci.ci_unique, cat.name AS category_name 
                FROM ci_instances ci 
                JOIN ci_categories cat ON ci.category_id = cat.id 
                ORDER BY cat.name ASC, ci.hostname ASC
            ");
            $cis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'cis' => $cis]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
