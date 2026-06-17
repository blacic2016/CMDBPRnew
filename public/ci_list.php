<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();

$page_title = 'Inventario CMDB Avanzado';
require_once __DIR__ . '/partials/header.php';

$pdo = getPDO();

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category_name_title = 'Todos los CIs';

if ($category_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT name FROM ci_categories WHERE id = ?");
    $stmt_cat->execute([$category_id]);
    $cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $category_name_title = $cat['name'];
    }
    
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
    $in_placeholders = implode(',', array_fill(0, count($descendant_ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as category_name, u.username as creator_name 
        FROM ci_instances i 
        JOIN ci_categories c ON i.category_id = c.id 
        LEFT JOIN users u ON i.created_by = u.id
        WHERE i.category_id IN ($in_placeholders)
        ORDER BY i.created_at DESC
    ");
    $stmt->execute($descendant_ids);
} else {
    $stmt = $pdo->query("
        SELECT i.*, c.name as category_name, u.username as creator_name 
        FROM ci_instances i 
        JOIN ci_categories c ON i.category_id = c.id 
        LEFT JOIN users u ON i.created_by = u.id
        ORDER BY i.created_at DESC
    ");
}
$instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid pt-4">
    <div class="card card-outline card-primary shadow-lg">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="fas fa-sitemap mr-2"></i> Inventario: <?php echo htmlspecialchars($category_name_title); ?></h3>
            <div>
                <a href="ci_business_view.php" class="btn btn-sm btn-info"><i class="fas fa-project-diagram mr-1"></i> Business View</a>
                <a href="ci_builder.php" class="btn btn-sm btn-success"><i class="fas fa-plus mr-1"></i> Nuevo CI</a>
                <?php if (has_role('SUPER_ADMIN')): ?>
                    <a href="ci_categories.php" class="btn btn-sm btn-warning"><i class="fas fa-cogs mr-1"></i> Gestionar Clases</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle" id="ci-table">
                    <thead class="bg-light">
                        <tr>
                            <th>Clase</th>
                            <th>CI / Descripción</th>
                            <th>IP Address</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Atributos Extendidos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instances as $inst): 
                            $attrs = json_decode($inst['attributes_json'], true);
                            $attrCount = is_array($attrs) ? count($attrs) : 0;
                        ?>
                        <tr>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($inst['category_name']); ?></span></td>
                            <td>
                                <div><a href="javascript:void(0)" onclick="viewCIDetails(<?php echo $inst['id']; ?>)" class="font-weight-bold text-primary" title="Ver Detalles Estructurados"><i class="fas fa-search-plus mr-1"></i><?php echo htmlspecialchars($inst['hostname']); ?></a></div>
                                <small class="text-muted"><?php echo htmlspecialchars($inst['description'] ?? 'Sin descripción'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($inst['ip_address']); ?></td>
                            <td>
                                <?php 
                                    $badge = 'secondary';
                                    if ($inst['status'] == 'Activo') $badge = 'success';
                                    elseif ($inst['status'] == 'Pasivo') $badge = 'info';
                                    elseif ($inst['status'] == 'Mantenimiento') $badge = 'warning';
                                    elseif ($inst['status'] == 'Retirado') $badge = 'danger';
                                ?>
                                <span class="badge badge-<?php echo $badge; ?>"><?php echo htmlspecialchars($inst['status']); ?></span>
                            </td>
                            <td>
                                <small class="d-block" title="Fecha de Creación"><i class="far fa-calendar-alt text-muted"></i> <?php echo date('d/m/Y H:i', strtotime($inst['created_at'])); ?></small>
                                <small class="d-block mt-1" title="Creado por"><i class="far fa-user text-muted"></i> <?php echo htmlspecialchars($inst['creator_name'] ?? 'Desconocido'); ?></small>
                                <?php if($inst['source'] == 'zabbix'): ?>
                                    <span class="badge badge-danger mt-1" title="Zabbix Host ID: <?php echo htmlspecialchars($inst['zabbix_host_id']); ?>"><i class="fas fa-server"></i> Zabbix (ID: <?php echo htmlspecialchars($inst['zabbix_host_id']); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-xs btn-outline-primary" onclick="viewCIDetails(<?php echo $inst['id']; ?>)">
                                    Ver <?php echo $attrCount; ?> atributos
                                </button>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="ci_business_view.php?ci_id=<?php echo $inst['id']; ?>" class="btn btn-sm btn-info" title="Ver Relaciones (Grafo)"><i class="fas fa-project-diagram"></i></a>
                                    <a href="ci_builder.php?id=<?php echo $inst['id']; ?>" class="btn btn-sm btn-primary" title="Editar / Actualizar"><i class="fas fa-edit"></i></a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCI(<?php echo $inst['id']; ?>)" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($instances)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No se han creado CIs en esta nueva estructura. Utiliza el botón "Nuevo CI".</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver atributos JSON -->
<div class="modal fade" id="attrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="attrModalTitle">Detalles del CI</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4" id="attrModalBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function viewCIDetails(id) {
    $('#attrModalTitle').text('Cargando...');
    $('#attrModalBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
    $('#attrModal').modal('show');
    
    $.get('api_ci.php?action=get_ci_details&id=' + id, function(res) {
        if (res.success) {
            let ci = res.data.ci;
            $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i>' + ci.hostname + ' <span class="badge badge-info ml-2">' + ci.category_name + '</span>');
            
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
            
            let groups = { 'General': {} };
            for(let key in allProps) {
                let prop = allProps[key];
                let groupName = prop.group || 'General';
                if(!groups[groupName]) groups[groupName] = {};
                groups[groupName][key] = prop;
            }
            
            let tabsHtml = '<ul class="nav nav-tabs w-100 mb-3" role="tablist">';
            let contentHtml = '<div class="tab-content w-100">';
            
            let groupKeys = Object.keys(groups).sort();
            
            // Add Dependencias
            if (res.data.relations.length > 0 || true) {
                if (!groups['Dependencias y Relaciones']) {
                    groupKeys.push('Dependencias y Relaciones');
                    groups['Dependencias y Relaciones'] = {};
                }
            }

            groupKeys.forEach((groupName, index) => {
                let safeId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-' + index;
                let activeClass = index === 0 ? 'active' : '';

                tabsHtml += `
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold ${activeClass}" data-toggle="tab" href="#view-${safeId}" role="tab">
                            <i class="fas fa-layer-group mr-1"></i> ${groupName}
                        </a>
                    </li>`;

                contentHtml += `<div class="tab-pane fade show ${activeClass}" id="view-${safeId}" role="tabpanel">`;
                contentHtml += `<div class="table-responsive"><table class="table table-bordered table-striped table-sm"><tbody>`;
                
                if (groupName === 'General') {
                    contentHtml += `<tr><th style="width:40%" class="bg-light">Hostname</th><td>${ci.hostname}</td></tr>`;
                    contentHtml += `<tr><th class="bg-light">IP Address</th><td>${ci.ip_address || 'N/A'}</td></tr>`;
                    contentHtml += `<tr><th class="bg-light">Status</th><td><span class="badge badge-secondary">${ci.status}</span></td></tr>`;
                    contentHtml += `<tr><th class="bg-light">Descripción</th><td>${ci.description || ''}</td></tr>`;
                    if (ci.source === 'zabbix') {
                        contentHtml += `<tr><th class="bg-light">Zabbix Host ID</th><td>${ci.zabbix_host_id}</td></tr>`;
                    }
                } else if (groupName === 'Dependencias y Relaciones') {
                    if (res.data.relations.length === 0) {
                        contentHtml += `<tr><td class="text-center text-muted">Sin relaciones registradas</td></tr>`;
                    } else {
                        contentHtml += `<tr class="bg-light"><th>Tipo</th><th>Destino</th><th>Impacto</th></tr>`;
                        res.data.relations.forEach(r => {
                            contentHtml += `<tr><td>${r.relation_type}</td><td><i class="fas fa-server mr-1"></i> ${r.target_name}</td><td>${r.impact}</td></tr>`;
                        });
                    }
                }
                
                let props = groups[groupName];
                for(let key in props) {
                    let val = attrs[key] !== undefined && attrs[key] !== '' ? attrs[key] : '<span class="text-muted font-italic">N/D</span>';
                    let label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                    contentHtml += `<tr><th style="width:40%" class="bg-light">${label}</th><td>${val}</td></tr>`;
                }
                
                contentHtml += `</tbody></table></div></div>`;
            });
            
            tabsHtml += '</ul>';
            contentHtml += '</div>';
            
            $('#attrModalBody').html(tabsHtml + contentHtml);
        } else {
            $('#attrModalBody').html('<div class="alert alert-danger">' + res.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#attrModalBody').html('<div class="alert alert-danger">Error al obtener los detalles del CI.</div>');
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
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
