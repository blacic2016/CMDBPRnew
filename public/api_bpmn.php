<?php
/**
 * API para el Módulo de Procesos (BPMN) - CMDB VILASECA
 */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

// Validar login
require_login();

require_once __DIR__ . '/../src/db.php';
$pdo = getPDO();

// Auto-creación de tablas de BPMN si no existen
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bpmn_diagrams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        xml_content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bpmn_diagram_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        diagram_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        xml_content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (diagram_id) REFERENCES bpmn_diagrams(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Continuar
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id, title, description, updated_at FROM bpmn_diagrams ORDER BY updated_at DESC");
            $diagrams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'diagrams' => $diagrams]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM bpmn_diagrams WHERE id = ?");
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

            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'El título es requerido.']);
                exit();
            }
            if (empty($xml)) {
                echo json_encode(['success' => false, 'error' => 'El contenido XML de BPMN es requerido.']);
                exit();
            }

            if ($id > 0) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE bpmn_diagrams SET title = ?, description = ?, xml_content = ? WHERE id = ?");
                $stmt->execute([$title, $description, $xml, $id]);
                $diagramId = $id;
                $msg = 'Diagrama BPMN actualizado correctamente.';
            } else {
                // Crear
                $stmt = $pdo->prepare("INSERT INTO bpmn_diagrams (title, description, xml_content) VALUES (?, ?, ?)");
                $stmt->execute([$title, $description, $xml]);
                $diagramId = $pdo->lastInsertId();
                $msg = 'Diagrama BPMN creado correctamente.';
            }

            // Guardar en el historial
            $stmtHistory = $pdo->prepare("INSERT INTO bpmn_diagram_history (diagram_id, title, description, xml_content) VALUES (?, ?, ?, ?)");
            $stmtHistory->execute([$diagramId, $title, $description, $xml]);

            echo json_encode(['success' => true, 'message' => $msg, 'id' => $diagramId]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM bpmn_diagrams WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Diagrama BPMN eliminado correctamente.']);
            break;

        case 'history':
            $diagramId = (int)($_GET['diagram_id'] ?? 0);
            if ($diagramId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de diagrama inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, title, description, created_at FROM bpmn_diagram_history WHERE diagram_id = ? ORDER BY created_at DESC");
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
            $stmt = $pdo->prepare("SELECT * FROM bpmn_diagram_history WHERE id = ?");
            $stmt->execute([$historyId]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$version) {
                echo json_encode(['success' => false, 'error' => 'Versión de historial no encontrada.']);
                exit();
            }
            echo json_encode(['success' => true, 'version' => $version]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
