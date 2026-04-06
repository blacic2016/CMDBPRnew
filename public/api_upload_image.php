<?php
/**
 * CMDB VILASECA - Procesador de Subida de Imágenes
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    require_login();
    // 1. Validar permisos
    if (!current_user_id()) {
        throw new Exception("Sesión expirada. Por favor inicie sesión nuevamente.");
    }

    // 2. Validar parámetros (Soporta múltiples nombres de parámetros para compatibilidad)
    $table = $_POST['table'] ?? $_POST['entity_type'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_POST['entity_id']) ? (int)$_POST['entity_id'] : 0);

    if (empty($table) || $id <= 0) {
        throw new Exception("Datos del activo incompletos (Tabla/Tipo: $table, ID: $id).");
    }

    // 3. Validar el archivo recibido
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['image']['error'] ?? 'No file';
        throw new Exception("Error en la carga del archivo. Código PHP: " . $error_code);
    }

    // 4. Configurar y validar la carpeta de destino
    $base_dir = dirname(__DIR__); 
    $upload_path = $base_dir . '/storage/uploads/';

    if (!is_dir($upload_path)) {
        if (!mkdir($upload_path, 0777, true)) {
            throw new Exception("No se pudo crear la carpeta de subidas en: " . $upload_path);
        }
    }

    // 5. Validar extensión
    $filename = $_FILES['image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed)) {
        throw new Exception("Formato no permitido. Use JPG, PNG o WEBP.");
    }

    // 6. Generar nombre único
    $new_name = "IMG_" . uniqid() . "." . $ext;
    $full_dest = $upload_path . $new_name;
    $db_relative_path = "storage/uploads/" . $new_name;

    // 7. Mover el archivo
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $full_dest)) {
        throw new Exception("Error al mover el archivo al destino. Revisa permisos de carpeta.");
    }

    // 8. Guardar en Base de Datos
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO images (entity_type, entity_id, filepath, filename, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$table, $id, $db_relative_path, $filename]);

    echo json_encode(['success' => true, 'message' => 'Imagen añadida correctamente']);

} catch (Exception $e) {
    error_log("SUBIDA FALLIDA: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}