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
            </style>

            <!-- Buscador general arriba de la tabla -->
            <div class="row mb-4 align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="input-group shadow-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0" id="search-addon" style="border-radius: 8px 0 0 8px;"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="ci-search-input" class="form-control border-left-0 pl-0" placeholder="Buscar por Nombre, Sigla o IP Address..." aria-describedby="search-addon" style="border-radius: 0 8px 8px 0; height: 38px;">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="ci-search-clear-btn" style="border-radius: 0 8px 8px 0;" title="Limpiar búsqueda"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-right">
                    <span class="badge badge-light border text-dark font-weight-bold px-3 py-2" id="ci-counter-label" style="font-size: 0.9em;">
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
<div class="modal fade" id="attrModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-gradient-primary text-white border-bottom-0 py-3 d-flex align-items-center">
                <h5 class="modal-title font-weight-bold text-white mb-0" id="attrModalTitle">
                    <i class="fas fa-server mr-2"></i> Detalles del CI
                </h5>
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-light mr-2 border-0" id="btn-maximize-modal" title="Pantalla Completa" style="opacity: 0.8; outline: none; background: transparent; color: white;">
                        <i class="fas fa-expand"></i>
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
// Ficha de Detalles en formato Estructurado Gerencial y Técnico (modal-xl)
function viewCIDetails(id) {
    $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i> Cargando detalles...');
    $('#attrModalBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 text-muted">Obteniendo información del CI de forma segura...</p>
        </div>
    `);
    $('#attrModal').modal('show');
    
    $.get('api_ci.php?action=get_ci_details&id=' + id, function(res) {
        if (res.success) {
            let ci = res.data.ci;
            
            // Set modal title
            $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i>' + ci.hostname + ' <span class="badge badge-info ml-2 font-weight-normal text-white">' + ci.category_name + '</span>');
            
            // Build properties schema lineage
            let allProps = {};
            res.data.lineage.forEach(cat => {
                try {
                    let schema = JSON.parse(cat.schema_json);
                    if (schema && schema.properties) {
                        for (let k in schema.properties) allProps[k] = schema.properties[k];
                    }
                } catch(e) {}
            });
            
            // Build attributes map
            let attrs = {};
            try { attrs = JSON.parse(ci.attributes_json); } catch(e) {}
            
            // Group attributes
            let groups = {};
            for(let key in allProps) {
                let prop = allProps[key];
                let groupName = prop.group || 'Atributos';
                if(!groups[groupName]) groups[groupName] = {};
                groups[groupName][key] = prop;
            }
            
            let groupKeys = Object.keys(groups).sort();
            let hasRelations = res.data.relations && res.data.relations.length > 0;
            
            // Ficha Técnica (Columna izquierda, sin Dependencia Superior que ahora está en pestañas)
            let sourceHtml = ci.source === 'zabbix' ? `
                <span class="badge badge-danger px-2.5 py-1.5"><i class="fas fa-server mr-1"></i> Zabbix Integration</span>
            ` : `
                <span class="badge badge-primary px-2.5 py-1.5"><i class="fas fa-keyboard mr-1"></i> Registro Manual</span>
            `;

            let leftColHtml = `
                <div class="card border-0 shadow-sm mb-4 h-100" style="border-radius: 10px;">
                    <div class="card-body">
                        <div class="text-center pb-3 border-bottom mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 text-primary shadow-sm" style="width: 60px; height: 60px; background-color: rgba(0, 123, 255, 0.1);">
                                <i class="fas fa-server fa-2x"></i>
                            </div>
                            <h4 class="font-weight-bold mb-1 text-dark">${ci.hostname}</h4>
                            <span class="badge badge-info mb-2 px-2 py-1 text-uppercase">${ci.category_name}</span>
                            <p class="text-muted small px-3 mb-0">${ci.description || 'Sin descripción descriptiva registrada.'}</p>
                        </div>
                        
                        <div class="px-2">
                            <div class="mb-3">
                                <div class="detail-card-label">Código Único CI</div>
                                <div class="detail-card-value font-weight-bold text-monospace"><span class="badge badge-dark px-2 py-1">${ci.ci_unique || 'SND-XXXXXXXXXX'}</span></div>
                            </div>
                            <div class="mb-3">
                                <div class="detail-card-label">Sigla / Etiqueta</div>
                                <div class="detail-card-value"><span class="badge badge-secondary px-2 py-1">${ci.sigla || '-'}</span></div>
                            </div>
                            <div class="mb-3">
                                <div class="detail-card-label">Dirección IP</div>
                                <div class="detail-card-value font-weight-bold text-primary"><i class="fas fa-network-wired mr-1"></i>${ci.ip_address || '<span class="text-muted font-italic">N/D</span>'}</div>
                            </div>
                            <div class="mb-3">
                                <div class="detail-card-label">Estado Operativo</div>
                                <div class="detail-card-value"><span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>${ci.status || 'Activo'}</span></div>
                            </div>
                            <div class="mb-3">
                                <div class="detail-card-label">Origen de Datos</div>
                                <div class="detail-card-value mt-1">${sourceHtml}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-4 text-center">
                        <div class="text-muted small">
                            <div><i class="far fa-calendar-alt mr-1"></i> <strong>Creado:</strong> ${ci.created_at ? new Date(ci.created_at).toLocaleString('es-ES') : '-'}</div>
                            <div class="mt-1"><i class="far fa-user mr-1"></i> <strong>Por:</strong> ${ci.creator_name || 'Desconocido'}</div>
                        </div>
                    </div>
                </div>
            `;

            // Atributos y relaciones (Columna derecha)
            let tabsHtml = '<ul class="nav modal-nav-pills mb-3 border-bottom pb-2" role="tablist" id="modalDetailTabs">';
            let contentHtml = '<div class="tab-content" id="modalDetailTabsContent">';
            
            let hasActiveTab = false;
            // Si tiene atributos definidos por grupos
            groupKeys.forEach((groupName, index) => {
                let safeId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-' + index;
                let activeClass = '';
                if (!hasActiveTab) {
                    activeClass = 'active';
                    hasActiveTab = true;
                }
                
                tabsHtml += `
                    <li class="nav-item mr-2">
                        <a class="nav-link font-weight-bold ${activeClass} px-3 py-2" data-toggle="tab" href="#view-${safeId}" role="tab">
                            <i class="fas fa-cubes mr-1.5"></i> ${groupName}
                        </a>
                    </li>`;
                
                contentHtml += `<div class="tab-pane fade show ${activeClass}" id="view-${safeId}" role="tabpanel">`;
                contentHtml += `<div class="row pt-2">`;
                
                let props = groups[groupName] || {};
                let keysCount = Object.keys(props).length;
                
                if (keysCount === 0) {
                    contentHtml += `<div class="col-12 text-center text-muted py-4"><p class="mb-0">Sin atributos configurados en este grupo.</p></div>`;
                } else {
                    for(let key in props) {
                        let prop = props[key];
                        let rawVal = attrs[key];
                        let val = '<span class="text-muted font-italic">N/D</span>';
                        
                        if (rawVal !== undefined && rawVal !== '') {
                            if (prop.type === 'boolean') {
                                if (rawVal == 1 || rawVal === '1' || rawVal === true) {
                                    val = '<span class="badge badge-success px-2.5 py-1.5"><i class="fas fa-check mr-1"></i>Sí</span>';
                                } else {
                                    val = '<span class="badge badge-secondary px-2.5 py-1.5"><i class="fas fa-times mr-1"></i>No</span>';
                                }
                            } else if (prop.type === 'image') {
                                val = `
                                    <div class="my-1 text-center">
                                        <a href="${rawVal}" target="_blank" class="d-inline-block shadow-sm rounded overflow-hidden border bg-white p-1">
                                            <img src="${rawVal}" style="max-height: 120px; max-width: 100%; display: block; object-fit: contain; border-radius: 4px;">
                                        </a>
                                    </div>
                                `;
                            } else if (prop.type === 'multiselect') {
                                let arr = Array.isArray(rawVal) ? rawVal : (typeof rawVal === 'string' ? rawVal.split(',') : []);
                                val = '';
                                arr.forEach(item => {
                                    item = item.trim();
                                    if (item) {
                                        val += `<span class="badge badge-dark mr-1.5 mb-1 px-2.5 py-1.5" style="font-size: 0.85rem;"><i class="fas fa-circle mr-1" style="font-size: 0.5rem; vertical-align: middle;"></i>${item}</span>`;
                                    }
                                });
                                if (!val) val = '<span class="text-muted font-italic">N/D</span>';
                            } else {
                                val = Array.isArray(rawVal) ? rawVal.join(', ') : rawVal;
                            }
                        }
                        
                        let label = prop.title || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        contentHtml += `
                            <div class="col-md-6 mb-3">
                                <div class="p-3 detail-attr-box h-100">
                                    <div class="detail-card-label">${label}</div>
                                    <div class="detail-card-value mt-2">${val}</div>
                                </div>
                            </div>`;
                    }
                }
                
                contentHtml += `</div></div>`;
            });

            // Clasificación Temática de Relaciones y Jerarquía
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
                    'Ubicación': ['país', 'pais', 'ciudad', 'datacenter', 'rooms', 'room', 'rack', 'fila', 'ubicación', 'ubicacion', 'geografía', 'geografia', 'sector', 'edificio', 'localidad', 'área', 'area', 'cuartos', 'cuarto'],
                    'Personal / Contacto': ['personal', 'soporte', 'propietario', 'contacto', 'proveedor', 'usuario', 'creador', 'cliente'],
                    'Facility': ['facility', 'eléctrico', 'electrico', 'aire', 'climatización', 'climatizacion', 'energía', 'energia', 'generador', 'ups', 'pdu', 'batería', 'bateria', 'chiller', 'tablero'],
                    'Hardware / Infraestructura': ['servidor', 'storage', 'switch', 'router', 'firewall', 'chasis', 'blade', 'hardware', 'equipo', 'monitoreo', 'enlace', 'red'],
                    'Servicios / Software': ['servicio', 'software', 'sistema operativo', 'base de datos', 'aplicación', 'aplicacion', 'api', 'licencia', 'vlan', 'puerto']
                };
                for (let group in groupMappings) {
                    if (groupMappings[group].some(keyword => catLower.includes(keyword))) {
                        return group;
                    }
                }
                return 'Otros / Relacionados';
            }

            // 1. Clasificar CIs ascendentes de parent_chain
            if (res.data.parent_chain && res.data.parent_chain.length > 0) {
                res.data.parent_chain.forEach(pci => {
                    let grp = getThematicGroup(pci.category_name);
                    thematicTabs[grp].push({
                        type: 'parent',
                        id: pci.id,
                        hostname: pci.hostname,
                        category_name: pci.category_name,
                        schema_json: pci.schema_json,
                        attributes_json: pci.attributes_json,
                        ci_unique: pci.ci_unique,
                        sigla: pci.sigla,
                        ip_address: pci.ip_address,
                        description: pci.description
                    });
                });
            }

            // 2. Clasificar relaciones directas
            if (res.data.relations && res.data.relations.length > 0) {
                res.data.relations.forEach(r => {
                    let grp = getThematicGroup(r.target_category_name);
                    thematicTabs[grp].push({
                        type: 'relation',
                        id: r.target_id,
                        hostname: r.target_name,
                        category_name: r.target_category_name || 'CI Relacionado',
                        schema_json: r.target_schema_json || '{}',
                        attributes_json: r.target_attributes_json || '{}',
                        ci_unique: r.target_unique,
                        sigla: r.target_sigla,
                        ip_address: r.target_ip,
                        description: r.target_desc,
                        relation_type: r.relation_type,
                        impact: r.impact
                    });
                });
            }

            // Renderizar las pestañas temáticas dinámicas
            let thematicKeys = Object.keys(thematicTabs);
            thematicKeys.forEach((groupName) => {
                let items = thematicTabs[groupName];
                
                let mustShowAlways = ['Ubicación', 'Personal / Contacto', 'Facility'].includes(groupName);
                if (items.length === 0 && !mustShowAlways) return;
                
                let safeGroupId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
                let iconClass = 'fa-link';
                if (groupName === 'Ubicación') iconClass = 'fa-map-marker-alt';
                else if (groupName === 'Personal / Contacto') iconClass = 'fa-users';
                else if (groupName === 'Facility') iconClass = 'fa-building';
                else if (groupName === 'Hardware / Infraestructura') iconClass = 'fa-laptop-house';
                else if (groupName === 'Servicios / Software') iconClass = 'fa-code-branch';
                
                let activeClass = '';
                if (!hasActiveTab) {
                    activeClass = 'active';
                    hasActiveTab = true;
                }
                
                tabsHtml += `
                    <li class="nav-item mr-2">
                        <a class="nav-link font-weight-bold ${activeClass} px-3 py-2" data-toggle="tab" href="#view-theme-${safeGroupId}" role="tab">
                            <i class="fas ${iconClass} mr-1.5"></i> ${groupName} (${items.length})
                        </a>
                    </li>`;
                
                contentHtml += `<div class="tab-pane fade show ${activeClass}" id="view-theme-${safeGroupId}" role="tabpanel">`;
                contentHtml += `<div class="pt-3">`;
                
                if (items.length === 0) {
                    contentHtml += `
                        <div class="row pt-2 px-3">
                            <div class="col-12 text-center py-4 text-muted bg-light rounded border" style="border-style: dashed !important; border-width: 1px;">
                                <i class="fas fa-exclamation-circle mr-1.5 text-warning"></i> N/A (No seleccionado / asociado)
                            </div>
                        </div>`;
                } else {
                    contentHtml += `<div class="row pt-2">`;
                    items.forEach((item) => {
                        let relationInfo = item.type === 'relation' ? ` <span class="badge badge-light border text-monospace ml-1.5" style="font-size: 0.7rem;">${item.relation_type}</span>` : '';
                        let valueHtml = `
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="font-weight-bold text-primary" style="font-size: 0.95rem;">${item.hostname}${relationInfo}</span>
                                <a href="javascript:void(0)" onclick="viewCIDetails(${item.id})" class="text-muted ml-2" title="Abrir Ficha Técnica" style="font-size: 0.95em;"><i class="fas fa-search-plus"></i></a>
                            </div>
                        `;
                        contentHtml += `
                            <div class="col-md-6 mb-3">
                                <div class="p-3 detail-attr-box h-100">
                                    <div class="detail-card-label">${item.category_name}</div>
                                    <div class="detail-card-value mt-2">${valueHtml}</div>
                                </div>
                            </div>`;
                    });
                    contentHtml += `</div>`;
                }
                contentHtml += `</div></div>`;
            });
            
            // Pestaña de Jerarquía de Categorías (Clase Lineage)
            let hierarchyActive = !hasActiveTab ? 'active' : '';
            tabsHtml += `
                <li class="nav-item">
                    <a class="nav-link font-weight-bold ${hierarchyActive} px-3 py-2" data-toggle="tab" href="#view-hierarchy" role="tab">
                        <i class="fas fa-sitemap mr-1.5"></i> Jerarquía de Clase
                    </a>
                </li>`;
            
            contentHtml += `<div class="tab-pane fade show ${hierarchyActive}" id="view-hierarchy" role="tabpanel">`;
            contentHtml += `<div class="pt-2">`;
            
            let lineageHtml = `
                <div class="card border shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="font-weight-bold text-secondary mb-4"><i class="fas fa-sitemap mr-2"></i>Árbol de Categoría del Activo</h6>
                        <div class="d-flex flex-column text-left">
            `;
            res.data.lineage.forEach((cat, idx) => {
                let isLast = idx === res.data.lineage.length - 1;
                let activeClass = isLast ? 'text-primary font-weight-bold' : 'text-muted';
                lineageHtml += `
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem; font-weight: bold; background: ${isLast ? 'linear-gradient(135deg, #007bff, #0056b3)' : '#6c757d'}">
                            ${idx + 1}
                        </div>
                        <div class="ml-3">
                            <span class="${activeClass}" style="font-size: 1.05rem;">${cat.name}</span>
                            ${cat.cat_unique ? `<span class="badge badge-light border ml-2 text-muted text-monospace small">${cat.cat_unique}</span>` : ''}
                        </div>
                    </div>
                    ${!isLast ? `
                    <div class="border-left ml-3.5 my-1" style="height: 25px; border-width: 2px !important; border-color: #dee2e6 !important;"></div>
                    ` : ''}
                `;
            });
            lineageHtml += `
                        </div>
                    </div>
                </div>`;
            
            contentHtml += lineageHtml;
            contentHtml += `</div></div>`;
            
            // Add Zabbix Monitoring tab if integrated
            if (ci.zabbix_host_id) {
                tabsHtml += `
                    <li class="nav-item mr-2">
                        <a class="nav-link font-weight-bold px-3 py-2" data-toggle="tab" href="#view-zabbix-monitoring" role="tab" id="tab-zabbix-monit">
                            <i class="fas fa-heartbeat mr-1.5 text-danger animate__animated animate__pulse animate__infinite"></i> Monitoreo Real-time
                        </a>
                    </li>`;
                
                contentHtml += `
                    <div class="tab-pane fade" id="view-zabbix-monitoring" role="tabpanel">
                        <div class="pt-3">
                            <div id="zabbix-modal-loading" class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                                <h5>Consultando métricas y alarmas en tiempo real...</h5>
                            </div>
                            <div id="zabbix-modal-content" style="display:none;">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="card bg-light border p-3">
                                            <span class="text-uppercase text-muted font-weight-bold" style="font-size:0.75rem;">Host Zabbix ID</span>
                                            <div class="h5 font-weight-bold text-dark mb-0">${ci.zabbix_host_id}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light border p-3">
                                            <span class="text-uppercase text-muted font-weight-bold" style="font-size:0.75rem;">Estado de Conexión</span>
                                            <div class="h5 font-weight-bold text-success mb-0"><i class="fas fa-check-circle mr-1"></i> Conectado</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-exclamation-triangle text-danger mr-2"></i>Alarmas Activas en Zabbix</h6>
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
                                        <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-chart-line text-info mr-2"></i>Métricas Monitoreadas (Últimos Valores)</h6>
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
            
            // Assemble left and right cols in the modal body
            let mainHtml = `
                <div class="container-fluid p-4 text-left">
                    <div class="row">
                        <div class="col-lg-4">
                            ${leftColHtml}
                        </div>
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm h-100" style="border-radius: 10px;">
                                <div class="card-body">
                                    ${tabsHtml}
                                    ${contentHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#attrModalBody').html(mainHtml);

            // Bind Zabbix monitoring tab fetch
            if (ci.zabbix_host_id) {
                // Remove previous handlers first to avoid double calls
                $(document).off('shown.bs.tab', '#tab-zabbix-monit');
                $(document).on('shown.bs.tab', '#tab-zabbix-monit', function() {
                    $('#zabbix-modal-loading').show();
                    $('#zabbix-modal-content').hide();
                    
                    $.getJSON('informes/process_alcance.php', { action: 'get_host_items_triggers', hostid: ci.zabbix_host_id }, function(resp) {
                        $('#zabbix-modal-loading').hide();
                        if (!resp.success) {
                            $('#tbl-zabbix-modal-triggers tbody').html('<tr><td colspan="2" class="text-center text-danger">Error: ' + (resp.error || 'Desconocido') + '</td></tr>');
                            $('#tbl-zabbix-modal-items tbody').html('<tr><td colspan="2" class="text-center text-danger">Error: ' + (resp.error || 'Desconocido') + '</td></tr>');
                            $('#zabbix-modal-content').show();
                            return;
                        }
                        
                        // Helpers inside closure
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

                        // Triggers
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
                        
                        // Items
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
                            itemHtml = '<tr><td colspan="2" class="text-center py-3 text-muted">No se encontraron métricas activas</td></tr>';
                        }
                        $('#tbl-zabbix-modal-items tbody').html(itemHtml);
                        
                        $('#zabbix-modal-content').fadeIn();
                    }).fail(function() {
                        $('#zabbix-modal-loading').hide();
                        $('#tbl-zabbix-modal-triggers tbody').html('<tr><td colspan="2" class="text-center text-danger">Error de comunicación con el servidor</td></tr>');
                        $('#tbl-zabbix-modal-items tbody').html('<tr><td colspan="2" class="text-center text-danger">Error de comunicación con el servidor</td></tr>');
                        $('#zabbix-modal-content').show();
                    });
                });
            }
        } else {
            $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>' + res.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>Error al consultar el endpoint api_ci.php.</div>');
    });
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
    // Manejador para maximizar/restaurar modal
    $('#btn-maximize-modal').click(function() {
        $('#attrModal').toggleClass('modal-fullscreen');
        let icon = $(this).find('i');
        if ($('#attrModal').hasClass('modal-fullscreen')) {
            icon.removeClass('fa-expand').addClass('fa-compress');
        } else {
            icon.removeClass('fa-compress').addClass('fa-expand');
        }
    });

    $('#attrModal').on('hidden.bs.modal', function () {
        $(this).removeClass('modal-fullscreen');
        $('#btn-maximize-modal').find('i').removeClass('fa-compress').addClass('fa-expand');
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
