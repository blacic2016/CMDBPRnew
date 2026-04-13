<?php
/**
 * CMDB VILASECA - Configuración de Claves Únicas y Zabbix
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/zabbix_api.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();
if (!has_role(['SUPER_ADMIN'])) {
    die("Acceso denegado: Se requieren permisos de Super Administrador.");
}

$page_title = 'Configuración Maestro';
require_once __DIR__ . '/partials/header.php';

$pdo = getPDO();
$tables = listSheetTables();

$configs = [];
$stmt = $pdo->query("SELECT table_name, unique_columns FROM sheet_configs");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $configs[$r['table_name']] = json_decode($r['unique_columns'], true) ?: [];
}

$zabbix_enabled_tables = [];
$stmt_zabbix = $pdo->query("SELECT table_name FROM zabbix_cmdb_config WHERE is_enabled = 1");
while ($r = $stmt_zabbix->fetch(PDO::FETCH_ASSOC)) {
    $zabbix_enabled_tables[] = $r['table_name'];
}
?>

<style>
    .premium-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06) !important;
    }
    .dark-mode .premium-card { background: #2c3034; box-shadow: 0 4px 20px rgba(0,0,0,0.2) !important; }
    
    .nav-pills .nav-link {
        border-radius: 50px;
        padding: 8px 25px;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.3s ease;
    }
    .nav-pills .nav-link.active {
        box-shadow: 0 4px 10px rgba(0,123,255,0.3);
    }
    
    .table-config-card {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .table-config-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    .dark-mode .table-config-card { border-color: #495057; }
    
    .custom-control-label { cursor: pointer; }
</style>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h1 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-cogs mr-2"></i> Maestro de Configuración</h1>
            <p class="text-muted small mb-0">Gestión de API Zabbix, visibilidad de tablas y reglas de integridad (Claves Únicas).</p>
        </div>
    </div>

    <div class="card premium-card">
        <div class="card-header bg-white border-0 pt-4">
            <ul class="nav nav-pills" id="configTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="zabbix-api-tab" data-toggle="pill" href="#zabbix-api" role="tab">
                        <i class="fas fa-plug mr-2"></i> Enlace Zabbix
                    </a>
                </li>
                <li class="nav-item ml-2">
                    <a class="nav-link" id="zabbix-tables-tab" data-toggle="pill" href="#zabbix-tables" role="tab">
                        <i class="fas fa-microchip mr-2"></i> Tablas Monitoreo
                    </a>
                </li>
                <li class="nav-item ml-2">
                    <a class="nav-link" id="unique-keys-tab" data-toggle="pill" href="#unique-keys" role="tab">
                        <i class="fas fa-key mr-2"></i> Claves Únicas
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content">
                <!-- Tab 1: API Zabbix -->
                <div class="tab-pane fade show active" id="zabbix-api" role="tabpanel">
                    <form id="zabbixCmdbConfigForm">
                        <div class="row">
                            <?php
                                $zabbix_url = ZABBIX_API_URL;
                                $zabbix_ip = '';
                                if (preg_match('/https?:\/\/([^\/]+)\//', $zabbix_url, $matches)) { $zabbix_ip = $matches[1]; }
                                $zabbix_token = ZABBIX_API_TOKEN;
                            ?>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Host / IP Servidor Zabbix</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text bg-light"><i class="fas fa-server"></i></span></div>
                                    <input type="text" name="zabbix_ip" class="form-control" placeholder="172.x.x.x" value="<?= htmlspecialchars($zabbix_ip) ?>" required>
                                </div>
                                <small class="form-text text-muted">Apunta al endpoint: <code>zabbix/api_jsonrpc.php</code></small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Token de Autenticación API</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text bg-light"><i class="fas fa-lock"></i></span></div>
                                    <input type="password" name="zabbix_api_key" class="form-control" placeholder="API Token" value="<?= htmlspecialchars($zabbix_token) ?>" required>
                                </div>
                            </div>
                        </div>
                        <hr class="my-4">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save mr-2"></i> Guardar Cambios</button>
                            <button type="button" class="btn btn-outline-info ml-3 px-4 shadow-sm" onclick="testConnection()">
                                <i class="fas fa-bolt mr-2"></i> Probar Conexión
                            </button>
                        </div>
                    </form>
                    <div id="zabbixTestResult" class="mt-4" style="display:none;"></div>
                </div>

                <!-- Tab 2: Tablas Monitoreo -->
                <div class="tab-pane fade" id="zabbix-tables" role="tabpanel">
                    <h5 class="font-weight-bold mb-3">Visibilidad en Monitoreo</h5>
                    <p class="text-muted">Define qué tablas de inventario pueden ser "zabbixadas" (sincronizadas como hosts).</p>
                    <form id="zabbixTablesForm">
                        <div class="row">
                            <?php foreach ($tables as $t): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success p-3 border rounded table-config-card">
                                        <input type="checkbox" class="custom-control-input checkbox-zbx" id="zbx_<?= $t ?>" name="tables[]" value="<?= $t ?>" <?= in_array($t, $zabbix_enabled_tables) ? 'checked' : '' ?>>
                                        <label class="custom-control-label font-weight-bold pl-2" for="zbx_<?= $t ?>"><?= htmlspecialchars(str_replace('sheet_', '', $t)) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success px-4 shadow-sm"><i class="fas fa-check-circle mr-2"></i> Guardar Visibilidad</button>
                        </div>
                    </form>
                </div>

                <!-- Tab 3: Claves Únicas -->
                <div class="tab-pane fade" id="unique-keys" role="tabpanel">
                    <h5 class="font-weight-bold mb-3">Reglas de Unicidad</h5>
                    <p class="text-muted">Las columnas seleccionadas se usarán para identificar registros únicos durante la importación de Excel.</p>
                    <div class="row">
                        <?php foreach ($tables as $t): 
                            $cols = getTableColumns($t); $existing = $configs[$t] ?? [];
                        ?>
                            <div class="col-md-6 mb-4">
                                <div class="card table-config-card h-100">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <span class="font-weight-bold text-uppercase small"><?= str_replace('sheet_', '', $t) ?></span>
                                        <?php if(!empty($existing)): ?>
                                            <span class="badge badge-primary px-2"><?= count($existing) ?> definidas</span>
                                        <?php endif; ?>
                                    </div>
                                    <form class="configForm" data-table="<?= $t ?>">
                                        <div class="card-body py-3">
                                            <div class="row no-gutters">
                                                <?php foreach ($cols as $c): if (in_array($c, ['id','_row_hash','created_at','updated_at'])) continue; ?>
                                                    <div class="col-6 mb-2">
                                                        <div class="custom-control custom-checkbox small">
                                                            <input type="checkbox" class="custom-control-input" id="key_<?= $t ?>_<?= $c ?>" name="cols[]" value="<?= $c ?>" <?= in_array($c, $existing) ? 'checked' : '' ?>>
                                                            <label class="custom-control-label" for="key_<?= $t ?>_<?= $c ?>"><?= $c ?></label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-white border-0 d-flex justify-content-between">
                                            <button type="submit" class="btn btn-sm btn-link font-weight-bold p-0"><i class="fas fa-save mr-1"></i> Actualizar</button>
                                            <button type="button" class="btn btn-sm btn-link text-danger font-weight-bold p-0 btnDelete"><i class="fas fa-trash-alt mr-1"></i> Limpiar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    
    // 1. Zabbix API Save
    document.getElementById('zabbixCmdbConfigForm').onsubmit = function(e) {
        e.preventDefault();
        saveConfig(this, 'save_zabbix_cmdb_config');
    };

    // 2. Tablas Monitoreo Save
    document.getElementById('zabbixTablesForm').onsubmit = function(e) {
        e.preventDefault();
        saveConfig(this, 'save_zabbix_cmdb_config');
    };

    function saveConfig(form, action) {
        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        const fd = new FormData(form);
        fd.append('action', action);
        fetch('api_action.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Éxito', 'Configuración actualizada.', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', data.error, 'error');
            }
        });
    }

    // 3. Claves Únicas
    document.querySelectorAll('.configForm').forEach(form => {
        form.onsubmit = function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('table', this.dataset.table);
            fd.append('action', 'save');
            fd.append('csrf_token', csrfToken);
            fetch('api_sheet_config.php', { method: 'POST', body: fd }).then(r => r.json()).then(js => {
                if (js.success) toastr.success('Claves guardadas para ' + this.dataset.table);
                else Swal.fire('Error', js.error, 'error');
            });
        };

        form.querySelector('.btnDelete').onclick = function() {
            Swal.fire({
                title: '¿Confirmar?',
                text: "Se borrarán las reglas de unicidad para esta tabla.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, borrar'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('table', form.dataset.table);
                    fd.append('action', 'delete');
                    fd.append('csrf_token', csrfToken);
                    fetch('api_sheet_config.php', { method:'POST', body: fd }).then(r => r.json()).then(js => {
                        if (js.success) location.reload();
                    });
                }
            });
        };
    });
});

function testConnection() {
    const form = document.getElementById('zabbixCmdbConfigForm');
    Swal.fire({ title: 'Probando Conexión...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    const fd = new FormData(form);
    fd.append('action', 'test_zabbix_connection');
    fetch('api_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Conexión Exitosa', 'Versión de Zabbix: ' + data.version, 'success');
        } else {
            Swal.fire('Fallo de Conexión', data.error, 'error');
        }
    });
}
</script>
