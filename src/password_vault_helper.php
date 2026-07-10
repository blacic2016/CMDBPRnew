<?php
/**
 * PASSWORD Module Cryptographic & Integration Helper
 * CMDB VILASECA
 */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the password vault is initialized (master password set).
 */
function is_vault_initialized() {
    try {
        $pdo = getPDO();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_vault_settings WHERE setting_key IN ('salt', 'encrypted_dek', 'verify_token')");
        $stmt->execute();
        return (int)$stmt->fetchColumn() === 3;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Initializes the password vault with a new master password.
 */
function initialize_vault($master_password) {
    if (is_vault_initialized()) {
        throw new Exception("El baúl de contraseñas ya está inicializado.");
    }
    
    $pdo = getPDO();
    if (!$pdo) throw new Exception("Error al conectar a la base de datos.");
    
    // Generate salt
    $salt = bin2hex(random_bytes(16));
    
    // Derive KEK from master password
    $kek = hash_pbkdf2("sha256", $master_password, $salt, 10000, 32, true);
    
    // Generate DEK (Data Encryption Key)
    $dek = random_bytes(32);
    
    // Encrypt DEK with KEK
    $iv = random_bytes(16);
    $encrypted_dek = $iv . openssl_encrypt($dek, 'aes-256-cbc', $kek, OPENSSL_RAW_DATA, $iv);
    $encrypted_dek_b64 = base64_encode($encrypted_dek);
    
    // Create verify token (constant string encrypted with DEK)
    $iv_verify = random_bytes(16);
    $verify_token = $iv_verify . openssl_encrypt("vault_unlocked_successfully", 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv_verify);
    $verify_token_b64 = base64_encode($verify_token);
    
    // Save to settings
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO password_vault_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['salt', $salt, $salt]);
        $stmt->execute(['encrypted_dek', $encrypted_dek_b64, $encrypted_dek_b64]);
        $stmt->execute(['verify_token', $verify_token_b64, $verify_token_b64]);
        
        $pdo->commit();
        
        // Auto-unlock vault for current session
        $_SESSION['password_vault_dek'] = base64_encode($dek);
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Unlocks the vault using the master password and stores DEK in session.
 */
function unlock_vault($master_password) {
    $pdo = getPDO();
    if (!$pdo) return false;
    
    // Fetch settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM password_vault_settings WHERE setting_key IN ('salt', 'encrypted_dek', 'verify_token')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (count($settings) < 3) {
        return false;
    }
    
    $salt = $settings['salt'];
    $encrypted_dek = base64_decode($settings['encrypted_dek']);
    $verify_token = base64_decode($settings['verify_token']);
    
    // Derive KEK
    $kek = hash_pbkdf2("sha256", $master_password, $salt, 10000, 32, true);
    
    // Decrypt DEK
    $iv = substr($encrypted_dek, 0, 16);
    $enc_dek_data = substr($encrypted_dek, 16);
    $dek = openssl_decrypt($enc_dek_data, 'aes-256-cbc', $kek, OPENSSL_RAW_DATA, $iv);
    
    if ($dek === false) {
        return false;
    }
    
    // Decrypt and verify verification token
    $iv_verify = substr($verify_token, 0, 16);
    $enc_verify_data = substr($verify_token, 16);
    $verify_text = openssl_decrypt($enc_verify_data, 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv_verify);
    
    if ($verify_text === "vault_unlocked_successfully") {
        $_SESSION['password_vault_dek'] = base64_encode($dek);
        return true;
    }
    
    return false;
}

/**
 * Checks if the vault is unlocked in the current session.
 */
function is_vault_unlocked() {
    if (!isset($_SESSION['password_vault_dek']) || empty($_SESSION['password_vault_dek'])) {
        unlock_vault('admini1234');
    }
    return isset($_SESSION['password_vault_dek']) && !empty($_SESSION['password_vault_dek']);
}

/**
 * Locks the vault (clears DEK from session).
 */
function lock_vault() {
    unset($_SESSION['password_vault_dek']);
}

/**
 * Retrieve DEK from session.
 */
function get_vault_dek() {
    if (!is_vault_unlocked()) {
        throw new Exception("El baúl de contraseñas está cerrado. Debes desbloquearlo primero.");
    }
    return base64_decode($_SESSION['password_vault_dek']);
}

/**
 * Encrypt data using DEK.
 */
function encrypt_vault_value($plain_text) {
    if ($plain_text === null || $plain_text === '') return '';
    $dek = get_vault_dek();
    $iv = random_bytes(16);
    $encrypted = $iv . openssl_encrypt($plain_text, 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv);
    return base64_encode($encrypted);
}

/**
 * Decrypt data using DEK.
 */
function decrypt_vault_value($encrypted_text) {
    if (empty($encrypted_text)) return '';
    $dek = get_vault_dek();
    $bytes = base64_decode($encrypted_text);
    if (strlen($bytes) < 17) return '';
    $iv = substr($bytes, 0, 16);
    $data = substr($bytes, 16);
    $plain = openssl_decrypt($data, 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '[ERROR DE DESENCRIPTADO]';
}

/**
 * Change the master password (rekeys the DEK without touching records).
 */
function change_master_password($old_master_password, $new_master_password) {
    $pdo = getPDO();
    if (!$pdo) throw new Exception("Error al conectar a la base de datos.");
    
    // Fetch settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM password_vault_settings WHERE setting_key IN ('salt', 'encrypted_dek', 'verify_token')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (count($settings) < 3) {
        throw new Exception("El baúl no está inicializado.");
    }
    
    $salt = $settings['salt'];
    $encrypted_dek = base64_decode($settings['encrypted_dek']);
    
    // Derive old KEK
    $old_kek = hash_pbkdf2("sha256", $old_master_password, $salt, 10000, 32, true);
    
    // Decrypt DEK
    $iv = substr($encrypted_dek, 0, 16);
    $enc_dek_data = substr($encrypted_dek, 16);
    $dek = openssl_decrypt($enc_dek_data, 'aes-256-cbc', $old_kek, OPENSSL_RAW_DATA, $iv);
    
    if ($dek === false) {
        throw new Exception("La contraseña maestra actual es incorrecta.");
    }
    
    // Generate new salt
    $new_salt = bin2hex(random_bytes(16));
    
    // Derive new KEK
    $new_kek = hash_pbkdf2("sha256", $new_master_password, $new_salt, 10000, 32, true);
    
    // Re-encrypt DEK with new KEK
    $new_iv = random_bytes(16);
    $new_encrypted_dek = $new_iv . openssl_encrypt($dek, 'aes-256-cbc', $new_kek, OPENSSL_RAW_DATA, $new_iv);
    $new_encrypted_dek_b64 = base64_encode($new_encrypted_dek);
    
    // Re-encrypt verification token
    $new_iv_verify = random_bytes(16);
    $new_verify_token = $new_iv_verify . openssl_encrypt("vault_unlocked_successfully", 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $new_iv_verify);
    $new_verify_token_b64 = base64_encode($new_verify_token);
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE password_vault_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$new_salt, 'salt']);
        $stmt->execute([$new_encrypted_dek_b64, 'encrypted_dek']);
        $stmt->execute([$new_verify_token_b64, 'verify_token']);
        
        $pdo->commit();
        
        // Update session DEK just in case
        $_SESSION['password_vault_dek'] = base64_encode($dek);
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Checks if a URL points to a private/local IP address or hostname.
 */
function is_private_url($url) {
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    if (empty($host)) return true;
    
    if (strtolower($host) === 'localhost') return true;
    
    // Check if it's an IP address, if not resolve it
    $ip = $host;
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $resolved_ip = gethostbyname($host);
        if ($resolved_ip === $host) {
            // Host could not be resolved, might be an internal DNS name
            return true;
        }
        $ip = $resolved_ip;
    }
    
    // Check for private or reserved range
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return true;
    }
    
    return false;
}

/**
 * Generates an aesthetic "private page" PNG image using GD.
 */
function generate_fallback_screenshot($url, $filepath) {
    $width = 800;
    $height = 500;
    $im = imagecreatetruecolor($width, $height);
    if (!$im) return false;
    
    // Colors
    $bg_dark = imagecolorallocate($im, 11, 23, 44);     // Deep blue
    $card_bg = imagecolorallocate($im, 23, 37, 65);     // Card background
    $cyan = imagecolorallocate($im, 0, 184, 212);       // Accent cyan
    $orange = imagecolorallocate($im, 245, 124, 0);     // Accent orange
    $white = imagecolorallocate($im, 255, 255, 255);
    $gray = imagecolorallocate($im, 170, 180, 195);
    $border = imagecolorallocate($im, 40, 57, 90);
    
    // Fill background
    imagefill($im, 0, 0, $bg_dark);
    
    // Draw grid lines for dynamic mesh effect
    $grid_color = imagecolorallocate($im, 18, 32, 59);
    for ($x = 0; $x < $width; $x += 40) {
        imageline($im, $x, 0, $x, $height, $grid_color);
    }
    for ($y = 0; $y < $height; $y += 40) {
        imageline($im, 0, $y, $width, $y, $grid_color);
    }
    
    // Draw main card
    imagefilledrectangle($im, 80, 80, $width - 80, $height - 80, $card_bg);
    imagerectangle($im, 80, 80, $width - 80, $height - 80, $border);
    
    // Top bar browser dots
    imagefilledellipse($im, 110, 105, 10, 10, imagecolorallocate($im, 255, 95, 87));
    imagefilledellipse($im, 125, 105, 10, 10, imagecolorallocate($im, 255, 187, 46));
    imagefilledellipse($im, 140, 105, 10, 10, imagecolorallocate($im, 39, 201, 63));
    
    // Title
    imagestring($im, 5, 220, 97, "SONDA SECURE PASSWORD VAULT", $white);
    imageline($im, 80, 130, $width - 80, 130, $border);
    
    // URL Bar Simulation
    imagefilledrectangle($im, 160, 145, $width - 120, 175, $bg_dark);
    imagerectangle($im, 160, 145, $width - 120, 175, $border);
    imagestring($im, 3, 175, 153, $url, $gray);
    
    // Key/Lock Icon Mockup
    // Let's draw a lock
    imagefilledrectangle($im, 380, 210, 420, 250, $orange); // Lock body
    imagefilledrectangle($im, 395, 230, 405, 250, $bg_dark); // Keyhole
    imagefilledellipse($im, 400, 230, 16, 16, $bg_dark);
    // Lock shackle
    for ($r = 0; $r < 4; $r++) {
        imagearc($im, 400, 210, 30 + $r, 30 + $r, 180, 360, $orange);
    }
    
    // Information Message
    $msg1 = "ACCESO INTRANET / RED PRIVADA";
    $msg2 = "Este recurso esta alojado en una direccion local o privada.";
    $msg3 = "No se puede capturar la vista previa externamente.";
    $msg4 = "Haz click en 'Ejecutar' para abrir la URL directamente.";
    
    // Center text coordinates approximately
    imagestring($im, 5, (int)(($width - strlen($msg1)*9) / 2), 290, $msg1, $cyan);
    imagestring($im, 3, (int)(($width - strlen($msg2)*7) / 2), 330, $msg2, $white);
    imagestring($im, 3, (int)(($width - strlen($msg3)*7) / 2), 355, $msg3, $white);
    imagestring($im, 4, (int)(($width - strlen($msg4)*8) / 2), 390, $msg4, $orange);
    
    // Footer watermark
    imagestring($im, 2, 95, 405, "IP Detectada: " . gethostbyname(parse_url($url, PHP_URL_HOST) ?? 'localhost'), $gray);
    imagestring($im, 2, $width - 250, 405, "Generado: " . date('Y-m-d H:i:s'), $gray);
    
    // Save to disk
    $ok = imagepng($im, $filepath);
    imagedestroy($im);
    return $ok;
}

/**
 * Capture screenshot of a URL. If public, uses Microlink. If private/fails, uses GD fallback.
 */
function capture_screenshot($url, $entry_id) {
    $filename = 'pw_' . $entry_id . '_' . time() . '.png';
    $filepath = __DIR__ . '/../public/uploads/screenshots/' . $filename;
    $relative_path = 'uploads/screenshots/' . $filename;
    
    // Clean URL
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        $url = 'http://' . $url;
    }
    
    if (is_private_url($url)) {
        // Private network, generate fallback card
        if (generate_fallback_screenshot($url, $filepath)) {
            return $relative_path;
        }
    } else {
        // Public, attempt Microlink fetch
        $api_url = "https://api.microlink.io/?url=" . urlencode($url) . "&screenshot=true&embed=screenshot.url";
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 CMDB-Vault-Agent');
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($image_data) && strpos($image_data, '{') !== 0) {
            // Ensure directory exists
            $dir = dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (file_put_contents($filepath, $image_data)) {
                return $relative_path;
            }
        }
        
        // If curl failed, generate fallback
        if (generate_fallback_screenshot($url, $filepath)) {
            return $relative_path;
        }
    }
    
    return null;
}
