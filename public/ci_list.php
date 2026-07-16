<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();
if (!has_module_access('ci_list')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Inventario CMDB';
require_once __DIR__ . '/partials/header.php';

$pdo = getPDO();

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$show_all = isset($_GET['show_all']) ? (int)$_GET['show_all'] : 1;
$category_name_title = 'Todos los CIs';

if ($category_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT name FROM ci_categories WHERE id = ?");
    $stmt_cat->execute([$category_id]);
    $cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $category_name_title = $cat['name'];
    }
}

// Obtener descendientes para cuando mostremos "Todos"
$all_cats = $pdo->query("SELECT id, parent_id FROM ci_categories")->fetchAll(PDO::FETCH_ASSOC);
$descendant_ids = [$category_id];

if (!function_exists('getDescendants')) {
    function getDescendants($parentId, $categories) {
        $ids = [];
        foreach($categories as $c) {
            if ($c['parent_id'] == $parentId) {
                $ids[] = $c['id'];
                $ids = array_merge($ids, getDescendants($c['id'], $categories));
            }
        }
        return $ids;
    }
}
$descendant_ids = array_merge($descendant_ids, getDescendants($category_id, $all_cats));

// Ejecutar consulta de CIs basados en show_all y category_id
if ($category_id > 0) {
    if ($show_all) {
        $in_placeholders = implode(',', array_fill(0, count($descendant_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as category_name, u.username as creator_name, p.hostname as parent_ci_name, pc.name as parent_category_name
            FROM ci_instances i 
            JOIN ci_categories c ON i.category_id = c.id 
            LEFT JOIN users u ON i.created_by = u.id
            LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
            LEFT JOIN ci_categories pc ON p.category_id = pc.id
            WHERE i.category_id IN ($in_placeholders)
            ORDER BY i.created_at DESC
        ");
        $stmt->execute($descendant_ids);
    } else {
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as category_name, u.username as creator_name, p.hostname as parent_ci_name, pc.name as parent_category_name
            FROM ci_instances i 
            JOIN ci_categories c ON i.category_id = c.id 
            LEFT JOIN users u ON i.created_by = u.id
            LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
            LEFT JOIN ci_categories pc ON p.category_id = pc.id
            WHERE i.category_id = ?
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$category_id]);
    }
} else {
    $stmt = $pdo->query("
        SELECT i.*, c.name as category_name, u.username as creator_name, p.hostname as parent_ci_name, pc.name as parent_category_name
        FROM ci_instances i 
        JOIN ci_categories c ON i.category_id = c.id 
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
        LEFT JOIN ci_categories pc ON p.category_id = pc.id
        ORDER BY i.created_at DESC
    ");
}
$instances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener esquema y herencia de atributos si estamos en una categoría
$schema_properties = [];
if ($category_id > 0) {
    $current_id = $category_id;
    $lineage_cats = [];
    while ($current_id) {
        $stmt_lin = $pdo->prepare("SELECT id, name, parent_id, schema_json FROM ci_categories WHERE id = ?");
        $stmt_lin->execute([$current_id]);
        $c = $stmt_lin->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            array_unshift($lineage_cats, $c);
            $current_id = $c['parent_id'];
        } else {
            break;
        }
    }
    foreach ($lineage_cats as $cat) {
        if (!empty($cat['schema_json'])) {
            $schema_dec = json_decode($cat['schema_json'], true);
            if (isset($schema_dec['properties']) && is_array($schema_dec['properties'])) {
                foreach ($schema_dec['properties'] as $key => $prop) {
                    $schema_properties[$key] = $prop;
                }
            }
        }
    }
}

$table_cols = [];
foreach ($schema_properties as $key => $prop) {
    $table_cols[$key] = $prop['title'] ?? ucfirst(str_replace('_', ' ', $key));
}
?>

<div class="container-fluid pt-4">
    <div class="card card-outline card-primary shadow-lg">
        <div class="card-header border-bottom-0 bg-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-sitemap mr-2 text-primary"></i> Inventario CMDB</h3>
                <div>
                    <a href="ci_business_view.php" class="btn btn-sm btn-info"><i class="fas fa-project-diagram mr-1"></i> Business View</a>
                    <a href="ci_builder.php<?php echo $category_id > 0 ? '?category_id=' . $category_id : ''; ?>" id="btn-nuevo-ci" class="btn btn-sm btn-success"><i class="fas fa-plus mr-1"></i> Nuevo CI</a>
                    <?php if (has_role('SUPER_ADMIN')): ?>
                        <a href="ci_categories.php" class="btn btn-sm btn-warning"><i class="fas fa-layer-group mr-1"></i> CMDB Admin (Categorías)</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php $active_filter = $category_id > 0 ? $category_name_title : 'all'; ?>
            <!-- Pestañas Dinámicas de Subcategorías Segmentadas en Línea Horizontal con Íconos -->
            <?php if ($category_id > 0): ?>
            <div class="category-tab-container mb-4">
                <ul class="nav-segmented" id="category-tabs">
                    <?php 
                    // Obtener pestañas: toda la línea de ascendientes de la categoría activa + sus hijos inmediatos
                    $all_tabs = [];
                    // Obtener ancestros (lineage)
                    $current_id = $category_id;
                    $lineage = [];
                    while ($current_id) {
                        $stmt_lin = $pdo->prepare("SELECT id, name, parent_id, icon FROM ci_categories WHERE id = ?");
                        $stmt_lin->execute([$current_id]);
                        $c = $stmt_lin->fetch(PDO::FETCH_ASSOC);
                        if ($c) {
                            array_unshift($lineage, ['id' => $c['id'], 'name' => $c['name'], 'icon' => $c['icon']]);
                            $current_id = $c['parent_id'];
                        } else {
                            break;
                        }
                    }
                    $all_tabs = $lineage;

                    // Obtener hijos inmediatos de la categoría activa
                    $stmt_subs = $pdo->prepare("SELECT id, name, icon FROM ci_categories WHERE parent_id = ? ORDER BY name ASC");
                    $stmt_subs->execute([$category_id]);
                    foreach($stmt_subs->fetchAll(PDO::FETCH_ASSOC) as $sub) {
                        $all_tabs[] = ['id' => $sub['id'], 'name' => $sub['name'], 'icon' => $sub['icon']];
                    }

                    // Obtener conteos precisos para cada pestaña desde la base de datos
                    $cat_counts = [];
                    foreach ($all_tabs as $tab) {
                        $tid = $tab['id'];
                        $tname = $tab['name'];
                        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM ci_instances WHERE category_id = ?");
                        $stmt_count->execute([$tid]);
                        $cat_counts[$tname] = $stmt_count->fetchColumn();
                    }

                    // Conteos para "Todos"
                    $in_placeholders = implode(',', array_fill(0, count($descendant_ids), '?'));
                    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM ci_instances WHERE category_id IN ($in_placeholders)");
                    $stmt_total->execute($descendant_ids);
                    $total_count = $stmt_total->fetchColumn();
                    
                    // Pestaña "Todos"
                    $todos_active = ($category_id == 0 || $show_all);
                    ?>
                    <li class="nav-segmented-item">
                        <a class="nav-segmented-link <?php echo $todos_active ? 'active' : ''; ?>" href="ci_list.php<?php echo '?category_id=' . $category_id . '&show_all=1'; ?>">
                            <i class="fas fa-list mr-1.5"></i> Todos <span class="badge badge-light text-dark ml-2"><?php echo $total_count; ?></span>
                        </a>
                    </li>
                    <?php
                    foreach ($all_tabs as $tab): 
                        $cname = $tab['name'];
                        $cid = $tab['id'];
                        $cicon = !empty($tab['icon']) ? $tab['icon'] : 'fa-cube';
                        $count = $cat_counts[$cname] ?? 0;
                        $is_active = (!$show_all && $category_id == $cid);
                        $tab_url = 'ci_list.php?category_id=' . $cid;
                        if ($cid == $category_id) {
                            $tab_url .= '&show_all=0';
                        }
                    ?>
                    <li class="nav-segmented-divider"><i class="fas fa-chevron-right"></i></li>
                    <li class="nav-segmented-item">
                        <a class="nav-segmented-link <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo $tab_url; ?>" title="Filtrar por <?php echo htmlspecialchars($cname); ?>">
                            <i class="fas <?php echo htmlspecialchars($cicon); ?> mr-1.5"></i> <?php echo htmlspecialchars($cname); ?> <span class="badge badge-light text-dark ml-2"><?php echo $count; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Estilos personalizados para el listado e interactividad Premium -->
            <style>
            .detail-card-label {
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #6c757d;
                margin-bottom: 0.2rem;
            }
            .detail-card-value {
                font-size: 0.95rem;
                color: #212529;
                font-weight: 500;
            }
            .detail-attr-box {
                background-color: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                transition: all 0.2s ease;
            }
            .detail-attr-box:hover {
                background-color: #ffffff;
                box-shadow: 0 4px 10px rgba(0,0,0,0.06);
                border-color: #007bff;
            }
            .sortable-header {
                user-select: none;
                transition: background-color 0.2s ease;
            }
            .sortable-header:hover {
                background-color: rgba(0, 123, 255, 0.05) !important;
            }
            .sortable-header i {
                font-size: 0.85em;
                transition: color 0.2s ease;
            }
            /* Dark mode support */
            .dark-mode .detail-attr-box {
                background-color: #343a40;
                border-color: #4b545c;
                color: #fff;
            }
            .dark-mode .detail-attr-box:hover {
                background-color: #3f474e;
                border-color: #007bff;
            }
            .dark-mode .detail-card-value {
                color: #e9ecef;
            }
            .dark-mode .detail-card-label {
                color: #adb5bd;
            }
            .dark-mode .sortable-header:hover {
                background-color: rgba(255, 255, 255, 0.05) !important;
            }

            /* Segments for dynamic category breadcrumbs */
            .category-tab-container {
                background: #fdfdfd;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 12px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            .dark-mode .category-tab-container {
                background: #1e2229;
                border-color: #2d3748;
            }
            .nav-segmented {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
                padding: 0;
                margin: 0;
                list-style: none;
            }
            .nav-segmented-item {
                display: flex;
                align-items: center;
            }
            .nav-segmented-link {
                display: inline-flex;
                align-items: center;
                padding: 8px 16px;
                background-color: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                color: #475569;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.2s ease;
                text-decoration: none !important;
            }
            .nav-segmented-link:hover {
                background-color: #f1f5f9;
                color: #0f172a;
                border-color: #94a3b8;
            }
            .nav-segmented-link.active {
                background-color: #007bff;
                color: #ffffff !important;
                border-color: #007bff;
                box-shadow: 0 4px 6px -1px rgba(0, 123, 255, 0.3);
            }
            .nav-segmented-divider {
                color: #94a3b8;
                font-size: 1rem;
                margin: 0 4px;
            }
            .dark-mode .nav-segmented-link {
                background-color: #2d3748;
                border-color: #4a5568;
                color: #e2e8f0;
            }
            .dark-mode .nav-segmented-link:hover {
                background-color: #4a5568;
                color: #ffffff;
                border-color: #718096;
            }
            .dark-mode .nav-segmented-link.active {
                background-color: #007bff;
                color: #ffffff !important;
                border-color: #007bff;
            }
            
            /* Modal Segmented Boxed Tabs */
            .modal-nav-pills .nav-link {
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                background-color: #ffffff;
                color: #475569;
                font-weight: 600;
                padding: 8px 16px;
                margin-bottom: 5px;
                transition: all 0.2s ease;
            }
            .modal-nav-pills .nav-link:hover {
                background-color: #f1f5f9;
                border-color: #94a3b8;
                color: #0f172a;
            }
            .modal-nav-pills .nav-link.active {
                background-color: #007bff !important;
                color: #ffffff !important;
                border-color: #007bff;
                box-shadow: 0 4px 6px -1px rgba(0, 123, 255, 0.3);
            }
            .dark-mode .modal-nav-pills .nav-link {
                background-color: #2d3748;
                border-color: #4a5568;
                color: #e2e8f0;
            }
            .dark-mode .modal-nav-pills .nav-link:hover {
                background-color: #4a5568;
                border-color: #718096;
                color: #ffffff;
            }
            .dark-mode .modal-nav-pills .nav-link.active {
                background-color: #007bff !important;
                color: #ffffff !important;
                border-color: #007bff;
            }

            /* Fullscreen Modal Support */
            .modal-fullscreen .modal-dialog {
                max-width: 100vw !important;
                width: 100vw !important;
                margin: 0 !important;
                height: 100vh !important;
            }
            .modal-fullscreen .modal-content {
                height: 100vh !important;
                border-radius: 0 !important;
            }
            .modal-fullscreen .modal-body {
                height: calc(100vh - 56px) !important;
                overflow-y: auto !important;
            }

            /* Premium detail card, gallery, and map styling */
            .premium-card { border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important; }
            .gallery-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; }
            .gallery-item { position: relative; border-radius: 8px; overflow: hidden; height: 90px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #fff; transition: transform 0.2s; }
            .gallery-item:hover { transform: scale(1.03); z-index: 10; cursor: pointer; }
            .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
            
            .btn-floating-delete { position: absolute; top: 3px; right: 3px; opacity: 0; transition: opacity 0.2s; padding: 2px 5px; font-size: 10px; }
            .gallery-item:hover .btn-floating-delete { opacity: 1; }
            
            .map-wrapper { height: 350px; border-radius: 12px; overflow: hidden; background: #e9ecef; }

            /* Match NovaIOPS table and control sizes */
            #ci-table,
            #tbl-zabbix-modal-triggers,
            #tbl-zabbix-modal-items {
                font-size: 0.75rem !important;
            }
            #ci-table th, 
            #ci-table td,
            #tbl-zabbix-modal-triggers th,
            #tbl-zabbix-modal-triggers td,
            #tbl-zabbix-modal-items th,
            #tbl-zabbix-modal-items td {
                padding: 4px 6px !important;
                vertical-align: middle !important;
                line-height: 1.25 !important;
            }
            #ci-table .badge {
                font-size: 0.68rem !important;
                padding: 2px 4px !important;
            }
            #ci-table a {
                font-size: 0.75rem !important;
            }
            #ci-table small {
                font-size: 0.68rem !important;
                display: block;
                margin-top: 1px;
            }
            /* Make the search controls compact to match NovaIOPS filters */
            #ci-search-input {
                height: 30px !important;
                font-size: 0.75rem !important;
                padding: 4px 8px !important;
            }
            #search-addon {
                height: 30px !important;
                font-size: 0.75rem !important;
                padding: 4px 8px !important;
                display: flex;
                align-items: center;
            }
            #ci-search-clear-btn {
                height: 30px !important;
                font-size: 0.75rem !important;
                padding: 4px 10px !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #ci-counter-label {
                font-size: 0.75rem !important;
                padding: 4px 10px !important;
                line-height: 1.5 !important;
            }
            /* Table header style tweaks */
            #ci-table thead th {
                font-weight: 600 !important;
                border-bottom: 2px solid #cbd5e1 !important;
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
            }
            .dark-mode #ci-table thead th {
                border-bottom: 2px solid #475569 !important;
                background-color: #334155 !important;
                color: #f8fafc !important;
            }
            .dark-mode #ci-table tbody tr {
                background-color: #1e293b !important;
                color: #f8fafc !important;
            }
            /* Row Hover transition */
            #ci-table tbody tr {
                transition: background-color 0.15s ease;
            }
            </style>

            <!-- Buscador general arriba de la tabla -->
            <div class="row mb-3 align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="input-group shadow-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0" id="search-addon" style="border-radius: 6px 0 0 6px;"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="ci-search-input" class="form-control border-left-0 pl-0" placeholder="Buscar por Nombre, Sigla o IP Address..." aria-describedby="search-addon" style="border-radius: 0; height: 30px;">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="ci-search-clear-btn" style="border-radius: 0 6px 6px 0; height: 30px;" title="Limpiar búsqueda"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-right">
                    <span class="badge badge-light border text-dark font-weight-bold px-3 py-1" id="ci-counter-label" style="font-size: 0.75rem;">
                        Mostrando <?php echo count($instances); ?> de <?php echo count($instances); ?> CIs
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle" id="ci-table">
                    <thead class="bg-light text-dark">
                        <tr>
                            <!-- Columnas en el orden requerido: código único / nombre / ipaddress / clase / sigla -->
                            <th class="sortable-header" data-column="unique" style="cursor: pointer; width: 120px;">Código Único <i class="fas fa-sort ml-1 text-muted"></i></th>
                            <th class="sortable-header" data-column="hostname" style="cursor: pointer;">CI / Nombre <i class="fas fa-sort ml-1 text-muted"></i></th>
                            <th class="sortable-header" data-column="ip" style="cursor: pointer; width: 130px;">IP Address <i class="fas fa-sort ml-1 text-muted"></i></th>
                            <th class="sortable-header" data-column="class" style="cursor: pointer;">Clase <i class="fas fa-sort ml-1 text-muted"></i></th>
                            <th class="sortable-header" data-column="sigla" style="cursor: pointer; width: 100px;">Sigla <i class="fas fa-sort ml-1 text-muted"></i></th>
                            
                            <?php if ($category_id > 0): ?>
                                <?php foreach ($table_cols as $key => $label): ?>
                                    <?php 
                                    // No duplicar columnas que ya mostramos de manera estándar
                                    if (in_array(strtolower($key), ['nombre', 'sigla', 'ci_unique'])) continue;
                                    ?>
                                    <th><?php echo htmlspecialchars($label); ?></th>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <th>Atributos Extendidos</th>
                            <?php endif; ?>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instances as $inst): 
                            $attrs = json_decode($inst['attributes_json'], true);
                            if (!is_array($attrs)) $attrs = [];
                            $attrCount = count($attrs);
                        ?>
                        <tr class="ci-row animate__animated animate__fadeIn" 
                            data-id="<?php echo $inst['id']; ?>"
                            data-unique="<?php echo htmlspecialchars($inst['ci_unique'] ?? ''); ?>"
                            data-hostname="<?php echo htmlspecialchars($inst['hostname'] ?? ''); ?>"
                            data-parent-name="<?php echo htmlspecialchars($inst['parent_ci_name'] ?? ''); ?>"
                            data-ip="<?php echo htmlspecialchars($inst['ip_address'] ?? ''); ?>"
                            data-category="<?php echo htmlspecialchars($inst['category_name'] ?? ''); ?>"
                            data-sigla="<?php echo htmlspecialchars($inst['sigla'] ?? ''); ?>">
                            
                            <!-- Celda Código Único -->
                            <td><span class="badge badge-dark font-weight-bold"><?php echo htmlspecialchars($inst['ci_unique'] ?? 'SND-XXXXXXXXXX'); ?></span></td>
                            
                            <!-- Celda CI / Nombre -->
                            <td>
                                <div><a href="javascript:void(0)" onclick="viewCIDetails(<?php echo $inst['id']; ?>)" class="font-weight-bold text-primary" title="Ver Detalles Estructurados"><i class="fas fa-search-plus mr-1"></i><?php echo htmlspecialchars($inst['hostname']); ?></a></div>
                                <small class="text-muted"><?php echo htmlspecialchars($inst['description'] ?? 'Sin descripción'); ?></small>
                            </td>

                            <!-- Celda IP Address -->
                            <td><?php echo htmlspecialchars($inst['ip_address']); ?></td>

                            <!-- Celda Clase -->
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($inst['category_name']); ?></span></td>

                            <!-- Celda Sigla -->
                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($inst['sigla'] ?? '-'); ?></span></td>
                            
                            <!-- Celdas Dinámicas -->
                            <?php if ($category_id > 0): ?>
                                <?php foreach ($table_cols as $key => $label): ?>
                                    <?php 
                                    if (in_array(strtolower($key), ['nombre', 'sigla', 'ci_unique'])) continue;
                                    ?>
                                    <td>
                                        <?php 
                                            $val = $attrs[$key] ?? null;
                                            if ($val === null || $val === '') {
                                                echo '<span class="text-muted font-italic">-</span>';
                                            } else {
                                                $propType = $schema_properties[$key]['type'] ?? '';
                                                if ($propType === 'boolean') {
                                                    if ($val == 1 || $val === '1' || $val === true) {
                                                        echo '<span class="badge badge-success">Sí</span>';
                                                    } else {
                                                        echo '<span class="badge badge-secondary">No</span>';
                                                    }
                                                } elseif ($propType === 'image') {
                                                    echo '<a href="' . htmlspecialchars($val) . '" target="_blank" class="d-inline-block shadow-sm rounded overflow-hidden border">';
                                                    echo '<img src="' . htmlspecialchars($val) . '" style="max-height: 40px; max-width: 80px; display: block; object-fit: contain;">';
                                                    echo '</a>';
                                                } elseif ($propType === 'multiselect' || is_array($val)) {
                                                    $arr = is_array($val) ? $val : explode(',', $val);
                                                    foreach ($arr as $item) {
                                                        $item = trim($item);
                                                        if ($item !== '') {
                                                            echo '<span class="badge badge-dark mr-1">' . htmlspecialchars($item) . '</span>';
                                                        }
                                                    }
                                                } else {
                                                    echo htmlspecialchars($val);
                                                }
                                            }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td>
                                    <button class="btn btn-xs btn-outline-primary shadow-sm" onclick="viewCIDetails(<?php echo $inst['id']; ?>)">
                                        Ver <?php echo $attrCount; ?> atributos
                                    </button>
                                </td>
                            <?php endif; ?>

                            <!-- Celda Registro -->
                            <td>
                                <small class="d-block" title="Fecha de Creación"><i class="far fa-calendar-alt text-muted"></i> <?php echo date('d/m/Y H:i', strtotime($inst['created_at'])); ?></small>
                                <small class="d-block mt-1" title="Creado por"><i class="far fa-user text-muted"></i> <?php echo htmlspecialchars($inst['creator_name'] ?? 'Desconocido'); ?></small>
                                <?php if($inst['source'] == 'zabbix'): ?>
                                    <span class="badge badge-danger mt-1" title="Zabbix Host ID: <?php echo htmlspecialchars($inst['zabbix_host_id']); ?>"><i class="fas fa-server"></i> Zabbix (ID: <?php echo htmlspecialchars($inst['zabbix_host_id']); ?>)</span>
                                <?php endif; ?>
                            </td>

                            <!-- Celda Acciones -->
                            <td>
                                <div class="btn-group shadow-sm">
                                    <a href="ci_business_view.php?ci_id=<?php echo $inst['id']; ?>" class="btn btn-sm btn-info" title="Ver Relaciones (Grafo)"><i class="fas fa-project-diagram"></i></a>
                                    <a href="ci_builder.php?id=<?php echo $inst['id']; ?>" class="btn btn-sm btn-primary" title="Editar / Actualizar"><i class="fas fa-edit"></i></a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCI(<?php echo $inst['id']; ?>)" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($instances)): ?>
                        <tr>
                            <td colspan="100" class="text-center py-5 text-muted">No se han creado CIs en esta nueva estructura. Utiliza el botón "Nuevo CI".</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal rediseñado a tamaño Extra Grande (modal-xl) con diseño Técnico/Gerencial -->
<div class="modal fade modal-fullscreen" id="attrModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-gradient-primary text-white border-bottom-0 py-3 d-flex align-items-center">
                <h5 class="modal-title font-weight-bold text-white mb-0" id="attrModalTitle">
                    <i class="fas fa-server mr-2"></i> Detalles del CI
                </h5>
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-light mr-2 border-0" id="btn-maximize-modal" title="Pantalla Completa" style="opacity: 0.8; outline: none; background: transparent; color: white;">
                        <i class="fas fa-compress"></i>
                    </button>
                    <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; opacity: 0.8; outline: none; line-height: 1;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 bg-light" id="attrModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-2 text-muted">Cargando ficha técnica...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function viewCIDetails(id) {
    $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i> Cargando detalles...');
    $('#attrModalBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 text-muted">Obteniendo expediente técnico...</p>
        </div>
    `);
    $('#attrModal').modal('show');
    
    $.get('api_ci.php?action=get_ci_details&id=' + id, function(res) {
        if (res.success) {
            let ci = res.data.ci;
            $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i>' + ci.hostname + ' <span class="badge badge-info ml-2 font-weight-normal text-white">' + ci.category_name + '</span>');
            
            // Setup Prev/Next Navigation
            let visibleIds = $('.ci-row:visible').map(function() { return $(this).data('id'); }).get();
            let currentIndex = visibleIds.indexOf(id);
            let prevId = currentIndex > 0 ? visibleIds[currentIndex - 1] : null;
            let nextId = currentIndex < visibleIds.length - 1 ? visibleIds[currentIndex + 1] : null;

            let navHtml = `
                <button type="button" class="btn btn-sm btn-outline-light mr-2 font-weight-bold px-3" id="btn-prev-ci" style="border-radius: 20px;" ${prevId ? '' : 'disabled'}>
                    <i class="fas fa-chevron-left mr-1"></i> Anterior
                </button>
                <button type="button" class="btn btn-sm btn-outline-light mr-2 font-weight-bold px-3" id="btn-next-ci" style="border-radius: 20px;" ${nextId ? '' : 'disabled'}>
                    Siguiente <i class="fas fa-chevron-right ml-1"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-light mr-2 border-0" id="btn-maximize-modal" title="Pantalla Completa" style="opacity: 0.8; outline: none; background: transparent; color: white;">
                    <i class="fas fa-compress"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger text-white font-weight-bold px-4" data-dismiss="modal" style="border-radius: 20px;">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            `;
            $('#attrModal .ml-auto').html(navHtml);
            
            // Re-bind navigation events
            $('#btn-prev-ci').off('click').on('click', function() { if (prevId) viewCIDetails(prevId); });
            $('#btn-next-ci').off('click').on('click', function() { if (nextId) viewCIDetails(nextId); });

            // Extract all properties from lineage schema
            let allProps = {};
            res.data.lineage.forEach(cat => {
                try {
                    let schema = JSON.parse(cat.schema_json);
                    if (schema && schema.properties) {
                        for (let k in schema.properties) allProps[k] = schema.properties[k];
                    }
                } catch(e) {}
            });
            
            let attrs = {};
            try { attrs = JSON.parse(ci.attributes_json); } catch(e) {}
            
            let groups = {};
            for(let key in allProps) {
                let prop = allProps[key];
                let groupName = prop.group || 'Atributos';
                if(!groups[groupName]) groups[groupName] = {};
                groups[groupName][key] = prop;
            }
            
            let groupKeys = Object.keys(groups).sort();
            
            // Base Attributes UI
            let baseFieldsHtml = `
                <div class="row pt-2">
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Código Único CI</div>
                        <div class="detail-card-value font-weight-bold text-monospace"><span class="badge badge-dark px-2.5 py-1.5" style="font-size: 0.85rem;">${ci.ci_unique || 'SND-XXXXXXXXXX'}</span></div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Nombre / Hostname</div>
                        <div class="detail-card-value font-weight-bold text-dark" style="font-size: 0.95rem;">${ci.hostname}</div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Dirección IP</div>
                        <div class="detail-card-value font-weight-bold text-primary" style="font-size: 0.95rem;"><i class="fas fa-network-wired mr-1.5"></i>${ci.ip_address || '<span class="text-muted font-italic">N/D</span>'}</div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Categoría</div>
                        <div class="detail-card-value"><span class="badge badge-info px-2.5 py-1.5 text-uppercase" style="font-size: 0.8rem;">${ci.category_name}</span></div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Sigla / Código</div>
                        <div class="detail-card-value"><span class="badge badge-secondary px-2.5 py-1.5" style="font-size: 0.8rem;">${ci.sigla || '-'}</span></div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Estado</div>
                        <div class="detail-card-value"><span class="badge badge-success px-2.5 py-1.5" style="font-size: 0.8rem;"><i class="fas fa-check-circle mr-1"></i>${ci.status || 'Activo'}</span></div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Origen</div>
                        <div class="detail-card-value">${ci.source === 'zabbix' ? '<span class="badge badge-danger px-2.5 py-1.5" style="font-size: 0.8rem;"><i class="fas fa-server mr-1"></i> Zabbix</span>' : '<span class="badge badge-primary px-2.5 py-1.5" style="font-size: 0.8rem;"><i class="fas fa-keyboard mr-1"></i> Manual</span>'}</div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Fecha Registro</div>
                        <div class="detail-card-value text-muted" style="font-size: 0.9rem;"><i class="far fa-calendar-alt mr-1"></i>${ci.created_at ? new Date(ci.created_at).toLocaleString('es-ES') : '-'}</div>
                    </div>
                    <div class="col-md-4 mb-3 pb-2 border-bottom">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Registrado Por</div>
                        <div class="detail-card-value text-muted" style="font-size: 0.9rem;"><i class="far fa-user mr-1"></i>${ci.creator_name || 'Desconocido'}</div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Descripción</div>
                        <div class="p-3 bg-light rounded text-muted" style="font-size: 0.9rem; border: 1px solid #e9ecef;">${ci.description || 'Sin descripción registrada.'}</div>
                    </div>
                </div>
            `;

            let tabsHtml = '<ul class="nav modal-nav-pills mb-3 border-bottom pb-2" role="tablist" id="modalDetailTabs">';
            let contentHtml = '<div class="tab-content" id="modalDetailTabsContent">';
            
            tabsHtml += `
                <li class="nav-item mr-2">
                    <a class="nav-link font-weight-bold active px-3 py-2" data-toggle="tab" href="#view-base-attrs" role="tab">
                        <i class="fas fa-info-circle mr-1.5 text-primary"></i> Atributos Base
                    </a>
                </li>`;
            
            contentHtml += `
                <div class="tab-pane fade show active" id="view-base-attrs" role="tabpanel">
                    ${baseFieldsHtml}
                </div>`;
            
            groupKeys.forEach((groupName, index) => {
                let safeId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-' + index;
                tabsHtml += `
                    <li class="nav-item mr-2">
                        <a class="nav-link font-weight-bold px-3 py-2" data-toggle="tab" href="#view-${safeId}" role="tab">
                            <i class="fas fa-cubes mr-1.5 text-info"></i> ${groupName}
                        </a>
                    </li>`;
                
                contentHtml += `<div class="tab-pane fade" id="view-${safeId}" role="tabpanel">`;
                contentHtml += `<div class="row pt-2">`;
                
                let props = groups[groupName] || {};
                for(let key in props) {
                    let prop = props[key];
                    let rawVal = attrs[key];
                    let val = '<span class="text-muted font-italic">N/D</span>';
                    
                    if (rawVal !== undefined && rawVal !== '') {
                        if (prop.type === 'boolean') {
                            val = (rawVal == 1 || rawVal === '1' || rawVal === true) ? '<span class="badge badge-success px-2.5 py-1.5"><i class="fas fa-check mr-1"></i>Sí</span>' : '<span class="badge badge-secondary px-2.5 py-1.5"><i class="fas fa-times mr-1"></i>No</span>';
                        } else if (prop.type === 'image') {
                            val = `<div class="my-1"><a href="${rawVal}" target="_blank" class="shadow-sm rounded"><img src="${rawVal}" style="max-height: 80px; border-radius: 4px;"></a></div>`;
                        } else if (prop.type === 'multiselect') {
                            let arr = Array.isArray(rawVal) ? rawVal : (typeof rawVal === 'string' ? rawVal.split(',') : []);
                            val = '';
                            arr.forEach(item => {
                                item = item.trim();
                                if (item) val += `<span class="badge badge-dark mr-1 mb-1 px-2 py-1">${item}</span>`;
                            });
                            if (!val) val = '<span class="text-muted font-italic">N/D</span>';
                        } else {
                            val = Array.isArray(rawVal) ? rawVal.join(', ') : rawVal;
                        }
                    }
                    
                    let label = prop.title || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                    contentHtml += `
                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded h-100 bg-white shadow-xs">
                                <div class="detail-card-label text-muted font-weight-bold mb-1.5" style="font-size:0.75rem; text-transform:uppercase;">${label}</div>
                                <div class="detail-card-value font-weight-bold text-dark" style="font-size:0.9rem;">${val}</div>
                            </div>
                        </div>`;
                }
                contentHtml += `</div></div>`;
            });

            // Thematic groups mapping
            let thematicTabs = {
                'Ubicación': [],
                'Personal / Contacto': [],
                'Facility': [],
                'Hardware / Infraestructura': [],
                'Servicios / Software': [],
                'Otros / Relacionados': []
            };

            function getThematicGroup(categoryName) {
                if (!categoryName) return 'Otros / Relacionados';
                let catLower = categoryName.toLowerCase();
                const groupMappings = {
                    'Ubicación': ['país', 'ciudad', 'datacenter', 'rack', 'ubicación', 'geografía', 'sector', 'edificio', 'localidad', 'área', 'area', 'cuarto', 'localidades'],
                    'Personal / Contacto': ['personal', 'soporte', 'propietario', 'contacto', 'proveedor', 'usuario'],
                    'Facility': ['facility', 'eléctrico', 'aire', 'climatización', 'energía', 'ups', 'pdu', 'batería', 'chiller', 'tablero'],
                    'Hardware / Infraestructura': ['servidor', 'storage', 'switch', 'router', 'firewall', 'chasis', 'blade', 'hardware', 'equipo', 'monitoreo', 'red'],
                    'Servicios / Software': ['servicio', 'software', 'sistema operativo', 'base de datos', 'aplicación', 'api', 'licencia', 'vlan']
                };
                for (let group in groupMappings) {
                    if (groupMappings[group].some(keyword => catLower.includes(keyword))) return group;
                }
                return 'Otros / Relacionados';
            }

            if (res.data.parent_chain && res.data.parent_chain.length > 0) {
                res.data.parent_chain.forEach(pci => {
                    thematicTabs[getThematicGroup(pci.category_name)].push({type: 'parent', ...pci});
                });
            }
            if (res.data.relations && res.data.relations.length > 0) {
                res.data.relations.forEach(r => {
                    thematicTabs[getThematicGroup(r.target_category_name)].push({
                        type: 'relation', 
                        id: r.target_id, 
                        hostname: r.target_name, 
                        category_name: r.target_category_name || 'Relación',
                        relation_type: r.relation_type
                    });
                });
            }

            Object.keys(thematicTabs).forEach((groupName) => {
                let items = thematicTabs[groupName];
                if (items.length === 0 && !['Ubicación', 'Personal / Contacto', 'Facility'].includes(groupName)) return;
                
                let safeGroupId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
                let iconClass = 'fa-link';
                if (groupName === 'Ubicación') iconClass = 'fa-map-marker-alt text-danger';
                else if (groupName === 'Personal / Contacto') iconClass = 'fa-users text-primary';
                else if (groupName === 'Facility') iconClass = 'fa-building text-warning';
                else if (groupName === 'Hardware / Infraestructura') iconClass = 'fa-server text-info';
                else if (groupName === 'Servicios / Software') iconClass = 'fa-laptop-code text-success';

                tabsHtml += `
                    <li class="nav-item mr-2">
                        <a class="nav-link font-weight-bold px-3 py-2" data-toggle="tab" href="#view-theme-${safeGroupId}" role="tab">
                            <i class="fas ${iconClass} mr-1.5"></i> ${groupName} (${items.length})
                        </a>
                    </li>`;
                
                contentHtml += `<div class="tab-pane fade" id="view-theme-${safeGroupId}" role="tabpanel">`;
                contentHtml += `<div class="row pt-2">`;
                
                if (items.length === 0) {
                    contentHtml += `
                        <div class="col-12 text-center py-4 text-muted bg-light rounded border m-2" style="border-style: dashed !important;">
                            <i class="fas fa-info-circle mr-1 text-warning"></i> N/A (No seleccionado / asociado)
                        </div>`;
                } else {
                    items.forEach((item) => {
                        let relBadge = item.type === 'relation' ? ` <span class="badge badge-light border text-monospace ml-1.5">${item.relation_type || 'Relación'}</span>` : '';
                        contentHtml += `
                            <div class="col-md-6 mb-3">
                                <div class="p-3 border rounded h-100 bg-white shadow-xs">
                                    <div class="detail-card-label text-muted font-weight-bold mb-1.5" style="font-size:0.75rem; text-transform:uppercase;">${item.category_name}</div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="font-weight-bold text-primary" style="font-size: 0.95rem;">${item.hostname}${relBadge}</span>
                                        <a href="javascript:void(0)" onclick="viewCIDetails(${item.id})" class="text-muted" title="Ver CI"><i class="fas fa-search-plus"></i></a>
                                    </div>
                                </div>
                            </div>`;
                    });
                }
                contentHtml += `</div></div>`;
            });
            
            // Class Hierarchy
            tabsHtml += `
                <li class="nav-item mr-2">
                    <a class="nav-link font-weight-bold px-3 py-2" data-toggle="tab" href="#view-hierarchy" role="tab">
                        <i class="fas fa-sitemap mr-1.5 text-secondary"></i> Jerarquía
                    </a>
                </li>`;
            
            contentHtml += `<div class="tab-pane fade" id="view-hierarchy" role="tabpanel"><div class="pt-2"><div class="card border p-4 shadow-xs">`;
            res.data.lineage.forEach((cat, idx) => {
                let isLast = idx === res.data.lineage.length - 1;
                contentHtml += `
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm font-weight-bold" style="width:32px; height:32px; min-width:32px;">${idx + 1}</div>
                        <div class="ml-3">
                            <span class="${isLast ? 'text-primary font-weight-bold' : 'text-muted'}" style="font-size: 1.05rem;">${cat.name}</span>
                        </div>
                    </div>
                    ${!isLast ? '<div class="border-left ml-3 my-1" style="height: 20px; border-width: 2px !important; border-color: #dee2e6 !important;"></div>' : ''}
                `;
            });
            contentHtml += `</div></div></div>`;
            
            // Zabbix monitoring tab
            if (ci.zabbix_host_id) {
                tabsHtml += `
                    <li class="nav-item mr-2">
                        <a class="nav-link font-weight-bold px-3 py-2" data-toggle="tab" href="#view-zabbix-monitoring" role="tab" id="tab-zabbix-monit">
                            <i class="fas fa-heartbeat text-danger"></i> Monitoreo Real-time
                        </a>
                    </li>`;
                
                contentHtml += `
                    <div class="tab-pane fade" id="view-zabbix-monitoring" role="tabpanel">
                        <div class="pt-3">
                            <div id="zabbix-modal-loading" class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                                <h5>Consultando métricas en Zabbix...</h5>
                            </div>
                            <div id="zabbix-modal-content" style="display:none;">
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-exclamation-triangle text-danger mr-2"></i>Alarmas Activas</h6>
                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm table-striped table-bordered mb-0" id="tbl-zabbix-modal-triggers" style="font-size: 0.8rem;">
                                                <thead class="thead-dark">
                                                    <tr>
                                                        <th>Descripción</th>
                                                        <th style="width: 30%">Severidad</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td colspan="2" class="text-center py-3 text-muted">Cargando...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-3">
                                        <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-chart-line text-info mr-2"></i>Últimas Métricas</h6>
                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm table-striped table-bordered mb-0" id="tbl-zabbix-modal-items" style="font-size: 0.8rem;">
                                                <thead class="thead-dark">
                                                    <tr>
                                                        <th>Métrica</th>
                                                        <th>Valor</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td colspan="2" class="text-center py-3 text-muted">Cargando...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
            }
            
            tabsHtml += '</ul>';
            contentHtml += '</div>';

            let googlemapsLink = ci.googlemaps || attrs.googlemaps || attrs.google_maps || attrs.mapa || '';
            let imagesHtml = '';
            if (res.data.images && res.data.images.length > 0) {
                imagesHtml = `
                    <div class="gallery-container">
                        ${res.data.images.map(img => `
                            <div class="gallery-item" onclick="window.open('${img.filepath}', '_blank')">
                                <img src="${img.filepath}" alt="Foto">
                                <button class="btn btn-danger btn-xs btn-floating-delete delete-ci-image-btn" data-image-id="${img.id}" onclick="event.stopPropagation()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                imagesHtml = `
                    <div class="text-center py-4 text-muted w-100 opacity-50">
                        <i class="fas fa-image fa-2x mb-2"></i>
                        <p class="small mb-0">Sin fotos adjuntas</p>
                    </div>
                `;
            }

            let rightColHtml = `
                <div class="card premium-card mb-4 border shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark" style="font-size:1.05rem;"><i class="fas fa-camera mr-2 text-primary"></i> Galería de Fotos</h5>
                        <button class="btn btn-xs btn-outline-primary" onclick="document.getElementById('ci-image-input').click()"><i class="fas fa-plus mr-1"></i>Subir</button>
                    </div>
                    <div class="card-body p-4">
                        <div id="ci-image-gallery">${imagesHtml}</div>
                        <form id="ci-image-upload-form" class="d-none">
                            <input type="file" id="ci-image-input" name="image" accept="image/*">
                        </form>
                    </div>
                </div>
                <div id="ci-map-section" style="${googlemapsLink ? 'display:block;' : 'display:none;'}" class="card premium-card border mb-4 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 font-weight-bold text-dark" style="font-size:1.05rem;"><i class="fas fa-map-marker-alt mr-2 text-danger"></i> Geolocalización</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="ci-map-container" class="map-wrapper"></div>
                    </div>
                </div>
            `;

            let mainHtml = `
                <div class="container-fluid p-4 text-left">
                    <div class="row align-items-center mb-4 border-bottom pb-3">
                        <div class="col">
                            <h2 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-microchip mr-2"></i> Expediente Técnico: ${ci.hostname}</h2>
                            <p class="text-muted small mb-0">Detalles de CI en la categoría <strong>${ci.category_name}</strong></p>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group shadow-sm">
                                <a href="ci_builder.php?id=${ci.id}" class="btn btn-primary font-weight-bold px-3"><i class="fas fa-edit mr-1.5"></i> Editar CI</a>
                                <button class="btn btn-danger font-weight-bold px-3" onclick="deleteCI(${ci.id})"><i class="fas fa-trash-alt mr-1.5"></i> Eliminar</button>
                                <button class="btn btn-secondary font-weight-bold px-3" data-dismiss="modal"><i class="fas fa-times mr-1.5"></i> Cerrar</button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <div class="card border shadow-sm h-100" style="border-radius: 12px;">
                                <div class="card-body">
                                    ${tabsHtml}
                                    ${contentHtml}
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            ${rightColHtml}
                        </div>
                    </div>
                </div>
            `;
            
            $('#attrModalBody').html(mainHtml);
            updateCIGoogleMapsView(googlemapsLink);

            // Fetch Zabbix details when monitoring tab is shown
            if (ci.zabbix_host_id) {
                $(document).off('shown.bs.tab', '#tab-zabbix-monit');
                $(document).on('shown.bs.tab', '#tab-zabbix-monit', function() {
                    $('#zabbix-modal-loading').show();
                    $('#zabbix-modal-content').hide();
                    
                    $.getJSON('informes/process_alcance.php', { action: 'get_host_items_triggers', hostid: ci.zabbix_host_id }, function(resp) {
                        $('#zabbix-modal-loading').hide();
                        if (!resp.success) {
                            $('#tbl-zabbix-modal-triggers tbody').html('<tr><td colspan="2" class="text-center text-danger">Error al consultar datos</td></tr>');
                            $('#tbl-zabbix-modal-items tbody').html('<tr><td colspan="2" class="text-center text-danger">Error al consultar datos</td></tr>');
                            $('#zabbix-modal-content').show();
                            return;
                        }

                        const getSeverityBadgeLocal = (priority) => {
                            const p = parseInt(priority);
                            const severities = {
                                0: { name: 'No clasificado', class: 'badge-secondary' },
                                1: { name: 'Información',   class: 'badge-info' },
                                2: { name: 'Advertencia',   class: 'badge-warning' },
                                3: { name: 'Promedio',      class: 'badge-primary' },
                                4: { name: 'Alta',          class: 'badge-danger' },
                                5: { name: 'Desastre',      class: 'badge-dark' }
                            };
                            const sev = severities[p] || { name: 'Desconocida', class: 'badge-secondary' };
                            return `<span class="badge ${sev.class} px-2 py-1 font-weight-bold text-uppercase" style="font-size: 0.75rem">${sev.name}</span>`;
                        };

                        let trigHtml = '';
                        if (resp.triggers && resp.triggers.length > 0) {
                            resp.triggers.forEach(t => {
                                trigHtml += `
                                    <tr>
                                        <td>${t.description}</td>
                                        <td class="text-center">${getSeverityBadgeLocal(t.priority)}</td>
                                    </tr>`;
                            });
                        } else {
                            trigHtml = '<tr><td colspan="2" class="text-center py-3 text-success"><i class="fas fa-check-circle mr-1"></i> Sin alarmas activas</td></tr>';
                        }
                        $('#tbl-zabbix-modal-triggers tbody').html(trigHtml);
                        
                        let itemHtml = '';
                        if (resp.items && resp.items.length > 0) {
                            resp.items.slice(0, 30).forEach(it => {
                                let val = it.lastvalue !== undefined ? `${it.lastvalue} ${it.units || ''}` : 'N/A';
                                itemHtml += `
                                    <tr>
                                        <td class="font-weight-bold">${it.name}</td>
                                        <td class="text-primary font-weight-bold text-monospace">${val}</td>
                                    </tr>`;
                            });
                        } else {
                            itemHtml = '<tr><td colspan="2" class="text-center py-3 text-muted">Sin métricas registradas</td></tr>';
                        }
                        $('#tbl-zabbix-modal-items tbody').html(itemHtml);
                        
                        $('#zabbix-modal-content').fadeIn();
                    }).fail(function() {
                        $('#zabbix-modal-loading').hide();
                        $('#tbl-zabbix-modal-triggers tbody').html('<tr><td colspan="2" class="text-center text-danger">Error de comunicación</td></tr>');
                        $('#tbl-zabbix-modal-items tbody').html('<tr><td colspan="2" class="text-center text-danger">Error de comunicación</td></tr>');
                        $('#zabbix-modal-content').show();
                    });
                });
            }

            // Photo uploading
            $(document).off('change', '#ci-image-input');
            $(document).on('change', '#ci-image-input', function() {
                if (!this.files.length) return;
                const fd = new FormData();
                fd.append('image', this.files[0]);
                fd.append('table', 'ci_instances');
                fd.append('id', ci.id);
                
                Swal.fire({ title: 'Subiendo imagen...', didOpen: () => Swal.showLoading() });
                fetch('api_upload_image.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Éxito', 'Imagen subida correctamente', 'success').then(() => {
                                viewCIDetails(ci.id);
                            });
                        } else {
                            Swal.fire('Error', data.error || 'Error al subir la imagen', 'error');
                        }
                    });
            });

            // Photo deletion
            $(document).off('click', '.delete-ci-image-btn');
            $(document).on('click', '.delete-ci-image-btn', function(e) {
                e.stopPropagation();
                const imageId = $(this).data('image-id');
                Swal.fire({
                    title: '¿Eliminar fotografía?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (res.isConfirmed) {
                        const fd = new FormData();
                        fd.append('action', 'delete_image');
                        fd.append('id', imageId);
                        fetch('api_action.php', { method: 'POST', body: fd }).then(r => r.json()).then(js => {
                            if (js.success) {
                                Swal.fire('Éxito', 'Imagen eliminada', 'success').then(() => {
                                    viewCIDetails(ci.id);
                                });
                            } else {
                                Swal.fire('Error', js.error || 'No se pudo eliminar la imagen', 'error');
                            }
                        });
                    }
                });
            });

        } else {
            $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>' + res.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>Error al consultar el endpoint api_ci.php.</div>');
    });
}

function updateCIGoogleMapsView(link) {
    const sec = document.getElementById('ci-map-section');
    const cnt = document.getElementById('ci-map-container');
    if (!sec || !cnt) return;
    if (!link || link.trim() === "") { sec.style.display = 'none'; return; }
    sec.style.display = 'block';
    if (link.includes('<iframe')) {
        cnt.innerHTML = link.replace(/width="\d+"/, 'width="100%"').replace(/height="\d+"/, 'height="350"');
    } else if (link.includes('maps.app.goo.gl')) {
        cnt.innerHTML = `<div class="p-4 text-center"><a href="${link}" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-external-link-alt mr-1"></i>Abrir Mapa</a></div>`;
    } else {
        cnt.innerHTML = `<iframe width="100%" height="350" frameborder="0" src="${link}" allowfullscreen></iframe>`;
    }
}

function deleteCI(id) {
    Swal.fire({
        title: '¿Eliminar CI?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_ci.php', {action: 'delete_instance', id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', res.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

// Búsqueda y Ordenación en lado de cliente
let currentSortCol = null;
let currentSortAsc = true;

function getRowValue(row, colType) {
    let $row = $(row);
    switch(colType) {
        case 'unique': return ($row.data('unique') || '').toString();
        case 'hostname': return ($row.data('hostname') || '').toString();
        case 'dependency': return ($row.data('parent-name') || '').toString();
        case 'ip': return ($row.data('ip') || '').toString();
        case 'class': return ($row.data('category') || '').toString();
        case 'sigla': return ($row.data('sigla') || '').toString();
        default: return '';
    }
}

function compareIPs(ipA, ipB, asc) {
    if (!ipA && !ipB) return 0;
    if (!ipA) return asc ? 1 : -1;
    if (!ipB) return asc ? -1 : 1;
    
    function ipToNum(ip) {
        let parts = ip.split('.');
        if (parts.length !== 4) return 0;
        return parts.reduce((acc, octet) => (acc << 8) + parseInt(octet, 10), 0) >>> 0;
    }
    
    let numA = ipToNum(ipA);
    let numB = ipToNum(ipB);
    
    if (numA === numB) return 0;
    return asc ? (numA < numB ? -1 : 1) : (numA > numB ? -1 : 1);
}

function sortCITable(colType, asc) {
    let tbody = $('#ci-table tbody');
    let rows = tbody.find('.ci-row').get();
    
    rows.sort(function(a, b) {
        let valA = getRowValue(a, colType);
        let valB = getRowValue(b, colType);
        
        if (colType === 'ip') {
            return compareIPs(valA, valB, asc);
        }
        
        return asc ? valA.localeCompare(valB, 'es', {numeric: true}) : valB.localeCompare(valA, 'es', {numeric: true});
    });
    
    $.each(rows, function(index, row) {
        tbody.append(row);
    });
}

function filterCITable(term) {
    let visibleCount = 0;
    let totalCount = $('.ci-row').length;
    
    $('.ci-row').each(function() {
        let hostname = ($(this).data('hostname') || '').toString().toLowerCase();
        let sigla = ($(this).data('sigla') || '').toString().toLowerCase();
        let ip = ($(this).data('ip') || '').toString().toLowerCase();
        
        if (term === '' || hostname.includes(term) || sigla.includes(term) || ip.includes(term)) {
            $(this).show();
            visibleCount++;
        } else {
            $(this).hide();
        }
    });
    
    $('#ci-counter-label').text(`Mostrando ${visibleCount} de ${totalCount} CIs`);
    
    $('#no-results-row').remove();
    if (visibleCount === 0 && totalCount > 0) {
        $('#ci-table tbody').append(`
            <tr id="no-results-row">
                <td colspan="100" class="text-center py-5 text-muted">
                    <i class="fas fa-search fa-2x mb-3 text-muted"></i><br>
                    No se encontraron CIs que coincidan con la búsqueda.
                </td>
            </tr>
        `);
    }
}

$(document).ready(function() {
    // Manejador delegado para maximizar/restaurar modal
    $(document).on('click', '#btn-maximize-modal', function() {
        $('#attrModal').toggleClass('modal-fullscreen');
        let icon = $(this).find('i');
        if ($('#attrModal').hasClass('modal-fullscreen')) {
            icon.removeClass('fa-expand').addClass('fa-compress');
        } else {
            icon.removeClass('fa-compress').addClass('fa-expand');
        }
    });

    $('#attrModal').on('hidden.bs.modal', function () {
        $(this).addClass('modal-fullscreen');
    });

    // Configurar búsqueda
    $('#ci-search-input').on('keyup input', function() {
        let term = $(this).val().toLowerCase().trim();
        filterCITable(term);
    });

    $('#ci-search-clear-btn').click(function() {
        $('#ci-search-input').val('');
        filterCITable('');
    });

    // Configurar ordenación
    $('.sortable-header').click(function() {
        let colType = $(this).data('column');
        
        if (currentSortCol === colType) {
            currentSortAsc = !currentSortAsc;
        } else {
            currentSortCol = colType;
            currentSortAsc = true;
        }
        
        $('.sortable-header i').removeClass('fa-sort-up fa-sort-down text-dark').addClass('fa-sort text-muted');
        let icon = $(this).find('i');
        if (currentSortAsc) {
            icon.removeClass('fa-sort').addClass('fa-sort-up text-dark');
        } else {
            icon.removeClass('fa-sort').addClass('fa-sort-down text-dark');
        }
        
        sortCITable(colType, currentSortAsc);
    });
    // Auto-open CI details modal if show_ci_id param is provided
    const urlParams = new URLSearchParams(window.location.search);
    const showCiId = urlParams.get('show_ci_id');
    if (showCiId) {
        viewCIDetails(showCiId);
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
