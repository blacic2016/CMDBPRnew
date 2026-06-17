<?php
$_REQUEST['action'] = 'get_ci_business_view';
$_GET['ci_id'] = 1;
$_SESSION['user_id'] = 1;
define('SKIP_AUTH', true);
require 'config.php';
require 'src/db.php';

$pdo = getPDO();
// ... run logic manually to see
$action = 'get_ci_business_view';
$ci_id = 1;

$stmtRels = $pdo->prepare("SELECT source_id, target_id, relation_type, impact FROM ci_relationships WHERE (source_id = ? OR target_id = ?) AND source_type='ci_instance' AND target_type='ci_instance'");
$stmtRels->execute([$ci_id, $ci_id]);
$rels = $stmtRels->fetchAll(PDO::FETCH_ASSOC);

print_r($rels);

$ci_ids = [$ci_id];
foreach ($rels as $r) {
    if (!in_array($r['source_id'], $ci_ids)) $ci_ids[] = $r['source_id'];
    if (!in_array($r['target_id'], $ci_ids)) $ci_ids[] = $r['target_id'];
}

$in_placeholders = implode(',', array_fill(0, count($ci_ids), '?'));
$stmtCIs = $pdo->prepare("SELECT i.id, i.hostname, i.category_id, i.ip_address, i.status FROM ci_instances i WHERE id IN ($in_placeholders)");
$stmtCIs->execute($ci_ids);
$cis = $stmtCIs->fetchAll(PDO::FETCH_ASSOC);

print_r($cis);

