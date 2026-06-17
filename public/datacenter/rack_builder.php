<?php
/**
 * Datacenter Visual Rack Builder
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';

require_login();

$pdo = getPDO();
$rack_id = (int)($_GET['id'] ?? 0);

if (!$rack_id) {
    die("ID de Rack no proporcionado.");
}

// Handle editing of rack properties
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_rack') {
    $name = $_POST['name'] ?? '';
    $room_id = $_POST['room_id'] ?? 0;
    $total_u = (int)($_POST['total_u'] ?? 42);
    $numbering_dir = $_POST['numbering_dir'] ?? 'DOWN';
    $description = $_POST['description'] ?? '';
    
    if ($name && $room_id) {
        $stmt = $pdo->prepare("UPDATE dc_racks SET name=?, room_id=?, total_u=?, numbering_dir=?, description=? WHERE id=?");
        $stmt->execute([$name, $room_id, $total_u, $numbering_dir, $description, $rack_id]);
        $_SESSION['flash_msg'] = "Rack actualizado exitosamente.";
        header("Location: rack_builder.php?id=" . $rack_id);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT r.*, rm.name as room_name FROM dc_racks r JOIN dc_rooms rm ON r.room_id = rm.id WHERE r.id = ?");
$stmt->execute([$rack_id]);
$rack = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rack) {
    die("Rack no encontrado.");
}

$page_title = 'Rack Builder: ' . htmlspecialchars($rack['name']);
$total_u = (int)$rack['total_u'];
$numbering_dir = $rack['numbering_dir'] ?? 'DOWN';
$description = $rack['description'] ?? '';

require_once __DIR__ . '/../partials/header.php';
?>
<style>
    .rack-container {
        width: 340px;
        background: #e0e0e0;
        border: 4px solid #333;
        border-radius: 4px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        padding: 4px;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
    }
    .rack-unit {
        height: 24px;
        border-bottom: 1px dashed #ccc;
        position: relative;
        display: flex;
        align-items: center;
        background: #fff;
    }
    .rack-unit:first-child { border-top: 1px dashed #ccc; }
    .rack-u-label {
        width: 30px;
        text-align: center;
        font-size: 10px;
        color: #666;
        border-right: 1px solid #ccc;
        font-weight: bold;
        background: #f8f9fa;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .rack-slot {
        flex: 1;
        height: 100%;
        position: relative;
        cursor: pointer;
    }
    .rack-slot:hover { background: #e9ecef; }
    
    .device {
        position: absolute;
        left: 31px; /* width of u-label + border */
        right: 0;
        z-index: 10;
        background: #2a2a2a;
        color: white;
        border: 1px solid #000;
        border-radius: 2px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        box-shadow: inset 0 0 5px rgba(255,255,255,0.2);
        overflow: hidden;
        transition: transform 0.1s;
    }
    .device:hover { filter: brightness(1.2); z-index: 11; transform: scale(1.02); }
    .device .dev-name { font-weight: bold; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90%; text-align:center; }
    .device .dev-meta { font-size: 9px; opacity: 0.8; }
    
    /* Panel lateral fijo para scroll */
    .builder-layout {
        display: flex;
        height: calc(100vh - 150px);
        gap: 20px;
    }
    .rack-scroll-area {
        flex: 0 0 400px;
        overflow-y: auto;
        padding-right: 10px;
        background: #f4f6f9;
        border-radius: 8px;
        padding: 20px 0;
    }
    .details-area {
        flex: 1;
        overflow-y: auto;
    }
</style>

<div class="container-fluid pt-3">
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3">
            <?php echo htmlspecialchars($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="fas fa-server text-warning mr-2"></i> <?php echo htmlspecialchars($rack['name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($rack['room_name']); ?>)</small></h4>
            <p class="text-muted mb-0">
                <span class="badge badge-secondary mr-2"><?php echo $total_u; ?> U Disponibles</span>
                <span class="badge badge-info mr-2"><i class="fas fa-arrow-<?php echo $numbering_dir === 'UP' ? 'down' : 'up'; ?>"></i> <?php echo $numbering_dir === 'UP' ? 'U1 Arriba (UP)' : 'U1 Abajo (DOWN)'; ?></span>
                <?php if ($description): ?>
                    <span class="text-muted ml-2"><i class="fas fa-comment-alt mr-1"></i> <?php echo htmlspecialchars($description); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <button class="btn btn-warning btn-sm mr-2" data-toggle="modal" data-target="#editRackModal"><i class="fas fa-edit mr-1"></i> Editar Rack</button>
            <a href="racks.php?room_id=<?php echo $rack['room_id']; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    <div class="builder-layout">
        <!-- RACK VISUAL -->
        <div class="rack-scroll-area shadow-sm">
            <div class="rack-container" id="rack-container">
                <!-- Se llenará con JS -->
            </div>
        </div>
        
        <!-- PANEL DE DETALLES -->
        <div class="details-area">
            <div class="card card-outline card-primary shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title" id="form-title"><i class="fas fa-info-circle mr-2"></i> Seleccione un espacio o equipo</h3>
                    <button type="button" class="btn btn-sm btn-danger d-none" id="btn-delete-device"><i class="fas fa-trash"></i> Eliminar Equipo</button>
                </div>
                <div class="card-body" style="overflow-y: auto;">
                    <div id="welcome-msg" class="text-center py-5 text-muted">
                        <i class="fas fa-hand-pointer fa-3x mb-3"></i>
                        <p>Haz clic en un espacio vacío del rack para agregar un equipo, o haz clic en un equipo existente para ver/editar sus detalles.</p>
                    </div>
                    
                    <form id="device-form" class="d-none">
                        <input type="hidden" name="action" value="save_device">
                        <input type="hidden" name="id" id="dev_id" value="0">
                        <input type="hidden" name="rack_id" value="<?php echo $rack_id; ?>">
                        
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-2">Origen de Datos</h5>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-outline-primary active" id="lbl-source-manual">
                                        <input type="radio" name="source" id="source_manual" value="manual" checked autocomplete="off"> <i class="fas fa-keyboard"></i> Creación Manual
                                    </label>
                                    <label class="btn btn-outline-primary" id="lbl-source-zabbix">
                                        <input type="radio" name="source" id="source_zabbix" value="zabbix" autocomplete="off"> <i class="fas fa-server"></i> Cargar desde Zabbix
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="zabbix-selection-area" class="d-none border p-3 bg-light mb-3 rounded">
                            <h6><i class="fas fa-search"></i> Buscar en Zabbix</h6>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Hostgroup Zabbix</label>
                                    <select id="zabbix_hostgroup" class="form-control">
                                        <option value="">Cargando hostgroups...</option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Equipo (Host)</label>
                                    <select id="zabbix_host" class="form-control" disabled>
                                        <option value="">Seleccione un hostgroup primero</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="button" class="btn btn-sm btn-info" id="btn-fetch-zabbix" disabled><i class="fas fa-download"></i> Traer Datos</button>
                            </div>
                        </div>
                        
                        <h5 class="text-primary border-bottom pb-2 mb-3">Ubicación y Dimensiones</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Posición Inicial (U) <span class="text-muted" style="font-size:0.85em;">(<?php echo $numbering_dir === 'UP' ? 'Superior' : 'Inferior'; ?>)</span></label>
                                <input type="number" name="start_u" id="dev_start_u" class="form-control" required min="1" max="<?php echo $total_u; ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Tamaño (Cantidad de U's)</label>
                                <input type="number" name="height_u" id="dev_height_u" class="form-control" required min="1" max="<?php echo $total_u; ?>">
                            </div>
                        </div>
                        
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-2">Información del Activo</h5>
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label>Nombre del Servidor / Equipo <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="dev_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Marca (Make)</label>
                                <input type="text" name="make" id="dev_make" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Modelo (Model)</label>
                                <input type="text" name="model" id="dev_model" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Asset Tag</label>
                                <input type="text" name="asset_tag" id="dev_asset_tag" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Serial Number</label>
                                <input type="text" name="serial_number" id="dev_serial_number" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Propietario (Owner)</label>
                                <input type="text" name="owner" id="dev_owner" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Función del Servidor</label>
                                <input type="text" name="server_function" id="dev_server_function" class="form-control">
                            </div>
                        </div>

                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-2">Red y Visualización</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Dirección IP</label>
                                <input type="text" name="ip_address" id="dev_ip" class="form-control">
                            </div>
                        </div>

                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-2">Energía y Físico (OpenDCIM Specs)</h5>
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Peso (Kg)</label>
                                <input type="number" step="0.1" name="weight" id="dev_weight" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Voltaje (V)</label>
                                <input type="number" name="voltage" id="dev_voltage" class="form-control" placeholder="Ej. 110, 220">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Potencia (Watts)</label>
                                <input type="number" name="watts" id="dev_watts" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Amperaje (A)</label>
                                <input type="number" step="0.1" name="amps" id="dev_amps" class="form-control">
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-6 form-group">
                                <label>Color en el Rack</label>
                                <input type="color" name="color" id="dev_color" class="form-control" style="height: 38px;">
                            </div>
                        </div>
                        
                        <div class="form-group text-right mt-4 border-top pt-3">
                            <button type="button" class="btn btn-secondary mr-2" id="btn-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const TOTAL_U = <?php echo $total_u; ?>;
    const RACK_ID = <?php echo $rack_id; ?>;
    const NUMBERING_DIR = '<?php echo $numbering_dir; ?>';
    const U_HEIGHT_PX = 24; 
    let devices = [];
    
    $(document).ready(function() {
        // En CSS, el rack-container necesita position: relative para que los dispositivos absolutos se posicionen correctamente
        $('#rack-container').css('position', 'relative');
        
        buildRackGrid();
        loadDevices();

        $('#device-form').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadDevices();
                        resetForm();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function(xhr) {
                    toastr.error('Error de servidor. Revisa la consola.');
                    console.error(xhr.responseText);
                }
            });
        });

        $('#btn-cancel').click(function() {
            resetForm();
        });

        $('#btn-delete-device').click(function() {
            if(confirm('¿Seguro que deseas eliminar este equipo del rack?')) {
                let id = $('#dev_id').val();
                $.post('api.php', {action: 'delete_device', id: id}, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadDevices();
                        resetForm();
                    } else {
                        toastr.error(res.message || 'Error desconocido al eliminar.');
                    }
                }, 'json').fail(function(xhr) {
                    toastr.error('Error de servidor al eliminar. Revisa la consola.');
                    console.error(xhr.responseText);
                });
            }
        });

        // Lógica Zabbix
        $('input[name="source"]').change(function() {
            if ($(this).val() === 'zabbix') {
                $('#zabbix-selection-area').removeClass('d-none');
                loadZabbixHostgroups();
            } else {
                $('#zabbix-selection-area').addClass('d-none');
            }
        });

        $('#zabbix_hostgroup').change(function() {
            let gid = $(this).val();
            if (gid) {
                loadZabbixHosts(gid);
            } else {
                $('#zabbix_host').html('<option value="">Seleccione un hostgroup primero</option>').prop('disabled', true);
                $('#btn-fetch-zabbix').prop('disabled', true);
            }
        });

        $('#zabbix_host').change(function() {
            if ($(this).val()) {
                $('#btn-fetch-zabbix').prop('disabled', false);
            } else {
                $('#btn-fetch-zabbix').prop('disabled', true);
            }
        });

        $('#btn-fetch-zabbix').click(function() {
            let selectedOption = $('#zabbix_host option:selected');
            if (selectedOption.val()) {
                let name = selectedOption.data('name');
                let ip = selectedOption.data('ip');
                
                $('#dev_name').val(name);
                $('#dev_ip').val(ip);
                toastr.success('Datos cargados desde Zabbix. Revisa y completa el formulario.');
            }
        });
    });

    function loadZabbixHostgroups() {
        let sel = $('#zabbix_hostgroup');
        sel.html('<option value="">Cargando...</option>').prop('disabled', true);
        $.get('api.php?action=get_zabbix_hostgroups', function(res) {
            if (res.success) {
                let html = '<option value="">-- Seleccionar Hostgroup --</option>';
                res.data.forEach(hg => {
                    html += `<option value="${hg.groupid}">${hg.name}</option>`;
                });
                sel.html(html).prop('disabled', false);
            } else {
                toastr.error('Error al cargar hostgroups: ' + res.message);
                sel.html('<option value="">Error</option>');
            }
        }, 'json').fail(function() {
            toastr.error('Error de conexión al cargar hostgroups.');
            sel.html('<option value="">Error</option>');
        });
    }

    function loadZabbixHosts(groupid) {
        let sel = $('#zabbix_host');
        sel.html('<option value="">Cargando hosts...</option>').prop('disabled', true);
        $('#btn-fetch-zabbix').prop('disabled', true);
        $.get('api.php?action=get_zabbix_hosts&groupid=' + groupid, function(res) {
            if (res.success) {
                let html = '<option value="">-- Seleccionar Host --</option>';
                res.data.forEach(h => {
                    html += `<option value="${h.hostid}" data-name="${h.name}" data-ip="${h.ip}">${h.name} (${h.ip || 'Sin IP'})</option>`;
                });
                sel.html(html).prop('disabled', false);
            } else {
                toastr.error('Error al cargar hosts: ' + res.message);
                sel.html('<option value="">Error</option>');
            }
        }, 'json').fail(function() {
            toastr.error('Error de conexión al cargar hosts.');
            sel.html('<option value="">Error</option>');
        });
    }

    function buildRackGrid() {
        let container = $('#rack-container');
        container.empty();
        
        if (NUMBERING_DIR === 'UP') {
            // Los racks se numeran de arriba (1) hacia abajo (TOTAL_U)
            for (let u = 1; u <= TOTAL_U; u++) {
                let uHtml = `
                    <div class="rack-unit" data-u="${u}">
                        <div class="rack-u-label">${u}</div>
                        <div class="rack-slot" onclick="openCreateForm(${u})"></div>
                    </div>
                `;
                container.append(uHtml);
            }
        } else {
            // Los racks se numeran de abajo (1) hacia arriba (TOTAL_U)
            for (let u = TOTAL_U; u >= 1; u--) {
                let uHtml = `
                    <div class="rack-unit" data-u="${u}">
                        <div class="rack-u-label">${u}</div>
                        <div class="rack-slot" onclick="openCreateForm(${u})"></div>
                    </div>
                `;
                container.append(uHtml);
            }
        }
    }

    function loadDevices() {
        $.get(`api.php?action=get_devices&rack_id=${RACK_ID}`, function(res) {
            if (res.success) {
                devices = res.data;
                renderDevices();
            }
        }, 'json');
    }

    function renderDevices() {
        $('.device').remove(); // Limpiar existentes
        
        devices.forEach(dev => {
            let startU = parseInt(dev.start_u);
            let heightU = parseInt(dev.height_u);
            
            if (startU > TOTAL_U) return; // Fuera de rango
            
            let heightPx = heightU * U_HEIGHT_PX;
            let positionStyle = '';
            
            if (NUMBERING_DIR === 'UP') {
                // startU = 1 significa la parte superior absoluta
                let topPx = (startU - 1) * U_HEIGHT_PX;
                positionStyle = `top: ${topPx}px;`;
            } else {
                // startU = 1 significa la parte inferior absoluta
                let bottomPx = (startU - 1) * U_HEIGHT_PX;
                positionStyle = `bottom: ${bottomPx}px;`;
            }
            
            // color por defecto
            let bgColor = dev.details.color ? dev.details.color : '#2a2a2a';
            
            let devHtml = `
                <div class="device" 
                     style="${positionStyle} height: ${heightPx}px; background-color: ${bgColor};" 
                     onclick="openEditForm(${dev.id})">
                    <div class="dev-name">${dev.name}</div>
                    ${heightU > 1 ? `<div class="dev-meta">${dev.details.make || ''} ${dev.details.model || ''}</div>` : ''}
                </div>
            `;
            $('#rack-container').append(devHtml);
        });
    }

    function openCreateForm(u) {
        resetForm();
        $('#welcome-msg').addClass('d-none');
        $('#device-form').removeClass('d-none');
        $('#form-title').html('<i class="fas fa-plus-circle mr-2 text-success"></i> Agregar Equipo');
        
        $('#dev_id').val(0);
        $('#dev_start_u').val(u);
        $('#dev_height_u').val(1);
        $('#dev_color').val('#2a2a2a');
        
        // Reset Zabbix toggle
        $('#source_manual').prop('checked', true).parent().addClass('active');
        $('#source_zabbix').prop('checked', false).parent().removeClass('active');
        $('#zabbix-selection-area').addClass('d-none');
        
        $('#dev_name').focus();
    }

    function openEditForm(id) {
        let dev = devices.find(d => d.id == id);
        if(!dev) return;
        
        resetForm();
        $('#welcome-msg').addClass('d-none');
        $('#device-form').removeClass('d-none');
        $('#btn-delete-device').removeClass('d-none');
        $('#form-title').html('<i class="fas fa-edit mr-2 text-primary"></i> Editar Equipo');
        
        $('#dev_id').val(dev.id);
        $('#dev_start_u').val(dev.start_u);
        $('#dev_height_u').val(dev.height_u);
        $('#dev_name').val(dev.name);
        
        $('#dev_make').val(dev.details.make || '');
        $('#dev_model').val(dev.details.model || '');
        $('#dev_asset_tag').val(dev.details.asset_tag || '');
        $('#dev_serial_number').val(dev.details.serial_number || '');
        $('#dev_owner').val(dev.details.owner || '');
        $('#dev_server_function').val(dev.details.server_function || '');
        $('#dev_ip').val(dev.details.ip_address || '');
        $('#dev_weight').val(dev.details.weight || '');
        $('#dev_watts').val(dev.details.watts || '');
        $('#dev_amps').val(dev.details.amps || '');
        $('#dev_voltage').val(dev.details.voltage || '');
        $('#dev_color').val(dev.details.color || '#2a2a2a');
    }

    function resetForm() {
        $('#device-form')[0].reset();
        $('#welcome-msg').removeClass('d-none');
        $('#device-form').addClass('d-none');
        $('#btn-delete-device').addClass('d-none');
        $('#form-title').html('<i class="fas fa-info-circle mr-2"></i> Seleccione un espacio o equipo');
        
        // Reset Zabbix area
        $('#source_manual').prop('checked', true).parent().addClass('active').siblings().removeClass('active');
        $('#zabbix-selection-area').addClass('d-none');
        $('#zabbix_host').html('<option value="">Seleccione un hostgroup primero</option>').prop('disabled', true);
        $('#btn-fetch-zabbix').prop('disabled', true);
    }
</script>

<!-- Modal Editar Rack -->
<div class="modal fade" id="editRackModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i> Editar Propiedades del Rack</h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_rack">
                <div class="modal-body text-left">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="text-dark">Cuarto (Room) <span class="text-danger">*</span></label>
                            <select name="room_id" class="form-control" required>
                                <?php 
                                $rooms = $pdo->query("SELECT id, name FROM dc_rooms ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rooms as $rm): 
                                ?>
                                    <option value="<?php echo $rm['id']; ?>" <?php echo $rack['room_id'] == $rm['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rm['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark">Nombre del Rack <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($rack['name']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark">Tamaño (Unidades Rack - U)</label>
                            <input type="number" name="total_u" class="form-control" min="1" max="100" required value="<?php echo $total_u; ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark">Dirección de Numeración (U1)</label>
                            <select name="numbering_dir" class="form-control">
                                <option value="DOWN" <?php echo $numbering_dir === 'DOWN' ? 'selected' : ''; ?>>Abajo hacia Arriba (U1 abajo)</option>
                                <option value="UP" <?php echo $numbering_dir === 'UP' ? 'selected' : ''; ?>>Arriba hacia Abajo (U1 arriba)</option>
                            </select>
                        </div>
                        <div class="col-md-12 form-group">
                            <label class="text-dark">Descripción / Propósito</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark font-weight-bold"><i class="fas fa-save mr-1"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
