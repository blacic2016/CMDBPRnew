<?php
/**
 * API para el Módulo de Flujos (Mermaid) - CMDB VILASECA
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

// Auto-creación de la tabla de historial si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mermaid_flow_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        flow_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        code TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (flow_id) REFERENCES mermaid_flows(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Continuar si ya está creada o si ocurre un fallo no crítico de permisos
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id, title, description, updated_at FROM mermaid_flows ORDER BY updated_at DESC");
            $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'flows' => $flows]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM mermaid_flows WHERE id = ?");
            $stmt->execute([$id]);
            $flow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$flow) {
                echo json_encode(['success' => false, 'error' => 'Flujo no encontrado.']);
                exit();
            }
            echo json_encode(['success' => true, 'flow' => $flow]);
            break;

        case 'save':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $code = $_POST['code'] ?? '';

            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'El título es requerido.']);
                exit();
            }
            if (empty($code)) {
                echo json_encode(['success' => false, 'error' => 'El código Mermaid es requerido.']);
                exit();
            }

            if ($id > 0) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE mermaid_flows SET title = ?, description = ?, code = ? WHERE id = ?");
                $stmt->execute([$title, $description, $code, $id]);
                $flowId = $id;
                $msg = 'Flujo actualizado correctamente.';
            } else {
                // Crear
                $stmt = $pdo->prepare("INSERT INTO mermaid_flows (title, description, code) VALUES (?, ?, ?)");
                $stmt->execute([$title, $description, $code]);
                $flowId = $pdo->lastInsertId();
                $msg = 'Flujo creado correctamente.';
            }

            // Guardar copia histórica del guardado
            $stmtHistory = $pdo->prepare("INSERT INTO mermaid_flow_history (flow_id, title, description, code) VALUES (?, ?, ?, ?)");
            $stmtHistory->execute([$flowId, $title, $description, $code]);

            echo json_encode(['success' => true, 'message' => $msg, 'id' => $flowId]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM mermaid_flows WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Flujo eliminado correctamente.']);
            break;

        case 'history':
            $flowId = (int)($_GET['flow_id'] ?? 0);
            if ($flowId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de flujo inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, title, description, created_at FROM mermaid_flow_history WHERE flow_id = ? ORDER BY created_at DESC");
            $stmt->execute([$flowId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        case 'get_history':
            $historyId = (int)($_GET['id'] ?? 0);
            if ($historyId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de historial inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM mermaid_flow_history WHERE id = ?");
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
