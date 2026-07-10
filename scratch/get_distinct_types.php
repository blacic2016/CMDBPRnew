<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
$pdo = getPDO();
$stmt = $pdo->query("SELECT DISTINCT tipo FROM cotizador_pool_servicios");
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($types);
