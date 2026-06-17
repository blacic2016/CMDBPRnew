<?php
/**
 * API for Graph-Based CI Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'get_categories') {
        $stmt = $pdo->query("SELECT c.*, u.username as creator_name FROM ci_categories c LEFT JOIN users u ON c.created_by = u.id ORDER BY c.name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $categories]);
        
    } elseif ($action === 'save_category') {
        if (!has_role('SUPER_ADMIN')) {
            throw new Exception("Permiso denegado");
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? 'Nueva Categoría';
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $schema_json = $_POST['schema_json'] ?? '{}';
        $icon = $_POST['icon'] ?? 'fa-cube';
        $description = $_POST['description'] ?? null;
        $created_by = $_SESSION['user_id'] ?? null;
        
        // Validar JSON
        json_decode($schema_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("El Schema JSON no es válido.");
        }
        
        // Validar jerarquía y profundidad (Max 3 niveles)
        if ($parent_id) {
            if ($id > 0 && $parent_id == $id) {
                throw new Exception("Una categoría no puede ser su propio padre.");
            }
            
            // Comprobar referencia circular
            if ($id > 0) {
                $curr = $parent_id;
                $visited = [];
                while ($curr) {
                    if (isset($visited[$curr])) throw new Exception("Referencia circular en la jerarquía.");
                    $visited[$curr] = true;
                    if ($curr == $id) {
                        throw new Exception("El padre asignado no puede ser un descendiente de esta misma categoría.");
                    }
                    $stmt = $pdo->prepare("SELECT parent_id FROM ci_categories WHERE id = ?");
                    $stmt->execute([$curr]);
                    $curr = $stmt->fetchColumn();
                }
            }
            
            // Calcular nivel del nuevo padre
            $parent_level = 1;
            $curr = $parent_id;
            while ($curr) {
                $stmt = $pdo->prepare("SELECT parent_id FROM ci_categories WHERE id = ?");
                $stmt->execute([$curr]);
                $p = $stmt->fetchColumn();
                if ($p) {
                    $parent_level++;
                    $curr = $p;
                } else {
                    break;
                }
            }
            
            $new_level = $parent_level + 1;
            
            // Calcular profundidad del subárbol de la categoría actual (si existe)
            $getSubtreeDepth = function($cat_id, $pdo) use (&$getSubtreeDepth) {
                $stmt = $pdo->prepare("SELECT id FROM ci_categories WHERE parent_id = ?");
                $stmt->execute([$cat_id]);
                $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty($children)) return 1;
                $maxDepth = 0;
                foreach ($children as $child) {
                    $d = $getSubtreeDepth($child, $pdo);
                    if ($d > $maxDepth) $maxDepth = $d;
                }
                return $maxDepth + 1;
            };
            
            $subtree_depth = ($id > 0) ? $getSubtreeDepth($id, $pdo) : 1;
            $total_depth = $new_level + $subtree_depth - 1;
            
            if ($total_depth > 3) {
                throw new Exception("Jerarquía inválida: El sistema solo permite 3 niveles (Ej: Nivel 1 > Nivel 2 > Nivel 3). Estás intentando crear un nivel " . $total_depth . ".");
            }
        }
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ci_categories SET name=?, parent_id=?, schema_json=?, icon=?, description=? WHERE id=?");
            $stmt->execute([$name, $parent_id, $schema_json, $icon, $description, $id]);
            echo json_encode(['success' => true, 'message' => 'Categoría actualizada']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ci_categories (name, parent_id, schema_json, icon, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $parent_id, $schema_json, $icon, $description, $created_by]);
            echo json_encode(['success' => true, 'message' => 'Categoría creada', 'id' => $pdo->lastInsertId()]);
        }
        
    } elseif ($action === 'delete_category') {
        if (!has_role('SUPER_ADMIN')) {
            throw new Exception("Permiso denegado");
        }
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM ci_categories WHERE id = ?");
        $stmt->execute([$id, $id]);
        echo json_encode(['success' => true, 'message' => 'Categoría eliminada']);
        
    } elseif ($action === 'get_attributes') {
        $stmt = $pdo->query("SELECT * FROM ci_attributes ORDER BY name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } elseif ($action === 'save_attribute') {
        if (!has_role('SUPER_ADMIN')) throw new Exception("Permiso denegado");
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'string';
        $group_name = $_POST['group_name'] ?? 'General';
        $description = $_POST['description'] ?? null;
        $is_required = !empty($_POST['is_required']) ? 1 : 0;
        $created_by = $_SESSION['user_id'] ?? null;
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ci_attributes SET name=?, type=?, group_name=?, description=?, is_required=? WHERE id=?");
            $stmt->execute([$name, $type, $group_name, $description, $is_required, $id]);
            echo json_encode(['success' => true, 'message' => 'Atributo actualizado']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ci_attributes (name, type, group_name, description, is_required, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $group_name, $description, $is_required, $created_by]);
            echo json_encode(['success' => true, 'message' => 'Atributo creado']);
        }
        
    } elseif ($action === 'delete_attribute') {
        if (!has_role('SUPER_ADMIN')) throw new Exception("Permiso denegado");
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM ci_attributes WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Atributo eliminado']);
        
    } elseif ($action === 'get_instances') {
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        if ($category_id) {
            $stmt = $pdo->prepare("SELECT i.*, c.name as category_name FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id WHERE i.category_id = ? ORDER BY i.hostname ASC");
            $stmt->execute([$category_id]);
        } else {
            $stmt = $pdo->query("SELECT i.*, c.name as category_name FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id ORDER BY i.hostname ASC");
        }
        $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $instances]);
        
    } elseif ($action === 'get_ci_by_category') {
        $category_id = (int)($_GET['category_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, hostname, ip_address FROM ci_instances WHERE category_id = ? ORDER BY hostname ASC");
        $stmt->execute([$category_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } elseif ($action === 'save_instance') {
        $id = (int)($_POST['id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $hostname = $_POST['hostname'] ?? '';
        $ip_address = $_POST['ip_address'] ?? null;
        $source = $_POST['source'] ?? 'manual';
        $zabbix_host_id = $_POST['zabbix_host_id'] ?? null;
        $status = $_POST['status'] ?? 'Activo';
        $description = $_POST['description'] ?? null;
        $created_by = $_SESSION['user_id'] ?? null;
        
        // Attributes is all other POST data except the standard fields
        $standard_fields = ['action', 'id', 'category_id', 'hostname', 'ip_address', 'source', 'zabbix_host_id', 'status', 'description', 'ci_relations'];
        $attributes = [];
        foreach ($_POST as $key => $val) {
            if (!in_array($key, $standard_fields)) {
                $attributes[$key] = $val;
            }
        }
        $attributes_json = json_encode($attributes);
        
        $pdo->beginTransaction();
        
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE ci_instances SET category_id=?, hostname=?, ip_address=?, source=?, zabbix_host_id=?, attributes_json=?, status=?, description=? WHERE id=?");
                $stmt->execute([$category_id, $hostname, $ip_address, $source, $zabbix_host_id, $attributes_json, $status, $description, $id]);
                
                // Limpiar relaciones anteriores donde este CI es origen
                $stmtDel = $pdo->prepare("DELETE FROM ci_relationships WHERE source_type='ci_instance' AND source_id=?");
                $stmtDel->execute([$id]);
                
                $instance_id = $id;
                $msg = 'CI actualizado';
            } else {
                $stmt = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, ip_address, source, zabbix_host_id, attributes_json, status, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $hostname, $ip_address, $source, $zabbix_host_id, $attributes_json, $status, $description, $created_by]);
                
                $instance_id = $pdo->lastInsertId();
                $msg = 'CI creado';
            }

            // Insertar nuevas relaciones
            if (!empty($_POST['ci_relations'])) {
                $relations = json_decode($_POST['ci_relations'], true);
                if (is_array($relations) && count($relations) > 0) {
                    $stmtRel = $pdo->prepare("INSERT INTO ci_relationships (source_type, source_id, target_type, target_id, relation_type, impact) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($relations as $rel) {
                        if (!empty($rel['target_id']) && !empty($rel['type'])) {
                            $stmtRel->execute([
                                'ci_instance',
                                $instance_id,
                                'ci_instance', // En un futuro podríamos tener otros tipos de target (ej. service)
                                (int)$rel['target_id'],
                                $rel['type'],
                                $rel['impact'] ?? 'Desconocido'
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $msg, 'id' => $instance_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'delete_instance') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM ci_instances WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'CI eliminado']);
        
    } elseif ($action === 'get_ci_business_view') {
        $ci_id = isset($_GET['ci_id']) ? (int)$_GET['ci_id'] : 0;
        
        if ($ci_id > 0) {
            // Fetch Relationships related to this CI
            $stmtRels = $pdo->prepare("SELECT source_id, target_id, relation_type, impact FROM ci_relationships WHERE (source_id = ? OR target_id = ?) AND source_type='ci_instance' AND target_type='ci_instance'");
            $stmtRels->execute([$ci_id, $ci_id]);
            $rels = $stmtRels->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unique CI IDs from relationships, plus the central CI
            $ci_ids = [$ci_id];
            foreach ($rels as $r) {
                if (!in_array($r['source_id'], $ci_ids)) $ci_ids[] = $r['source_id'];
                if (!in_array($r['target_id'], $ci_ids)) $ci_ids[] = $r['target_id'];
            }
            
            $in_placeholders = implode(',', array_fill(0, count($ci_ids), '?'));
            $stmtCIs = $pdo->prepare("SELECT i.id, i.hostname, i.category_id, i.ip_address, i.status FROM ci_instances i WHERE id IN ($in_placeholders)");
            $stmtCIs->execute($ci_ids);
            $cis = $stmtCIs->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unique category IDs from the CIs
            $cat_ids = [];
            foreach ($cis as $c) {
                if (!in_array($c['category_id'], $cat_ids)) $cat_ids[] = $c['category_id'];
            }
            
            // Need to recursively get parent categories to build the hierarchy properly
            $all_categories = [];
            if (!empty($cat_ids)) {
                // Since categories are few, just fetch all and filter in PHP
                $stmtCats = $pdo->query("SELECT * FROM ci_categories ORDER BY parent_id ASC, name ASC");
                $allCatsDB = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
                $catMap = [];
                foreach ($allCatsDB as $c) { $catMap[$c['id']] = $c; }
                
                $needed_cats = [];
                foreach ($cat_ids as $cid) {
                    $curr = $cid;
                    $visited = [];
                    while ($curr && isset($catMap[$curr]) && !isset($visited[$curr])) {
                        $visited[$curr] = true;
                        if (!isset($needed_cats[$curr])) {
                            $needed_cats[$curr] = $catMap[$curr];
                        }
                        $curr = $catMap[$curr]['parent_id'];
                    }
                }
                $categories = array_values($needed_cats);
            } else {
                $categories = [];
            }
        } else {
            // Fetch ALL (fallback)
            $stmtCats = $pdo->query("SELECT * FROM ci_categories ORDER BY parent_id ASC, name ASC");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
            $stmtCIs = $pdo->query("SELECT i.id, i.hostname, i.category_id, i.ip_address, i.status FROM ci_instances i");
            $cis = $stmtCIs->fetchAll(PDO::FETCH_ASSOC);
            $stmtRels = $pdo->query("SELECT source_id, target_id, relation_type, impact FROM ci_relationships WHERE source_type='ci_instance' AND target_type='ci_instance'");
            $rels = $stmtRels->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } elseif ($action === 'get_ci_details') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT i.*, c.name as category_name, c.schema_json FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id WHERE i.id = ?");
        $stmt->execute([$id]);
        $ci = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ci) {
            $catMap = [];
            $stmtCats = $pdo->query("SELECT * FROM ci_categories");
            foreach ($stmtCats->fetchAll(PDO::FETCH_ASSOC) as $c) { $catMap[$c['id']] = $c; }
            
            $lineage = [];
            $curr = $ci['category_id'];
            $visited = [];
            while ($curr && isset($catMap[$curr]) && !isset($visited[$curr])) {
                $visited[$curr] = true;
                array_unshift($lineage, $catMap[$curr]);
                $curr = $catMap[$curr]['parent_id'];
            }
            
            $stmtRel = $pdo->prepare("SELECT r.*, c.hostname as target_name FROM ci_relationships r JOIN ci_instances c ON r.target_id = c.id WHERE r.source_type='ci_instance' AND r.source_id=?");
            $stmtRel->execute([$id]);
            $relations = $stmtRel->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => ['ci' => $ci, 'lineage' => $lineage, 'relations' => $relations]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'CI no encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
