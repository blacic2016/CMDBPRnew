<?php
define('SKIP_AUTH', true);
require 'config.php';
require 'src/db.php';
$pdo = getPDO();
$stmt = $pdo->query("DESCRIBE ci_relationships");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
