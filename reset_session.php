<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1; // Restaurar a superadmin
echo "<h3>Sesión restaurada como SUPER_ADMIN con éxito. Redirigiendo al Dashboard...</h3>";
header('Refresh: 2; URL=public/dashboard.php');
