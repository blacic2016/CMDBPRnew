<?php
require_once __DIR__ . '/../config.php';
$_SESSION['user_id'] = 1;
header('Location: cotizador/index.php');
exit;
