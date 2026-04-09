<?php
/**
 * CMDB VILASECA - Configuración de Claves Únicas y Zabbix
 * Ubicación: /var/www/html/Sonda/public/sheet_configs.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/zabbix_api.php';

// Protección de sesión: Solo SUPER_ADMIN puede gestionar claves
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();
if (!has_role(['SUPER_ADMIN'])) {
    die("Acceso denegado: Se requieren permisos de Super Administrador.");
}

$page_title = 'Configuración de Claves Únicas';
require_once __DIR__ . '/partials/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php

$pdo = getPDO();
$tables = listSheetTables(); // Listar tablas que empiezan con 'sheet_'

// 1. Cargar configuraciones de claves únicas existentes
$configs = [];
$stmt = $pdo->query("SELECT table_name, unique_columns FROM sheet_configs");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $configs[$r['table_name']] = json_decode($r['unique_columns'], true) ?: [];
}

// 2. Cargar tablas habilitadas para el Monitoreo Zabbix
$zabbix_enabled_tables = [];
$stmt_zabbix = $pdo->query("SELECT table_name FROM zabbix_cmdb_config WHERE is_enabled = 1");
while ($r = $stmt_zabbix->fetch(PDO::FETCH_ASSOC)) {
    $zabbix_enabled_tables[] = $r['table_name'];
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Configuración Maestro de Tablas</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" id="zabbix-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="zabbix-api-tab" data-toggle="pill" href="#zabbix-api" role="tab" aria-controls="zabbix-api" aria-selected="true">
                                <i class="fas fa-server mr-1"></i> Configuración API
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="zabbix-tables-tab" data-toggle="pill" href="#zabbix-tables" role="tab" aria-controls="zabbix-tables" aria-selected="false">
                                <i class="fas fa-table mr-1"></i> Tablas Visibles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="unique-keys-tab" data-toggle="pill" href="#unique-keys" role="tab" aria-controls="unique-keys" aria-selected="false">
                                <i class="fas fa-key mr-1"></i> Claves Únicas
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <form id="zabbixCmdbConfigForm">
                        
                        <div class="tab-content" id="zabbix-tabs-content">
                            <!-- Pestaña: Configuración API -->
                            <div class="tab-pane fade show active" id="zabbix-api" role="tabpanel" aria-labelledby="zabbix-api-tab">
                                <div class="row mb-4">
                                    <?php
                                        $zabbix_url = ZABBIX_API_URL;
                                        $zabbix_ip = '';
                                        if (preg_match('/https?:\/\/([^\/]+)\//', $zabbix_url, $matches)) {
                                            $zabbix_ip = $matches[1];
                                        }
                                        $zabbix_token = ZABBIX_API_TOKEN;
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <label><i class="fas fa-server mr-1"></i> Servidor Zabbix (IP o Hostname)</label>
                                        <input type="text" name="zabbix_ip" class="form-control" placeholder="Ej: 172.32.1.50" value="<?php echo htmlspecialchars($zabbix_ip); ?>" required>
                                        <small class="text-muted">Se usará para construir la URL de la API: <code>http://{IP}/zabbix/api_jsonrpc.php</code></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label><i class="fas fa-key mr-1"></i> Zabbix API Token</label>
                                        <input type="password" name="zabbix_api_key" class="form-control" placeholder="Ingresa el token de Zabbix" value="<?php echo htmlspecialchars($zabbix_token); ?>" required>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-start">
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="fas fa-save mr-1"></i> Guardar Configuración API
                                    </button>
                                     <button type="button" id="btnTestZabbix" class="btn btn-info" onclick="testConnection()">
                                         <i class="fas fa-plug mr-1"></i> Probar Conexión
                                     </button>
                                </div>
                                <div id="zabbixTestResult" class="mt-3" style="display:none;">
                                    <div class="alert" role="alert">
                                        <h5 class="alert-heading"><i class="fas fa-info-circle mr-1"></i> Resultado de la Prueba</h5>
                                        <p id="zabbixTestMsg" class="mb-0"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Pestaña: Tablas Visibles -->
                            <div class="tab-pane fade" id="zabbix-tables" role="tabpanel" aria-labelledby="zabbix-tables-tab">
                                <h5 class="mb-3 text-secondary"><i class="fas fa-eye mr-2"></i> Selección de Tablas para Monitoreo</h5>
                                <p class="text-muted">Marca las tablas que el equipo técnico de SONDA podrá utilizar para gestionar hosts en Zabbix automáticamente.</p>
                                <div class="row mb-4">
                                    <?php foreach ($tables as $t): ?>
                                        <div class="col-md-3 mb-2">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input checkbox-zbx" 
                                                       id="zbx_<?php echo $t; ?>" name="tables[]" value="<?php echo $t; ?>"
                                                       <?php echo in_array($t, $zabbix_enabled_tables) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="zbx_<?php echo $t; ?>">
                                                    <?php echo htmlspecialchars(str_replace('sheet_', '', $t)); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex justify-content-start border-top pt-3">
                                    <button type="submit" class="btn btn-success mr-2">
                                        <i class="fas fa-check-circle mr-1"></i> Guardar Selección de Tablas
                                    </button>
                                    <button type="button" id="btnClearZabbixTables" class="btn btn-outline-danger">
                                        <i class="fas fa-undo mr-1"></i> Limpiar Selección
                                    </button>
                                </div>
                            </div>

                            <!-- Pestaña: Claves Únicas -->
                            <div class="tab-pane fade" id="unique-keys" role="tabpanel" aria-labelledby="unique-keys-tab">
                                <h5 class="mb-3 text-secondary"><i class="fas fa-key mr-2"></i> Definición de Claves Únicas para Importación</h5>
                                <p class="text-muted">Selecciona las columnas que identifican de forma única a un activo para evitar duplicados en Excel.</p>
                                
                                <?php if (empty($tables)): ?>
                                    <div class="alert alert-info">No se detectaron tablas de inventario.</div>
                                <?php else: ?>
                                    <?php foreach ($tables as $t): 
                                        $cols = getTableColumns($t); 
                                        $existing = $configs[$t] ?? []; 
                                    ?>
                                        <div class="card card-outline card-secondary mb-3 shadow-sm">
                                            <div class="card-header bg-light py-2">
                                                <h3 class="card-title text-bold text-uppercase" style="font-size: 0.9rem;"><?php echo str_replace('sheet_', '', $t); ?></h3>
                                            </div>
                                            <div class="card-body py-2">
                                                <form class="configForm" data-table="<?php echo htmlspecialchars($t); ?>">
                                                    
                                                    <div class="row">
                                                        <?php foreach ($cols as $c): 
                                                            if (in_array($c, ['id','_row_hash','created_at','updated_at'])) continue; 
                                                        ?>
                                                            <div class="col-md-3 form-check mb-1">
                                                                <input class="form-check-input" type="checkbox" value="<?php echo htmlspecialchars($c); ?>" 
                                                                       id="<?php echo $t . '_' . $c; ?>" name="cols[]" 
                                                                       <?php echo in_array($c, $existing) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label small" for="<?php echo $t . '_' . $c; ?>">
                                                                    <?php echo htmlspecialchars($c); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="mb-2 mt-2">
                                                        <?php if(!empty($existing)): ?>
                                                            <div class="p-2 bg-light border rounded shadow-sm">
                                                                <small class="text-bold text-info"><i class="fas fa-info-circle mr-1"></i> Parámetros almacenados:</small>
                                                                <span class="badge badge-info p-1 ml-1" style="font-size: 0.75rem;">
                                                                    <?php echo implode(', ', $existing); ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-2 d-flex align-items-center border-top pt-2">
                                                        <button type="submit" class="btn btn-success btn-xs mr-2">
                                                            <i class="fas fa-check mr-1"></i> Guardar Claves
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-xs btnDelete">
                                                            <i class="fas fa-trash-alt mr-1"></i> Limpiar
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    
    // 1. Guardar Configuración de visibilidad para Zabbix
    const zabbixForm = document.getElementById('zabbixCmdbConfigForm');
    if(zabbixForm) {
        zabbixForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Guardando...',
                text: 'Actualizando configuración técnica y de monitoreo.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            const formData = new FormData(this);
            formData.append('action', 'save_zabbix_cmdb_config');

            fetch('api_action.php', { method: 'POST', body: formData })
            .then(res => {
                if (!res.ok) throw new Error('Servidor respondió con código ' + res.status);
                return res.text(); // Leemos como texto primero para validar JSON
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        toastr?.success('Configuración de Zabbix actualizada.');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                } catch (e) {
                    console.error('Error al decodificar JSON:', text);
                    alert('Error de respuesta del servidor (No es JSON). Revisa la consola o los logs.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de conexión con api_action.php: ' + err.message);
            });
        });

        // 1.1 Limpiar Selección de Tablas
        const btnClearTables = document.getElementById('btnClearZabbixTables');
        if(btnClearTables) {
            btnClearTables.addEventListener('click', function() {
                document.querySelectorAll('.checkbox-zbx').forEach(cb => cb.checked = false);
            });
        }
    }

    // 2. Guardar Claves Únicas por Tabla
    document.querySelectorAll('.configForm').forEach(function(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const table = this.dataset.table;
            const data = new FormData(this);
            data.append('table', table);
            data.append('action', 'save');
            data.append('csrf_token', csrfToken);

            fetch('api_sheet_config.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(js => { 
                if (js.success) { 
                    location.reload(); 
                } else {
                    alert(js.error || 'Error al guardar'); 
                }
            })
            .catch(() => alert('Error de red al conectar con api_config_sheets.php'));
        });

        // 3. Eliminar Configuración de Claves
        form.querySelector('.btnDelete').addEventListener('click', function(){
            if (!confirm('¿Deseas eliminar la configuración de claves para esta tabla?')) return;
            const table = form.dataset.table; 
            const data = new FormData(); 
            data.append('table', table); 
            data.append('action','delete');
            data.append('csrf_token', csrfToken);

            fetch('api_sheet_config.php', { method:'POST', body: data })
            .then(r => r.json())
            .then(js => { 
                if (js.success) location.reload(); 
                else alert(js.error); 
            });
        });
    });
});

function testConnection() {
    console.log('testConnection global invoked');
    const zabbixForm = document.getElementById('zabbixCmdbConfigForm');
    const btnTest = document.getElementById('btnTestZabbix');
    const testResultDiv = document.getElementById('zabbixTestResult');
    const testMsg = document.getElementById('zabbixTestMsg');

    if (typeof Swal === 'undefined') {
        alert('Cargando librerías de alertas... Por favor reintente en 2 segundos.');
        return;
    }

    Swal.fire({
        title: 'Procesando...',
        text: 'Consultando API de Zabbix, por favor espere.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const formData = new FormData(zabbixForm);
    formData.append('action', 'test_zabbix_connection');

    btnTest.disabled = true;
    btnTest.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Consultando...';
    testResultDiv.style.display = 'none';

    fetch('api_action.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        Swal.close();
        testResultDiv.style.display = 'block';
        const alertDiv = testResultDiv.querySelector('.alert');
        
        if (data.success) {
            alertDiv.className = 'alert alert-success shadow-sm';
            testMsg.innerHTML = `<strong>Éxito!</strong><br>Conexión establecida con Zabbix.<br>Versión detectada: <strong>${data.version}</strong><br>${data.message || ''}`;
            
            Swal.fire({
                title: 'Conexión Exitosa',
                text: 'Versión de Zabbix: ' + data.version,
                icon: 'success'
            });
        } else {
            alertDiv.className = 'alert alert-danger shadow-sm';
            testMsg.innerHTML = `<strong>Error de Conexión:</strong><br>${data.error}`;
            
            Swal.fire({
                title: 'Error de Conexión',
                text: data.error,
                icon: 'error'
            });
        }
    })
    .catch(err => {
        Swal.close();
        testResultDiv.style.display = 'block';
        testResultDiv.querySelector('.alert').className = 'alert alert-danger shadow-sm';
        testMsg.innerHTML = `<strong>Error Crítico:</strong><br>Ocurrió un error al contactar con el servidor.`;
        Swal.fire('Error Critico', 'No se pudo contactar con el endpoint de pruebas.', 'error');
    })
    .finally(() => {
        btnTest.disabled = false;
        btnTest.innerHTML = '<i class="fas fa-plug mr-1"></i> Probar Conexión';
    });
}
</script>