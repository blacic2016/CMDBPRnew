<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "Gestión de Inventario de Equipos";
$page_icon = "fas fa-boxes text-warning";
$hide_content_header = true;
require_once __DIR__ . '/../../../partials/header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

<style>
    /* Scoped styles for the inventory management page to prevent bleeding */
    /* Break out of standard AdminLTE page margins/paddings to cover 100% of the screen */
    .content-wrapper > .content,
    .content-wrapper > .content > .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .wireless-inventario-wrapper {
        --primary-bg: #ffffff; 
        --secondary-bg: #f4f6f9; 
        --accent-bg: #e9ecef;
        --card-bg: #ffffff; 
        --text-primary: #000000; 
        --text-secondary: #495057;
        --accent-color: #ff5c05; 
        --success-color: #28a745; 
        --warning-color: #ffc107;
        --danger-color: #dc3545; 
        --orange-color: #ff5c05; 
        --border-color: #dee2e6;
        --button-text-color: #ffffff;
        background: linear-gradient(135deg, var(--primary-bg) 0%, var(--secondary-bg) 100%);
        color: var(--text-primary);
        font-family: 'Inter', sans-serif;
        padding: 20px;
        border-radius: 8px;
    }
    .wireless-inventario-wrapper.dark-mode {
        --primary-bg: #101B31; 
        --secondary-bg: #0b1323; 
        --accent-bg: #1f3050;
        --card-bg: #1a2844; 
        --text-primary: #ffffff; 
        --text-secondary: #cddbe8;
        --accent-color: #ff5c05; 
        --success-color: #c0da20; 
        --warning-color: #ffb800;
        --danger-color: #ff4757; 
        --orange-color: #ff5c05; 
        --border-color: #1f3050;
        --button-text-color: #ffffff;
    }
    .header-inventory { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px; 
        padding: 20px; 
        background: var(--card-bg); 
        border-radius: 15px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
    }
    .header-inventory h1 { font-size: 2em; color: var(--accent-color); margin: 0; }
    .table-inventory-container { 
        background: var(--card-bg); 
        padding: 25px; 
        border-radius: 15px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
        border: 1px solid var(--border-color); 
        overflow-x: auto; 
    }
    
    .wireless-inventario-wrapper table.dataTable {
        border-collapse: collapse !important;
        border: 1px solid var(--border-color) !important;
    }
    .wireless-inventario-wrapper table.dataTable thead th, 
    .wireless-inventario-wrapper table.dataTable tbody td { 
        color: var(--text-primary); 
        border-bottom: 1px solid var(--border-color); 
        border-right: 1px solid var(--border-color); 
        padding: 10px; 
    }
    .wireless-inventario-wrapper table.dataTable thead th { background: var(--accent-bg); }
    
    .wireless-inventario-wrapper .action-btn { 
        padding: 5px 10px; 
        margin: 2px; 
        border: none; 
        border-radius: 5px; 
        cursor: pointer; 
        font-size: 0.85em; 
        transition: background-color 0.3s ease; 
    }
    .wireless-inventario-wrapper .btn-edit { background-color: var(--accent-color); color: var(--button-text-color); }
    .wireless-inventario-wrapper .btn-delete { background-color: var(--danger-color); color: #fff; }
    .wireless-inventario-wrapper .btn-create { background-color: var(--success-color); color: var(--button-text-color); }
    .wireless-inventario-wrapper .btn-edit:hover { background-color: #00aaff; } 
    .wireless-inventario-wrapper .btn-delete:hover { background-color: #c0392b; } 
    .wireless-inventario-wrapper .btn-create:hover { background-color: #00c766; }
    
    /* Modales */
    .wireless-inventario-wrapper .modal { 
        display: none; 
        position: fixed; 
        z-index: 2000; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.7); 
    }
    .wireless-inventario-wrapper .modal-content { 
        background-color: var(--card-bg); 
        margin: 5% auto; 
        padding: 30px; 
        border: 1px solid var(--border-color); 
        width: 90%; 
        max-width: 600px; 
        border-radius: 15px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.5); 
        animation: fadeIn 0.3s; 
    }
    @keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
    .wireless-inventario-wrapper .modal-content input, 
    .wireless-inventario-wrapper .modal-content textarea, 
    .wireless-inventario-wrapper .modal-content select { 
        width: 100%; 
        padding: 10px; 
        margin: 8px 0 15px 0; 
        border: 1px solid var(--border-color); 
        border-radius: 4px; 
        box-sizing: border-box; 
        background: var(--accent-bg); 
        color: var(--text-primary); 
    }
    .wireless-inventario-wrapper .modal-content input:focus, 
    .wireless-inventario-wrapper .modal-content textarea:focus, 
    .wireless-inventario-wrapper .modal-content select:focus { 
        border-color: var(--accent-color); 
        outline: none; 
    }
    .wireless-inventario-wrapper .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    .custom-filters { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
    .custom-filters select { padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--accent-bg); color: var(--text-primary); }

    .read-only-field[readonly] {
        background: var(--secondary-bg); 
        cursor: not-allowed;
        opacity: 0.7;
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Clientes</li>
                    <li class="breadcrumb-item active">SONDA</li>
                    <li class="breadcrumb-item active">iin</li>
                    <li class="breadcrumb-item active">Inventario Wireless</li>
                </ol>
            </div>
        </div>
    </div>
</div>

                <div class="wireless-inventario-wrapper">
                    <div class="header-inventory">
                        <div></div>
                        <button id="btnCrearEquipo" class="action-btn btn-create"><i class="fas fa-plus mr-1"></i> Crear Nuevo Equipo</button>
                    </div>

                    <div class="table-inventory-container">
                        <h3>Filtros Personalizados</h3>
                        <div class="custom-filters">
                            <select id="filterUnidad">
                                <option value="">Todas las Unidades</option>
                            </select>
                            <select id="filterSubUnidad">
                                <option value="">Todas las Sub Unidades</option>
                            </select>
                            <select id="filterEstado">
                                <option value="">Todos los Estados</option>
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="DESACTIVADO">DESACTIVADO</option>
                            </select>
                        </div>

                        <table id="tablaInventario" class="display nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th> 
                                    <th>NRO</th> 
                                    <th>IP</th>
                                    <th>CIUDAD</th>
                                    <th>UNIDAD</th>
                                    <th>SUB UNIDAD</th>
                                    <th>NOMBRE EQUIPO</th>
                                    <th>HOSTNAME</th>
                                    <th>OBSERVACIONES</th>
                                    <th>ESTADO</th>
                                    <th>F. CREACIÓN</th>
                                    <th>F. ACTUALIZACIÓN</th>
                                    <th>ACCIONES</th> 
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    
                    <!-- Modales -->
                    <div id="modalGestion" class="modal">
                        <div class="modal-content">
                            <h2 id="modalTitle">Crear/Actualizar Equipo</h2>
                            <form id="formGestion">
                                <input type="hidden" id="id" name="id"> 
                                
                                <label for="ip">IP *</label>
                                <input type="text" id="ip" name="ip" required>

                                <label for="unidad_modal">Unidad *</label>
                                <input type="text" id="unidad_modal" name="unidad" required>

                                <label for="sub_unidad_modal">Sub Unidad *</label>
                                <input type="text" id="sub_unidad_modal" name="sub_unidad" required>
                                
                                <label for="nombre_equipo">Nombre del Equipo *</label>
                                <input type="text" id="nombre_equipo" name="nombre_equipo" required>

                                <label for="observaciones">Observaciones</label>
                                <textarea id="observaciones" name="observaciones"></textarea>
                                
                                <hr style="margin: 15px 0; border-color: var(--border-color);">

                                <label for="hostname">Hostname</label>
                                <input type="text" id="hostname" name="hostname" class="read-only-field">
                                
                                <label for="nro">NRO (Campo Secundario)</label>
                                <input type="text" id="nro" name="nro" class="read-only-field">
                                
                                <label for="fecha">Fecha</label>
                                <input type="text" id="fecha" name="fecha" class="read-only-field" placeholder="Ej: 2025-10-30">

                                <label for="ciudad">Ciudad</label>
                                <input type="text" id="ciudad" name="ciudad" class="read-only-field">

                                <label for="app_seguridad">App Seguridad</label>
                                <input type="text" id="app_seguridad" name="app_seguridad" class="read-only-field">
                                
                                <label for="nessus">Nessus</label>
                                <input type="text" id="nessus" name="nessus" class="read-only-field">
                                
                                <label for="aranda">Aranda</label>
                                <input type="text" id="aranda" name="aranda" class="read-only-field">
                                
                                <label for="hx_v35_31_28">HX_V35_31_28</label>
                                <input type="text" id="hx_v35_31_28" name="hx_v35_31_28" class="read-only-field">
                                
                                <label for="dominio">Dominio</label>
                                <input type="text" id="dominio" name="dominio" class="read-only-field">

                                <hr style="margin: 15px 0; border-color: var(--border-color);">

                                <label for="password_admin">Código de Autorización</label>
                                <input type="password" id="password_admin" name="password_admin" required placeholder="Código secreto">

                                <div class="modal-footer">
                                    <button type="button" class="action-btn" onclick="document.getElementById('modalGestion').style.display='none'">Cancelar</button>
                                    <button type="submit" class="action-btn btn-create" id="btnGuardar">Guardar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div id="modalConfirmacion" class="modal">
                        <div class="modal-content">
                            <h2>Confirmar Desactivación</h2>
                            <p>¿Está seguro de que desea <strong>DESACTIVAR</strong> el equipo ID: <span id="confirmId"></span>?</p>
                            <p>El estado actual cambiará de <strong>ACTIVO</strong> a <strong>DESACTIVADO</strong>.</p>
                            
                            <label for="password_delete">Código de Autorización</label>
                            <input type="password" id="password_delete" required placeholder="Código secreto">

                            <div class="modal-footer">
                                <button type="button" class="action-btn" onclick="document.getElementById('modalConfirmacion').style.display='none'">Cancelar</button>
                                <button type="button" class="action-btn btn-delete" id="btnConfirmarBorrado">Desactivar</button>
                            </div>
                        </div>
                    </div>
                </div>

<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="wireless_js/modal_gestion.js"></script>

<script>
    $(document).ready(function() {
        const savedThemeIndex = localStorage.getItem('selectedThemeIndex') || 0;
        const themes = ['', 'dark-mode', 'gray-mode'];
        if (themes[savedThemeIndex]) {
            $('.wireless-inventario-wrapper').addClass(themes[savedThemeIndex]);
        }
    });
</script>

<?php
require_once __DIR__ . '/../../../partials/footer.php';
?>