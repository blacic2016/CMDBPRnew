<?php

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=CMDBVilaseca2;charset=utf8mb4", "root", "zabbix", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $pdo->beginTransaction();

    // 1. Set 00 Cliente (ID 45) as the parent of all major root categories
    $pdo->exec("UPDATE ci_categories SET parent_id = 45 WHERE id IN (37, 38, 39, 40, 41, 42)");
    
    // 2. Set the root unique code for 00 Cliente to CAT-00
    $pdo->exec("UPDATE ci_categories SET cat_unique = 'CAT-00' WHERE id = 45");
    
    // 3. Reset all other cat_unique codes to unique TEMPORARY prefixes so they get recalculated
    $pdo->exec("UPDATE ci_categories SET cat_unique = CONCAT('TEMP-', id) WHERE id != 45");
    
    $pdo->commit();
    echo "Parent hierarchy updated successfully. Starting unique code recalculation...\n";
    
    // 4. Recursive recalculation function matching the system design
    function recurse_recalculate($cat_id, $pdo) {
        $stmt = $pdo->prepare("SELECT parent_id, cat_unique FROM ci_categories WHERE id = ?");
        $stmt->execute([$cat_id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cat) return;
        
        // If it's already set (and it's not the root that we set to CAT-00), skip
        if ($cat_id != 45 && !empty($cat['cat_unique']) && strpos($cat['cat_unique'], 'TEMP-') === false) {
            // Recurse children
            $stmt_c = $pdo->prepare("SELECT id FROM ci_categories WHERE parent_id = ?");
            $stmt_c->execute([$cat_id]);
            $children = $stmt_c->fetchAll(PDO::FETCH_COLUMN);
            foreach ($children as $cid) {
                recurse_recalculate($cid, $pdo);
            }
            return;
        }
        
        $parent_id = $cat['parent_id'];
        $new_unique = '';
        
        if (!$parent_id) {
            $new_unique = 'CAT-00';
        } else {
            // Get parent unique
            $stmt_p = $pdo->prepare("SELECT cat_unique FROM ci_categories WHERE id = ?");
            $stmt_p->execute([$parent_id]);
            $parent_unique = $stmt_p->fetchColumn();
            
            // Get siblings
            $stmt_s = $pdo->prepare("SELECT id, name, cat_unique FROM ci_categories WHERE parent_id = ? AND id != ? ORDER BY id ASC");
            $stmt_s->execute([$parent_id, $cat_id]);
            $siblings = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
            
            $existing_nums = [];
            foreach ($siblings as $sib) {
                if (!empty($sib['cat_unique']) && strpos($sib['cat_unique'], $parent_unique) === 0) {
                    $suffix = substr($sib['cat_unique'], strlen($parent_unique));
                    $existing_nums[] = (int)$suffix;
                }
            }
            
            // Find first free sequence from 01 to 99
            $next_seq = 1;
            for ($i = 1; $i <= 99; $i++) {
                if (!in_array($i, $existing_nums)) {
                    $next_seq = $i;
                    break;
                }
            }
            
            $new_unique = $parent_unique . str_pad($next_seq, 2, '0', STR_PAD_LEFT);
        }
        
        // Update category
        $stmt_u = $pdo->prepare("UPDATE ci_categories SET cat_unique = ? WHERE id = ?");
        $stmt_u->execute([$new_unique, $cat_id]);
        
        echo "Updated Category ID $cat_id to code: $new_unique\n";
        
        // Recurse children
        $stmt_c = $pdo->prepare("SELECT id FROM ci_categories WHERE parent_id = ?");
        $stmt_c->execute([$cat_id]);
        $children = $stmt_c->fetchAll(PDO::FETCH_COLUMN);
        foreach ($children as $cid) {
            recurse_recalculate($cid, $pdo);
        }
    }
    
    // Start recursion from 00 Cliente
    recurse_recalculate(45, $pdo);
    
    echo "Recalculation complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
