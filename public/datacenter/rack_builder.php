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
        position: relative;
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
    .device.depth-half { right: 50% !important; border-right: 3px dashed #ffc107 !important; }
    .device.depth-third { right: 66% !important; border-right: 3px dashed #dc3545 !important; }
    
    /* Estilos para PDUs / Multitomas Verticales */
    .device.vertical-pdu {
        left: auto !important;
        right: auto !important;
        width: 32px !important;
        z-index: 20 !important;
        color: #333;
        border: 1px solid #777;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        box-shadow: 2px 2px 8px rgba(0,0,0,0.4), inset 0 0 4px rgba(255,255,255,0.6);
        padding: 8px 0;
        box-sizing: border-box;
        background: linear-gradient(to right, #f0f0f0, #d8d8d8, #c0c0c0);
        overflow: hidden;
    }
    .device.vertical-pdu.pdu-dark {
        background: linear-gradient(to right, #444, #2d2d2d, #1a1a1a);
        color: #fff;
        border-color: #222;
        box-shadow: 2px 2px 8px rgba(0,0,0,0.5), inset 0 0 4px rgba(255,255,255,0.1);
    }
    .device.vertical-pdu.left-pdu {
        left: -40px !important;
    }
    .device.vertical-pdu.right-pdu {
        right: -40px !important;
    }
    .device.vertical-pdu .pdu-outlets-container {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: center;
        margin-bottom: auto;
        margin-top: 4px;
        width: 100%;
        max-height: calc(100% - 60px);
        overflow: hidden;
    }
    .device.vertical-pdu .pdu-outlet {
        width: 10px;
        height: 10px;
        background: #111;
        border-radius: 50%;
        box-shadow: inset 0 0 3px rgba(255,255,255,0.2), 0 1px 1px rgba(255,255,255,0.4);
        position: relative;
        flex-shrink: 0;
    }
    .device.vertical-pdu .pdu-outlet::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 4px;
        height: 4px;
        background: #444;
        border-radius: 50%;
    }
    .device.vertical-pdu.pdu-dark .pdu-outlet::after {
        background: #222;
    }
    .device.vertical-pdu .pdu-text {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 9px;
        font-weight: bold;
        letter-spacing: 0.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 80%;
        text-align: center;
        padding: 4px 2px;
        margin-top: auto;
        line-height: 1;
        width: 100%;
    }
    .device.vertical-pdu.right-pdu .pdu-text {
        transform: rotate(0deg);
    }
    
    .rack-container-wrapper {
        position: relative;
        padding: 0 45px;
        display: inline-block;
    }
    
    /* Panel lateral fijo para scroll */
    .builder-layout {
        display: flex;
        height: calc(100vh - 150px);
        gap: 20px;
    }
    .rack-scroll-area {
        flex: 0 0 780px;
        overflow-y: auto;
        overflow-x: auto;
        padding-right: 10px;
        background: #f4f6f9;
        border-radius: 8px;
        padding: 20px 10px;
    }
    /* Rieles de PDU Verticales como marcadores interactivos */
    .pdu-rail-placeholder {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 32px;
        background: rgba(0, 0, 0, 0.04);
        border: 2px dashed #bbb;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 5;
        transition: all 0.2s;
    }
    .pdu-rail-placeholder:hover {
        background: rgba(40, 167, 69, 0.15);
        border-color: #28a745;
    }
    .pdu-rail-placeholder.left-rail {
        left: -40px;
    }
    .pdu-rail-placeholder.right-rail {
        right: -40px;
    }
    .pdu-rail-placeholder .rail-text {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 9px;
        color: #999;
        font-weight: bold;
        letter-spacing: 2px;
        user-select: none;
    }
    .pdu-rail-placeholder:hover .rail-text {
        color: #28a745;
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
        <!-- RACK VISUAL (FRONT AND REAR SIDE-BY-SIDE) -->
        <div class="rack-scroll-area shadow-sm">
            <div class="d-flex justify-content-center" style="gap: 20px; min-width: 720px;">
                <div class="text-center">
                    <h5 class="font-weight-bold text-secondary mb-2"><i class="fas fa-desktop"></i> FRENTE (FRONT)</h5>
                    <div class="rack-container-wrapper">
                        <div class="rack-container" id="rack-container-front">
                            <!-- Se llenará con JS -->
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <h5 class="font-weight-bold text-secondary mb-2"><i class="fas fa-tools"></i> DETRÁS (REAR)</h5>
                    <div class="rack-container-wrapper">
                        <div class="rack-container" id="rack-container-rear">
                            <!-- Se llenará con JS -->
                        </div>
                    </div>
                </div>
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
                        <i class="fas fa-hand-pointer fa-3x mb-3 text-primary"></i>
                        <p class="font-weight-bold text-dark">¿Cómo agregar equipos o multitomas (PDUs)?</p>
                        <p class="px-2">Haz clic en cualquier espacio vacío del rack para agregar un equipo o multitoma, o haz clic en uno existente para editarlo.</p>
                        <div class="text-left bg-light p-3 rounded mx-2 mt-4 border" style="font-size: 0.85rem; line-height: 1.4;">
                            <p class="mb-2 text-primary font-weight-bold"><i class="fas fa-plug mr-1 text-warning"></i> Agregar Multitomas / PDUs Verticales (0U):</p>
                            <ol class="pl-3 mb-0 text-secondary">
                                <li class="mb-1">Haz clic en cualquier U libre del rack.</li>
                                <li class="mb-1">En el formulario de la derecha, busca <strong>Tipo de Montaje (Mounting)</strong> y cámbialo a:
                                    <br><span class="badge badge-info mt-1">Vertical A</span> o <span class="badge badge-info mt-1">Vertical B</span> (ubicadas en la parte trasera del rack).
                                </li>
                                <li>Indica la cantidad de tomas (C13, C19 o NEMA) que tiene la multitoma y haz clic en <strong>Guardar Cambios</strong>.</li>
                            </ol>
                        </div>
                    </div>
                    
                    <form id="device-form" class="d-none">
                        <input type="hidden" name="action" value="save_device">
                        <input type="hidden" name="id" id="dev_id" value="0">
                        <input type="hidden" name="rack_id" value="<?php echo $rack_id; ?>">
                        
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-2"><i class="fas fa-link mr-1"></i> Vincular con la CMDB</h5>
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label>Equipo CMDB CI (Opcional)</label>
                                <select name="cmdb_reference" id="dev_cmdb_reference" class="form-control select2-cmdb">
                                    <option value="">-- No vinculado (Creación manual) --</option>
                                </select>
                                <small class="form-text text-muted">Vincule a un CI existente para sincronizar automáticamente el nombre, imágenes y propiedades.</small>
                            </div>
                        </div>

                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-2">Origen de Datos (Alternativo)</h5>
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
                                    <select id="zabbix_host" class="form-control select2-zabbix" disabled>
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
                            <div class="col-md-6 form-group">
                                <label>Orientación / Lado</label>
                                <select name="orientation" id="dev_orientation" class="form-control">
                                    <option value="front">Frente (Front)</option>
                                    <option value="rear">Detrás (Rear)</option>
                                    <option value="both">Ambos Lados (Both)</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Profundidad (Depth)</label>
                                <select name="depth" id="dev_depth" class="form-control">
                                    <option value="full">Completa (Full)</option>
                                    <option value="half">Media (1/2)</option>
                                    <option value="third">Un Tercio (1/3)</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Tipo de Montaje (Mounting)</label>
                                <select name="mounting" id="dev_mounting" class="form-control">
                                    <option value="horizontal">Horizontal (U Estándar)</option>
                                    <option value="vertical_left">Vertical A (PDU Lateral Externa - Detrás)</option>
                                    <option value="vertical_right">Vertical B (PDU Lateral Interna - Detrás)</option>
                                </select>
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
                        <!-- Tomas de Energía para PDU -->
                        <div id="pdu-outlets-section" class="d-none">
                            <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Tomas de Energía (Outlets de PDU)</h5>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>Tomas C13</label>
                                    <input type="number" name="outlets_c13" id="dev_outlets_c13" class="form-control" placeholder="Ej. 24" min="0">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Tomas C19</label>
                                    <input type="number" name="outlets_c19" id="dev_outlets_c19" class="form-control" placeholder="Ej. 4" min="0">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Tomas NEMA</label>
                                    <input type="number" name="outlets_nema" id="dev_outlets_nema" class="form-control" placeholder="Ej. 0" min="0">
                                </div>
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
    let cmdbInstances = [];
    
    // Drag & Drop HTML5 Helpers
    function allowDrop(ev) {
        ev.preventDefault();
        $(ev.target).closest('.rack-slot').css('background-color', '#d4edda');
    }

    function dragLeave(ev) {
        $(ev.target).closest('.rack-slot').css('background-color', '');
    }

    function drag(ev, devId) {
        ev.dataTransfer.setData("text", devId);
    }

    function drop(ev, targetU, side) {
        ev.preventDefault();
        $('.rack-slot').css('background-color', '');
        let devId = ev.dataTransfer.getData("text");
        if (devId) {
            $.post('api.php', {
                action: 'update_device_u_position',
                id: devId,
                start_u: targetU,
                orientation: side
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadDevices();
                } else {
                    toastr.error(res.message);
                }
            }, 'json').fail(function() {
                toastr.error('Error al mover el equipo.');
            });
        }
    }
    
    $(document).ready(function() {
        $('#rack-container-front').css('position', 'relative');
        $('#rack-container-rear').css('position', 'relative');
        
        // Inicializar Select2 para CMDB
        $('.select2-cmdb').select2({
            theme: 'bootstrap4',
            placeholder: 'Seleccione un CI para vincular...'
        });

        // Inicializar Select2 para Zabbix
        $('.select2-zabbix').select2({
            theme: 'bootstrap4',
            placeholder: 'Seleccione un equipo de Zabbix...'
        });
        
        buildRackGrid();
        loadCMDBInstances();
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
            loadZabbixHosts(gid || '');
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
                let name = selectedOption.data('name') || '';
                let ip = selectedOption.data('ip') || '';
                let make = selectedOption.data('make') || '';
                let model = selectedOption.data('model') || '';
                let serial = selectedOption.data('serial') || '';
                let assetTag = selectedOption.data('asset-tag') || '';
                let owner = selectedOption.data('owner') || '';
                let notes = selectedOption.data('notes') || '';
                
                $('#dev_name').val(name);
                $('#dev_ip').val(ip);
                $('#dev_make').val(make);
                $('#dev_model').val(model);
                $('#dev_serial_number').val(serial);
                $('#dev_asset_tag').val(assetTag);
                $('#dev_owner').val(owner);
                $('#dev_server_function').val(notes);
                
                toastr.success('Datos cargados desde Zabbix. Revisa y completa el formulario.');
            }
        });

        // Lógica CMDB Vincular
        $('#dev_cmdb_reference').on('change', function() {
            let ciId = $(this).val();
            if (ciId) {
                let ci = cmdbInstances.find(c => c.id == ciId);
                if (ci) {
                    $('#dev_name').val(ci.hostname);
                    
                    let attrs = {};
                    try { attrs = JSON.parse(ci.attributes_json); } catch(e) {}
                    
                    let ip = ci.ip_address || attrs.ip_address || attrs.ip || '';
                    $('#dev_ip').val(ip);
                    
                    $('#dev_make').val(attrs.marca || attrs.make || '');
                    $('#dev_model').val(attrs.modelo || attrs.model || '');
                    $('#dev_serial_number').val(attrs.serial_number || attrs.serial || '');
                    $('#dev_asset_tag').val(attrs.asset_tag || '');
                    $('#dev_owner').val(attrs.owner || attrs.propietario || '');
                    
                    if (attrs.rack_height_u) $('#dev_height_u').val(attrs.rack_height_u);
                    if (attrs.rack_orientation) $('#dev_orientation').val(attrs.rack_orientation);
                    if (attrs.rack_color) $('#dev_color').val(attrs.rack_color);
                    if (attrs.rack_depth) $('#dev_depth').val(attrs.rack_depth);
                    if (attrs.rack_mounting) $('#dev_mounting').val(attrs.rack_mounting);
                    if (attrs.rack_outlets_c13) $('#dev_outlets_c13').val(attrs.rack_outlets_c13);
                    if (attrs.rack_outlets_c19) $('#dev_outlets_c19').val(attrs.rack_outlets_c19);
                    if (attrs.rack_outlets_nema) $('#dev_outlets_nema').val(attrs.rack_outlets_nema);
                    togglePduOutletsSection();
                }
            }
        });

        $('#dev_mounting').on('change', togglePduOutletsSection);
    });

    function loadCMDBInstances() {
        $.get('../api_ci.php?action=get_instances', function(res) {
            if (res.success) {
                cmdbInstances = res.data;
                let sel = $('#dev_cmdb_reference');
                sel.empty().append('<option value="">-- No vinculado (Creación manual) --</option>');
                res.data.forEach(ci => {
                    sel.append(`<option value="${ci.id}">${ci.hostname} (${ci.category_name})</option>`);
                });
            }
        }, 'json');
    }

    function loadZabbixHostgroups() {
        let sel = $('#zabbix_hostgroup');
        sel.html('<option value="">Cargando...</option>').prop('disabled', true);
        $.get('api.php?action=get_zabbix_hostgroups', function(res) {
            if (res.success) {
                let html = '<option value="">-- Todos los Equipos --</option>';
                res.data.forEach(hg => {
                    html += `<option value="${hg.groupid}">${hg.name}</option>`;
                });
                sel.html(html).prop('disabled', false);
                
                // Cargar todos los hosts inicialmente
                loadZabbixHosts('');
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
        
        let url = 'api.php?action=get_zabbix_hosts';
        if (groupid) {
            url += '&groupid=' + groupid;
        }
        
        $.get(url, function(res) {
            if (res.success) {
                let html = '<option value="">-- Seleccionar Host --</option>';
                res.data.forEach(h => {
                    html += `<option value="${h.hostid}" 
                        data-name="${h.name}" 
                        data-ip="${h.ip || ''}"
                        data-make="${h.make || ''}"
                        data-model="${h.model || ''}"
                        data-serial="${h.serial || ''}"
                        data-asset-tag="${h.asset_tag || ''}"
                        data-owner="${h.owner || ''}"
                        data-notes="${h.notes || ''}">${h.name} (${h.ip || 'Sin IP'})</option>`;
                });
                sel.html(html).prop('disabled', false);
                sel.trigger('change');
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
        let frontContainer = $('#rack-container-front');
        let rearContainer = $('#rack-container-rear');
        frontContainer.empty();
        rearContainer.empty();
        
        rearContainer.append(`
            <div class="pdu-rail-placeholder left-rail" onclick="openCreateForm(1, 'rear', 'vertical_left')" title="Click para agregar PDU A (Lateral Externa)">
                <div class="rail-text">PDU A (EXTERNA)</div>
            </div>
            <div class="pdu-rail-placeholder right-rail" onclick="openCreateForm(1, 'rear', 'vertical_right')" title="Click para agregar PDU B (Lateral Interna)">
                <div class="rail-text">PDU B (INTERNA)</div>
            </div>
        `);
        
        if (NUMBERING_DIR === 'UP') {
            for (let u = 1; u <= TOTAL_U; u++) {
                let uHtmlFront = `
                    <div class="rack-unit" data-u="${u}">
                        <div class="rack-u-label">${u}</div>
                        <div class="rack-slot" onclick="openCreateForm(${u}, 'front')" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, ${u}, 'front')"></div>
                    </div>
                `;
                let uHtmlRear = `
                    <div class="rack-unit" data-u="${u}">
                        <div class="rack-u-label">${u}</div>
                        <div class="rack-slot" onclick="openCreateForm(${u}, 'rear')" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, ${u}, 'rear')"></div>
                    </div>
                `;
                frontContainer.append(uHtmlFront);
                rearContainer.append(uHtmlRear);
            }
        } else {
            for (let u = TOTAL_U; u >= 1; u--) {
                let uHtmlFront = `
                    <div class="rack-unit" data-u="${u}">
                        <div class="rack-u-label">${u}</div>
                        <div class="rack-slot" onclick="openCreateForm(${u}, 'front')" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, ${u}, 'front')"></div>
                    </div>
                `;
                let uHtmlRear = `
                    <div class="rack-unit" data-u="${u}">
                        <div class="rack-u-label">${u}</div>
                        <div class="rack-slot" onclick="openCreateForm(${u}, 'rear')" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, ${u}, 'rear')"></div>
                    </div>
                `;
                frontContainer.append(uHtmlFront);
                rearContainer.append(uHtmlRear);
            }
        }
    }

    function loadDevices() {
        $.get(`api.php?action=get_devices&rack_id=${RACK_ID}`, function(res) {
            if (res.success) {
                devices = res.data;
                renderDevices();
            }
        });
    }

    function renderDevices() {
        $('.device').remove(); // Limpiar existentes
        
        devices.forEach(dev => {
            let startU = parseInt(dev.start_u);
            let heightU = parseInt(dev.height_u);
            let orientation = dev.orientation || 'front';
            let depth = dev.details.depth || 'full';
            let mounting = dev.details.mounting || 'horizontal';
            
            if (startU > TOTAL_U) return; // Fuera de rango
            
            let heightPx = heightU * U_HEIGHT_PX;
            let positionStyle = '';
            
            if (NUMBERING_DIR === 'UP') {
                let topPx = (startU - 1) * U_HEIGHT_PX;
                positionStyle = `top: ${topPx}px;`;
            } else {
                let bottomPx = (startU - 1) * U_HEIGHT_PX;
                positionStyle = `bottom: ${bottomPx}px;`;
            }
            
            let bgColor = dev.details.color ? dev.details.color : '#2a2a2a';
            
            // Si la profundidad es completa (full), renderizar en ambos lados automáticamente.
            // Si es parcial (1/2 o 1/3), se renderiza en la orientación especificada.
            let showFront = false;
            let showRear = false;
            
            if (depth === 'full') {
                showFront = true;
                showRear = true;
            } else {
                if (orientation === 'front' || orientation === 'both') {
                    showFront = true;
                }
                if (orientation === 'rear' || orientation === 'both') {
                    showRear = true;
                }
            }
            
            if (mounting === 'vertical_left' || mounting === 'vertical_right') {
                let isLeft = (mounting === 'vertical_left');
                let sideClass = isLeft ? 'left-pdu' : 'right-pdu';
                let pduColorClass = (bgColor === '#2a2a2a' || bgColor === '#000000' || bgColor === 'rgb(42, 42, 42)') ? 'pdu-dark' : '';
                
                // Generar puntos de salida de energía según la cantidad ingresada o la altura por defecto
                let outletCount = parseInt(dev.details.outlets_c13 || 0) + 
                                  parseInt(dev.details.outlets_c19 || 0) + 
                                  parseInt(dev.details.outlets_nema || 0);
                if (outletCount <= 0) {
                    outletCount = Math.max(2, heightU * 2);
                }
                let outletsHtml = '';
                for (let i = 0; i < outletCount; i++) {
                    outletsHtml += '<div class="pdu-outlet"></div>';
                }
                
                let pduHtml = `
                    <div class="device vertical-pdu ${sideClass} ${pduColorClass}" 
                         draggable="true"
                         ondragstart="drag(event, ${dev.id})"
                         style="${positionStyle} height: ${heightPx}px; background-color: ${bgColor};" 
                         onclick="openEditForm(${dev.id})"
                         title="${dev.name} (${dev.details.make || ''} ${dev.details.model || ''}) [PDU Vertical]">
                         <div class="pdu-outlets-container">
                             ${outletsHtml}
                         </div>
                         <div class="pdu-text" style="color: ${pduColorClass ? '#fff' : '#333'}">${dev.name}</div>
                    </div>
                `;
                
                // Render only on the rear container
                $('#rack-container-rear').append(pduHtml);
            } else {
                let depthLabel = '';
                let depthClass = '';
                if (depth === 'half') {
                    depthLabel = ' [1/2 Prof]';
                    depthClass = 'depth-half';
                }
                if (depth === 'third') {
                    depthLabel = ' [1/3 Prof]';
                    depthClass = 'depth-third';
                }
                
                // Render on front
                if (showFront) {
                    let bgImageStyle = '';
                    let frontImage = dev.cmdb_imagen_frontal || '';
                    if (frontImage) {
                        if (!frontImage.startsWith('http') && !frontImage.startsWith('/')) {
                            frontImage = '../' + frontImage;
                        }
                        bgImageStyle = `background-image: url('${frontImage}'); background-size: cover; background-position: center; border: 1px solid #ffc107;`;
                    }
                    
                    let devHtml = `
                        <div class="device ${depthClass}" 
                             draggable="true"
                             ondragstart="drag(event, ${dev.id})"
                             style="${positionStyle} height: ${heightPx}px; background-color: ${bgColor}; ${bgImageStyle}" 
                             onclick="openEditForm(${dev.id})"
                             title="${dev.name} (${dev.details.make || ''} ${dev.details.model || ''})${depthLabel}">
                             ${!frontImage ? `<div class="dev-name">${dev.name}</div>` : `<div class="dev-name" style="background: rgba(0,0,0,0.6); width: 100%; text-align: center; font-size: 10px; padding: 2px 0;">${dev.name}</div>`}
                             ${heightU > 1 && !frontImage ? `<div class="dev-meta">${dev.details.make || ''} ${dev.details.model || ''}${depthLabel}</div>` : (depthLabel && !frontImage ? `<div class="dev-meta">${depthLabel}</div>` : '')}
                        </div>
                    `;
                    $('#rack-container-front').append(devHtml);
                }
                
                // Render on rear
                if (showRear) {
                    let bgImageStyle = '';
                    let rearImage = dev.cmdb_imagen_trasera || '';
                    if (rearImage) {
                        if (!rearImage.startsWith('http') && !rearImage.startsWith('/')) {
                            rearImage = '../' + rearImage;
                        }
                        bgImageStyle = `background-image: url('${rearImage}'); background-size: cover; background-position: center; border: 1px solid #17a2b8;`;
                    }
                    
                    let devHtml = `
                        <div class="device ${depthClass}" 
                             draggable="true"
                             ondragstart="drag(event, ${dev.id})"
                             style="${positionStyle} height: ${heightPx}px; background-color: ${bgColor}; ${bgImageStyle}" 
                             onclick="openEditForm(${dev.id})"
                             title="${dev.name} (${dev.details.make || ''} ${dev.details.model || ''})${depthLabel}">
                             ${!rearImage ? `<div class="dev-name">${dev.name}</div>` : `<div class="dev-name" style="background: rgba(0,0,0,0.6); width: 100%; text-align: center; font-size: 10px; padding: 2px 0;">${dev.name}</div>`}
                             ${heightU > 1 && !rearImage ? `<div class="dev-meta">${dev.details.make || ''} ${dev.details.model || ''}${depthLabel}</div>` : (depthLabel && !rearImage ? `<div class="dev-meta">${depthLabel}</div>` : '')}
                        </div>
                    `;
                    $('#rack-container-rear').append(devHtml);
                }
            }
        });
    }

    function togglePduOutletsSection() {
        let mounting = $('#dev_mounting').val();
        if (mounting === 'vertical_left' || mounting === 'vertical_right') {
            $('#pdu-outlets-section').removeClass('d-none');
        } else {
            $('#pdu-outlets-section').addClass('d-none');
        }
    }

    function openCreateForm(u, orientation, forcedMounting) {
        resetForm();
        $('#welcome-msg').addClass('d-none');
        $('#device-form').removeClass('d-none');
        $('#form-title').html('<i class="fas fa-plus-circle mr-2 text-success"></i> Agregar Equipo');
        
        $('#dev_id').val(0);
        $('#dev_start_u').val(u);
        if (forcedMounting) {
            $('#dev_height_u').val(TOTAL_U);
            $('#dev_mounting').val(forcedMounting);
        } else {
            $('#dev_height_u').val(1);
            $('#dev_mounting').val('horizontal');
        }
        $('#dev_color').val('#2a2a2a');
        $('#dev_orientation').val(orientation || 'front');
        $('#dev_depth').val('full');
        
        $('#dev_outlets_c13').val('');
        $('#dev_outlets_c19').val('');
        $('#dev_outlets_nema').val('');
        togglePduOutletsSection();
        
        // Reset Zabbix toggle
        $('#source_manual').prop('checked', true).parent().addClass('active');
        $('#source_zabbix').prop('checked', false).parent().removeClass('active');
        $('#zabbix-selection-area').addClass('d-none');
        
        // Reset CMDB Select2
        $('#dev_cmdb_reference').val('').trigger('change.select2');
        
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
        $('#dev_orientation').val(dev.orientation || 'front');
        $('#dev_depth').val(dev.details.depth || 'full');
        $('#dev_mounting').val(dev.details.mounting || 'horizontal');
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
        
        $('#dev_outlets_c13').val(dev.details.outlets_c13 || '');
        $('#dev_outlets_c19').val(dev.details.outlets_c19 || '');
        $('#dev_outlets_nema').val(dev.details.outlets_nema || '');
        togglePduOutletsSection();
        
        // Select CMDB reference in Select2
        if (dev.cmdb_reference) {
            $('#dev_cmdb_reference').val(dev.cmdb_reference).trigger('change.select2');
        } else {
            $('#dev_cmdb_reference').val('').trigger('change.select2');
        }
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
        $('#zabbix_host').val('').trigger('change.select2').prop('disabled', true);
        $('#btn-fetch-zabbix').prop('disabled', true);
        
        // Reset CMDB Select2
        $('#dev_cmdb_reference').val('').trigger('change.select2');
        $('#dev_depth').val('full');
        $('#dev_mounting').val('horizontal');
        
        $('#dev_outlets_c13').val('');
        $('#dev_outlets_c19').val('');
        $('#dev_outlets_nema').val('');
        togglePduOutletsSection();
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
