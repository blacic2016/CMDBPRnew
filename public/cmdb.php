<?php
/**
 * CMDB VILASECA - Visualizador de Activos
 * Ubicación: /var/www/html/Sonda/public/cmdb.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

// Protección de sesión
require_login(); 

// 1. Procesar nombre de la tabla para el título
$sheet_name_clean = 'CMDB';
$name = $_GET['name'] ?? '';

if ($name) {
    // Corregimos la regex: 'sheet_' por el nombre real de la tabla
    $sheet_name_clean = ucfirst(str_replace('sheet_', '', $name));
}

$page_title = $sheet_name_clean;

// 2. Cargar Header (Ruta corregida)
require_once __DIR__ . '/partials/header.php'; 

// 3. Lógica de Paginación y Filtrado
$rows = [];
$cols = [];
$total = 0;
$page = 1;
$perPage = 25;
$lastPage = 1;
$q = '';

if ($name && isValidTableName($name)) {
    $cols = getTableColumns($name);
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $perPage;
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    $filters = [];
    $params = [];
    
    if ($q !== '') {
        $qparam = '%' . $q . '%';
        $or = [];
        foreach ($cols as $c) {
            $or[] = "`$c` LIKE :q";
        }
        $filters[] = '(' . implode(' OR ', $or) . ')';
        $params[':q'] = $qparam;
    }
    
    // Funciones en helpers.php que interactúan con la DB en 172.32.1.51
    $total = countTableRows($name, $filters, $params);
    $rows = fetchTableRows($name, $filters, $params, '', $perPage, $offset);
    $lastPage = (int)ceil($total / $perPage);
}

// Generar Token CSRF para seguridad en las acciones
$csrfToken = get_csrf_token();
?>


        <div class="container-fluid pt-4">
            <?php if ($name && isValidTableName($name)): ?>
                
                <!-- Advanced Header & Stats -->
                <div class="row mb-4 animate__animated animate__fadeIn">
                    <div class="col-md-12">
                        <div class="card card-outline card-primary shadow-lg border-0 bg-white">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h1 class="display-5 font-weight-bold text-dark mb-0">
                                            <i class="fas fa-database text-primary mr-3"></i><?php echo htmlspecialchars($sheet_name_clean); ?>
                                        </h1>
                                        <p class="text-muted lead mt-2 mb-0">Gestión de activos del inventario de CMDB.</p>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div class="btn-group shadow-sm">
                                            <a href="import.php?table=<?php echo urlencode($name); ?>" class="btn btn-outline-primary px-4">
                                                <i class="fas fa-file-import mr-2"></i>Importar
                                            </a>
                                            <a href="item_create.php?table=<?php echo urlencode($name); ?>" class="btn btn-success px-4">
                                                <i class="fas fa-plus mr-2"></i>Nuevo Activo
                                            </a>
                                        </div>
                                        <div class="dropdown d-inline-block ml-2">
                                            <button class="btn btn-dark dropdown-toggle px-3" type="button" data-toggle="dropdown">
                                                <i class="fas fa-tools mr-2"></i>Herramientas
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right shadow-lg border-0">
                                                <h6 class="dropdown-header">Gestión de Tabla</h6>
                                                <a class="dropdown-item text-warning" href="#" onclick="handleTableAction('truncate')">
                                                    <i class="fas fa-eraser mr-2"></i>Vaciar Datos (Truncar)
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="#" onclick="handleTableAction('drop')">
                                                    <i class="fas fa-trash-alt mr-2"></i>Eliminar Tabla Completa
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="row text-center">
                                    <div class="col-md-3 border-right">
                                        <div class="px-3">
                                            <h6 class="text-uppercase text-muted font-weight-bold small">Total Registros</h6>
                                            <span class="h4 font-weight-bold text-primary"><?php echo number_format($total); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="px-3 text-left">
                                            <div class="d-flex justify-content-between align-items-end mb-1">
                                                <h6 class="text-uppercase text-muted font-weight-bold small mb-0">Uso de Almacenamiento</h6>
                                                <span id="storage-text" class="text-xs text-muted">Cargando estadísticas...</span>
                                            </div>
                                            <div class="progress progress-sm shadow-sm" style="height: 10px; border-radius: 5px;">
                                                <div id="storage-progress" class="progress-bar bg-gradient-primary" role="progressbar" style="width: 0%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Data Card -->
                <div class="card shadow-lg border-0 animate__animated animate__fadeInUp" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <form method="get">
                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                                    <div class="input-group input-group-sm">
                                        <input class="form-control border-right-0" name="q" placeholder="Filtro rápido..." value="<?php echo htmlspecialchars($q); ?>" style="border-radius: 20px 0 0 20px; padding-left: 15px;">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary border-left-0" type="submit" style="border-radius: 0 20px 20px 0; padding-right: 15px;">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-8 text-right">
                                <span class="text-xs text-muted">Mostrando <?php echo count($rows); ?> de <?php echo $total; ?> resultados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 align-middle">
                                <thead>
                                    <tr class="bg-light">
                                        <?php foreach ($cols as $c): if ($c === '_row_hash') continue; ?>
                                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-muted"><?php echo htmlspecialchars(str_replace('_', ' ', $c)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td colspan="100%" class="text-center py-5 text-muted">
                                                <i class="fas fa-folder-open fa-3x mb-3 d-block opacity-2"></i>
                                                <p class="lead">No se encontraron registros en esta tabla.</p>
                                            </td>
                                        </tr>
                                    <?php else: foreach ($rows as $r): ?>
                                        <tr class='data-row clickable-row' data-id="<?php echo $r['id']; ?>" data-table="<?php echo htmlspecialchars($name); ?>" style="cursor: pointer; transition: all 0.2s;">
                                            <?php foreach ($cols as $c): if ($c === '_row_hash') continue; ?>
                                                <td class="py-3"><?php echo htmlspecialchars($r[$c] ?? ''); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($lastPage > 1): ?>
                        <div class="card-footer bg-white border-top py-3">
                            <nav>
                                <ul class="pagination pagination-md justify-content-center mb-0">
                                    <?php 
                                    $range = 2; // Cantidad de páginas laterales a mostrar
                                    for ($p = 1; $p <= $lastPage; $p++): 
                                        if ($p == 1 || $p == $lastPage || ($p >= $page - $range && $p <= $page + $range)):
                                            $query_params = http_build_query(array_merge($_GET, ['page' => $p]));
                                    ?>
                                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                                <a class="page-link shadow-sm mx-1 rounded" href="?<?php echo $query_params; ?>"><?php echo $p; ?></a>
                                            </li>
                                    <?php 
                                        elseif ($p == $page - $range - 1 || $p == $page + $range + 1):
                                            echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                        endif;
                                    endfor; 
                                    ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="row justify-content-center align-items-center" style="min-height: 500px;">
                    <div class="col-md-6 text-center animate__animated animate__zoomIn">
                        <div class="p-5 bg-white shadow-lg rounded-lg border-0">
                            <i class="fas fa-database fa-5x text-primary mb-4 opacity-5"></i>
                            <h2 class="font-weight-bold">Centro de Control CMDB</h2>
                            <p class="lead text-muted">Bienvenido al núcleo de gestión de activos. Por favor, selecciona una categoría del panel izquierdo para comenzar a visualizar y gestionar tus activos tecnológicos.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .clickable-row:hover { background-color: rgba(0,123,255,0.05) !important; transform: scale(1.002); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .bg-gradient-primary { background: linear-gradient(90deg, #007bff, #00c6ff); }
    .page-link { border: none; color: #444; }
    .page-item.active .page-link { background: #007bff !important; color: white !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableName = '<?php echo $name; ?>';
    const csrfToken = '<?php echo $csrfToken; ?>';

    // 1. Redirección al detalle al hacer clic en una fila
    document.querySelectorAll('.data-row').forEach(function(row) {
        row.addEventListener('click', function() {
            const id = this.dataset.id;
            const table = this.dataset.table;
            window.location.href = `item_detail.php?table=${encodeURIComponent(table)}&id=${encodeURIComponent(id)}`;
        });
    });

    // 2. Cargar Estadísticas de la Tabla
    if (tableName) {
        fetch('api_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=get_table_stats&tableName=${encodeURIComponent(tableName)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sizeMb = (data.stats.data_size / 1024 / 1024).toFixed(2);
                const items = data.stats.rows;
                $('#storage-text').text(`${items} ítems | ${sizeMb} MB de datos`);
                
                // Cálculo de proporción visual (ejemplo: 50MB como "techo" para la barra)
                let pct = Math.min(100, (data.stats.data_size / (50 * 1024 * 1024)) * 100);
                $('#storage-progress').css('width', pct + '%');
                if (pct > 80) $('#storage-progress').addClass('bg-danger');
            }
        });
    }

    // 3. Manejo de Acciones de Tabla (Vaciar / Eliminar)
    window.handleTableAction = function(type) {
        const actionText = type === 'truncate' ? 'vaciar todos los datos de' : 'eliminar completamente';
        const actionTitle = type === 'truncate' ? '¿Vaciar tabla?' : '¿Eliminar tabla?';
        const apiAction = type === 'truncate' ? 'truncate_cmdb_table' : 'drop_cmdb_table';

        Swal.fire({
            title: actionTitle,
            text: `¿Estás seguro de que deseas ${actionText} la tabla "${tableName}"? Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, proceder',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch('api_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${apiAction}&tableName=${encodeURIComponent(tableName)}&csrf_token=${csrfToken}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('¡Éxito!', data.message, 'success').then(() => {
                            window.location.href = type === 'drop' ? 'dashboard.php' : `cmdb.php?name=${tableName}`;
                        });
                    } else {
                        Swal.fire('Error', data.error, 'error');
                    }
                });
            }
        });
    }
});
</script>