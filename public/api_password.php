<?php
/**
 * API for the PASSWORD Module - CMDB VILASECA
 */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';
require_once __DIR__ . '/../src/password_vault_helper.php';

// Ensure user is logged in
require_login();

// Check if user has permission to PASSWORD module
if (!has_role(['SUPER_ADMIN', 'ADMIN']) && !has_module_access('password')) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para acceder al módulo PASSWORD.']);
    exit();
}

$pdo = getPDO();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'vault_status':
            echo json_encode([
                'success' => true,
                'initialized' => is_vault_initialized(),
                'unlocked' => is_vault_unlocked()
            ]);
            break;

        case 'initialize':
            $master_password = $_POST['master_password'] ?? '';
            if (strlen($master_password) < 6) {
                echo json_encode(['success' => false, 'error' => 'La contraseña maestra debe tener al menos 6 caracteres.']);
                exit();
            }
            try {
                initialize_vault($master_password);
                echo json_encode(['success' => true, 'message' => 'Baúl inicializado con éxito.']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'unlock':
            $master_password = $_POST['master_password'] ?? '';
            if (empty($master_password)) {
                echo json_encode(['success' => false, 'error' => 'La contraseña maestra es requerida.']);
                exit();
            }
            if (unlock_vault($master_password)) {
                echo json_encode(['success' => true, 'message' => 'Baúl desbloqueado con éxito.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Contraseña maestra incorrecta.']);
            }
            break;

        case 'lock':
            lock_vault();
            echo json_encode(['success' => true, 'message' => 'Baúl cerrado con éxito.']);
            break;

        case 'change_master':
            $old_master = $_POST['old_master_password'] ?? '';
            $new_master = $_POST['new_master_password'] ?? '';
            
            if (empty($old_master) || empty($new_master)) {
                echo json_encode(['success' => false, 'error' => 'Ambas contraseñas son requeridas.']);
                exit();
            }
            if (strlen($new_master) < 6) {
                echo json_encode(['success' => false, 'error' => 'La nueva contraseña maestra debe tener al menos 6 caracteres.']);
                exit();
            }
            
            try {
                change_master_password($old_master, $new_master);
                echo json_encode(['success' => true, 'message' => 'Contraseña maestra cambiada con éxito.']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'list':
            // Verify vault is unlocked
            if (!is_vault_unlocked()) {
                echo json_encode(['success' => false, 'error' => 'El baúl está bloqueado.', 'locked' => true]);
                exit();
            }
            
            // List entries
            // We return encrypted usernames/passwords as placeholder in lists for security.
            // observations are decrypted for showing in observations field, or can be decrypted on demand.
            // Let's decrypt observations in the list since they are observations, but keep password/username masked or fetched on-demand.
            $stmt = $pdo->prepare("SELECT p.id, p.name, p.url, p.tags, p.screenshot_path, p.created_at, p.updated_at, p.observations, u.username as creator 
                                   FROM password_entries p 
                                   JOIN users u ON p.created_by = u.id 
                                   ORDER BY p.name ASC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $entries = [];
            foreach ($rows as $row) {
                // Decrypt observations
                $row['observations'] = decrypt_vault_value($row['observations']);
                $entries[] = $row;
            }
            
            echo json_encode(['success' => true, 'entries' => $entries]);
            break;

        case 'get_decrypted':
            if (!is_vault_unlocked()) {
                echo json_encode(['success' => false, 'error' => 'El baúl está bloqueado.', 'locked' => true]);
                exit();
            }
            
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT username, password, username_sec, password_sec, observations FROM password_entries WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                echo json_encode(['success' => false, 'error' => 'Entrada no encontrada.']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'username' => decrypt_vault_value($row['username']),
                'password' => decrypt_vault_value($row['password']),
                'username_sec' => decrypt_vault_value($row['username_sec']),
                'password_sec' => decrypt_vault_value($row['password_sec']),
                'observations' => decrypt_vault_value($row['observations'])
            ]);
            break;
 
        case 'save':
            if (!is_vault_unlocked()) {
                echo json_encode(['success' => false, 'error' => 'El baúl está bloqueado.', 'locked' => true]);
                exit();
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $username_sec = trim($_POST['username_sec'] ?? '');
            $password_sec = trim($_POST['password_sec'] ?? '');
            $observations = trim($_POST['observations'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            $screenshot_base64 = $_POST['screenshot_base64'] ?? '';
            $delete_screenshot = isset($_POST['delete_screenshot']) && $_POST['delete_screenshot'] === '1';
            
            if (empty($name) || empty($url) || empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'error' => 'Todos los campos marcados con (*) son obligatorios.']);
                exit();
            }
                  // Encrypt sensitive data
            $enc_username = encrypt_vault_value($username);
            $enc_password = encrypt_vault_value($password);
            $enc_username_sec = encrypt_vault_value($username_sec);
            $enc_password_sec = encrypt_vault_value($password_sec);
            $enc_observations = encrypt_vault_value($observations);
            $user_id = current_user_id();
            
            $pdo->beginTransaction();
            try {
                // Process base64 screenshot if uploaded/pasted
                $screenshot_path = null;
                if (!empty($screenshot_base64)) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $screenshot_base64, $type)) {
                        $data = substr($screenshot_base64, strpos($screenshot_base64, ',') + 1);
                        $type = strtolower($type[1]);
                        if (in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                            $data = base64_decode($data);
                            if ($data !== false) {
                                $filename = 'pw_user_' . uniqid() . '.' . $type;
                                $filepath = __DIR__ . '/../public/uploads/screenshots/' . $filename;
                                if (!is_dir(dirname($filepath))) {
                                    if (!@mkdir(dirname($filepath), 0777, true) && !is_dir(dirname($filepath))) {
                                        throw new Exception("No se pudo crear el directorio de destino de capturas.");
                                    }
                                }
                                if (@file_put_contents($filepath, $data) === false) {
                                    throw new Exception("No se pudo guardar la captura en el disco. Permiso denegado.");
                                }
                                $screenshot_path = 'uploads/screenshots/' . $filename;
                            } else {
                                throw new Exception("Error al decodificar la imagen en base64.");
                            }
                        } else {
                            throw new Exception("Formato de imagen no permitido (use JPG, PNG o WebP).");
                        }
                    } else {
                        throw new Exception("Datos de imagen corruptos o formato incorrecto.");
                    }
                }

                $is_new = ($id <= 0);
                
                if ($is_new) {
                    // Create entry
                    $stmt = $pdo->prepare("INSERT INTO password_entries (name, url, username, password, username_sec, password_sec, observations, tags, screenshot_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $url, $enc_username, $enc_password, $enc_username_sec, $enc_password_sec, $enc_observations, $tags, $screenshot_path, $user_id]);
                    $entry_id = $pdo->lastInsertId();
                    
                    // Add creation history
                    $stmt_hist = $pdo->prepare("INSERT INTO password_history (entry_id, username, password, username_sec, password_sec, observations, changed_by, change_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'creation')");
                    $stmt_hist->execute([$entry_id, $enc_username, $enc_password, $enc_username_sec, $enc_password_sec, $enc_observations, $user_id]);
                } else {
                    // Fetch existing to see if password/username/url/sec changed
                    $stmt_ex = $pdo->prepare("SELECT username, password, username_sec, password_sec, observations, url FROM password_entries WHERE id = ?");
                    $stmt_ex->execute([$id]);
                    $existing = $stmt_ex->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$existing) {
                        throw new Exception("Entrada no encontrada para actualizar.");
                    }
                    
                    $entry_id = $id;
                    
                    // Update entry
                    if ($delete_screenshot) {
                        $stmt = $pdo->prepare("UPDATE password_entries SET name = ?, url = ?, username = ?, password = ?, username_sec = ?, password_sec = ?, observations = ?, tags = ?, screenshot_path = NULL WHERE id = ?");
                        $stmt->execute([$name, $url, $enc_username, $enc_password, $enc_username_sec, $enc_password_sec, $enc_observations, $tags, $id]);
                    } else if ($screenshot_path) {
                        $stmt = $pdo->prepare("UPDATE password_entries SET name = ?, url = ?, username = ?, password = ?, username_sec = ?, password_sec = ?, observations = ?, tags = ?, screenshot_path = ? WHERE id = ?");
                        $stmt->execute([$name, $url, $enc_username, $enc_password, $enc_username_sec, $enc_password_sec, $enc_observations, $tags, $screenshot_path, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE password_entries SET name = ?, url = ?, username = ?, password = ?, username_sec = ?, password_sec = ?, observations = ?, tags = ? WHERE id = ?");
                        $stmt->execute([$name, $url, $enc_username, $enc_password, $enc_username_sec, $enc_password_sec, $enc_observations, $tags, $id]);
                    }
                    
                    // If changes occurred, log to history
                    if ($existing['username'] !== $enc_username || 
                        $existing['password'] !== $enc_password || 
                        $existing['username_sec'] !== $enc_username_sec || 
                        $existing['password_sec'] !== $enc_password_sec || 
                        $existing['observations'] !== $enc_observations) {
                        
                        $stmt_hist = $pdo->prepare("INSERT INTO password_history (entry_id, username, password, username_sec, password_sec, observations, changed_by, change_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'modification')");
                        $stmt_hist->execute([$entry_id, $enc_username, $enc_password, $enc_username_sec, $enc_password_sec, $enc_observations, $user_id]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => $is_new ? 'Entrada creada correctamente.' : 'Entrada modificada correctamente.',
                    'id' => $entry_id
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'delete':
            if (!is_vault_unlocked()) {
                echo json_encode(['success' => false, 'error' => 'El baúl está bloqueado.', 'locked' => true]);
                exit();
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            
            // Delete entry (cascades history via foreign keys)
            $stmt = $pdo->prepare("DELETE FROM password_entries WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Entrada eliminada correctamente.']);
            break;

        case 'history':
            if (!is_vault_unlocked()) {
                echo json_encode(['success' => false, 'error' => 'El baúl está bloqueado.', 'locked' => true]);
                exit();
            }
            
            $entry_id = (int)($_GET['entry_id'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT h.id, h.username, h.password, h.username_sec, h.password_sec, h.observations, h.changed_at, h.change_type, u.username as changer 
                                   FROM password_history h 
                                   JOIN users u ON h.changed_by = u.id 
                                   WHERE h.entry_id = ? 
                                   ORDER BY h.changed_at DESC");
            $stmt->execute([$entry_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $history = [];
            foreach ($rows as $row) {
                $row['username'] = decrypt_vault_value($row['username']);
                $row['password'] = decrypt_vault_value($row['password']);
                $row['username_sec'] = decrypt_vault_value($row['username_sec']);
                $row['password_sec'] = decrypt_vault_value($row['password_sec']);
                $row['observations'] = decrypt_vault_value($row['observations']);
                $history[] = $row;
            }
            
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        case 'upload_screenshot':
            if (!is_vault_unlocked()) {
                echo json_encode(['success' => false, 'error' => 'El baúl está bloqueado.', 'locked' => true]);
                exit();
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $screenshot_base64 = $_POST['screenshot_base64'] ?? '';
            
            if ($id <= 0 || empty($screenshot_base64)) {
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos.']);
                exit();
            }
            
            if (preg_match('/^data:image\/(\w+);base64,/', $screenshot_base64, $type)) {
                $data = substr($screenshot_base64, strpos($screenshot_base64, ',') + 1);
                $type = strtolower($type[1]);
                if (in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                    $data = base64_decode($data);
                    if ($data !== false) {
                        $filename = 'pw_user_' . uniqid() . '.' . $type;
                        $filepath = __DIR__ . '/../public/uploads/screenshots/' . $filename;
                        if (!is_dir(dirname($filepath))) {
                            mkdir(dirname($filepath), 0777, true);
                        }
                        if (file_put_contents($filepath, $data)) {
                            $screenshot_path = 'uploads/screenshots/' . $filename;
                            
                            $stmt = $pdo->prepare("UPDATE password_entries SET screenshot_path = ? WHERE id = ?");
                            $stmt->execute([$screenshot_path, $id]);
                            
                            echo json_encode(['success' => true, 'message' => 'Captura actualizada correctamente.', 'screenshot_path' => $screenshot_path]);
                            exit();
                        }
                    }
                }
            }
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen o formato inválido.']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
