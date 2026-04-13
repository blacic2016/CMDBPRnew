<?php
/**
 * CMDB VILASECA - Importación Granular (Tabla por Tabla)
 * Ubicación: /var/www/html/VILASECA/CMDBPR/public/import.php
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

require_login();
if (!has_role(['ADMIN', 'SUPER_ADMIN'])) {
    header("HTTP/1.1 403 Forbidden");
    exit('Acceso denegado.');
}

$user = current_user();
$page_title = 'Importación de Inventario';
$sheet_tables = listSheetTables();
$preselected_table = $_GET['table'] ?? '';

require_once __DIR__ . '/partials/header.php';
?>

<style>
    :root {
        --step-active: #007bff;
        --step-completed: #28a745;
        --step-inactive: #dee2e6;
    }
    .step-card { display: none; border-radius: 15px; overflow: hidden; }
    .step-card.active { display: block; animation: fadeIn 0.5s ease; }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .wizard-steps { display: flex; justify-content: space-between; margin-bottom: 2rem; position: relative; }
    .wizard-step { text-align: center; flex: 1; position: relative; z-index: 2; }
    .wizard-steps::before { 
        content: ''; position: absolute; top: 20px; left: 10%; width: 80%; height: 3px; 
        background: var(--step-inactive); z-index: 1; 
    }
    
    .step-num { 
        width: 40px; height: 40px; line-height: 40px; border-radius: 50%; 
        background: var(--step-inactive); display: inline-block; position: relative; 
        z-index: 2; font-weight: bold; font-size: 1.1rem; transition: all 0.3s ease;
        border: 4px solid var(--white);
    }
    .wizard-step.active .step-num { background: var(--step-active); color: white; transform: scale(1.1); box-shadow: 0 0 15px rgba(0,123,255,0.4); }
    .wizard-step.completed .step-num { background: var(--step-completed); color: white; }
    .step-label { display: block; margin-top: 8px; font-size: 0.9rem; color: #6c757d; font-weight: 500; }
    .wizard-step.active .step-label { color: var(--step-active); font-weight: 700; }
    
    .premium-card {
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08) !important;
        transition: transform 0.3s ease;
    }
    .dark-mode .premium-card {
        background: #2c3034;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
    }
    
    .gradient-header {
        background: linear-gradient(135deg, #007bff 0%, #00d2ff 100%);
        color: white;
        padding: 1.5rem;
    }
    .gradient-header-success {
        background: linear-gradient(135deg, #28a745 0%, #83d475 100%);
        color: white;
        padding: 1.5rem;
    }
    
    .dropzone-area {
        border: 2px dashed #007bff;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        background: rgba(0,123,255,0.02);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .dropzone-area:hover {
        background: rgba(0,123,255,0.05);
        border-style: solid;
    }
    
    .table-mapping thead th {
        background: #f8f9fa;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
    }
    .dark-mode .table-mapping thead th {
        background: #343a40;
    }
</style>

<div class="container-fluid py-4">
    <!-- Wizard Header -->
    <div class="card premium-card p-4 mb-4">
        <div class="wizard-steps">
            <div class="wizard-step active" id="badge-step-1">
                <span class="step-num">1</span>
                <span class="step-label">Configuración</span>
            </div>
            <div class="wizard-step" id="badge-step-2">
                <span class="step-num">2</span>
                <span class="step-label">Mapeo de Datos</span>
            </div>
            <div class="wizard-step" id="badge-step-3">
                <span class="step-num">3</span>
                <span class="step-label">Finalización</span>
            </div>
        </div>
    </div>

    <!-- STEP 1: Archivo y Tabla -->
    <div class="card premium-card step-card active" id="step-1">
        <div class="gradient-header">
            <h3 class="card-title mb-0"><i class="fas fa-file-excel mr-2"></i> Paso 1: Selección de Origen</h3>
        </div>
        <div class="card-body p-4">
            <form id="form-step-1">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Tabla Destino en CMDB</label>
                        <select name="tableName" id="destTable" class="form-control form-control-lg select2" required>
                            <option value="">-- Seleccionar Tabla Existante --</option>
                            <?php foreach ($sheet_tables as $table): ?>
                                <option value="<?= htmlspecialchars($table) ?>" <?= ($preselected_table === $table ? 'selected' : '') ?>><?= htmlspecialchars(ucfirst(str_replace('sheet_', '', $table))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="newTableContainer" class="mt-3 <?= $preselected_table ? 'd-none' : '' ?>">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-plus"></i></span>
                                </div>
                                <input type="text" id="newTableName" class="form-control" placeholder="Nombre para nueva categoría (Ej: routers)">
                            </div>
                            <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle mr-1"></i> Esto creará una nueva tabla en el inventario.</small>
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Archivo de Datos (XLSX, XLS)</label>
                        <div class="custom-file">
                            <input type="file" name="file" class="custom-file-input" id="excelFile" accept=".xlsx, .xls" required>
                            <label class="custom-file-label custom-file-label-lg" for="excelFile">Arrastra o selecciona el archivo...</label>
                        </div>
                        <div class="mt-4 p-3 bg-light rounded border">
                            <h6 class="text-warning font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i> Advertencia</h6>
                            <p class="small mb-0">La tabla seleccionada será <strong>sobreescrita</strong>. Asegúrate de que el archivo contiene los datos actualizados.</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white text-right p-4">
            <button type="button" class="btn btn-primary btn-lg px-5 shadow-sm" id="btn-to-step-2">
                Analizar Archivo <i class="fas fa-chevron-right ml-2"></i>
            </button>
        </div>
    </div>

    <!-- STEP 2: Pestaña y Mapeo -->
    <div class="card premium-card step-card" id="step-2">
        <div class="gradient-header">
            <h3 class="card-title mb-0"><i class="fas fa-tasks mr-2"></i> Paso 2: Mapeo de Columnas</h3>
        </div>
        <div class="card-body p-4">
            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="font-weight-bold">Hoja del Libro Excel</label>
                    <select id="excelSheet" class="form-control form-control-lg border-primary"></select>
                </div>
                <div class="col-md-8 text-right align-self-end">
                    <span class="badge badge-info p-2"><i class="fas fa-info-circle mr-1"></i> Desmarca las columnas que no desees importar.</span>
                </div>
            </div>
            
            <div class="table-responsive rounded shadow-sm border">
                <table class="table table-hover table-mapping mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 80px;">Importar</th>
                            <th>Columna en Excel</th>
                            <th>Vista Previa Dato</th>
                            <th>Nombre en Inventario</th>
                        </tr>
                    </thead>
                    <tbody id="mapping-body"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between p-4">
            <button type="button" class="btn btn-outline-secondary btn-lg" id="btn-back-to-1">
                <i class="fas fa-chevron-left mr-2"></i> Regresar
            </button>
            <button type="button" class="btn btn-success btn-lg px-5 shadow-sm" id="btn-execute">
                <i class="fas fa-cloud-upload-alt mr-2"></i> Iniciar Procesamiento
            </button>
        </div>
    </div>

    <!-- STEP 3: Resultado -->
    <div class="card premium-card step-card" id="step-3">
        <div class="gradient-header-success text-center py-5">
            <div class="display-1 mb-3"><i class="fas fa-check-circle"></i></div>
            <h1 class="font-weight-bold">¡Importación Exitosa!</h1>
        </div>
        <div class="card-body p-5 text-center" id="result-content">
            <!-- Se llena con JS -->
        </div>
        <div class="card-footer bg-white text-center p-5">
            <a href="import.php" class="btn btn-primary btn-lg shadow-sm">
                <i class="fas fa-plus-circle mr-2"></i> Nueva Importación
            </a>
            <a href="dashboard.php" class="btn btn-outline-dark btn-lg ml-3 shadow-sm">
                <i class="fas fa-home mr-2"></i> Panel Principal
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
$(document).ready(function() {
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    let tempFileName = '';
    let excelMetadata = null;

    // Mostrar nombre de archivo
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Toggle para nueva tabla
    $('#destTable').on('change', function() {
        if ($(this).val() === "") {
            $('#newTableContainer').removeClass('d-none');
        } else {
            $('#newTableContainer').addClass('d-none');
            $('#newTableName').val('');
        }
    });

    // Paso 1 -> Paso 2
    $('#btn-to-step-2').on('click', function() {
        const file = $('#excelFile')[0].files[0];
        const table = $('#destTable').val() || $('#newTableName').val();

        if (!file || !table) {
            Swal.fire('Atención', 'Por favor selecciona un archivo y una tabla de destino.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_excel_metadata');
        formData.append('csrf_token', csrfToken);
        formData.append('file', file);

        Swal.fire({
            title: 'Analizando archivo...',
            text: 'Descubriendo estructura del Excel.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api_action.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp) {
                Swal.close();
                if (resp.success) {
                    tempFileName = resp.tempFile;
                    excelMetadata = resp.metadata;
                    
                    let options = '';
                    excelMetadata.forEach((m, idx) => {
                        options += `<option value="${m.sheetName}" data-idx="${idx}">${m.sheetName}</option>`;
                    });
                    $('#excelSheet').html(options).trigger('change');

                    $('#step-1').removeClass('active');
                    $('#step-2').addClass('active');
                    $('#badge-step-1').removeClass('active').addClass('completed');
                    $('#badge-step-2').addClass('active');
                    window.scrollTo(0,0);
                } else {
                    Swal.fire('Error', resp.error, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo procesar el archivo. Revisa el tamaño y formato.', 'error');
            }
        });
    });

    // Cambio de hoja
    $('#excelSheet').on('change', function() {
        const idx = $(this).find(':selected').data('idx');
        const meta = excelMetadata[idx];
        let html = '';

        meta.columns.forEach((col, cIdx) => {
            if (!col) return;
            let suggestion = col.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
            if (suggestion === 'id') suggestion = 'item_id';

            html += `
                <tr>
                    <td class="text-center align-middle">
                        <div class="custom-control custom-checkbox custom-control-lg">
                            <input type="checkbox" class="custom-control-input col-include" id="col_${cIdx}" checked data-idx="${cIdx}">
                            <label class="custom-control-label" for="col_${cIdx}"></label>
                        </div>
                    </td>
                    <td class="align-middle font-weight-bold">${col}</td>
                    <td class="align-middle"><code class="text-muted">${meta.sample[cIdx] || ''}</code></td>
                    <td class="align-middle">
                        <input type="text" class="form-control form-control-sm border-info col-db-name" value="${suggestion}">
                    </td>
                </tr>
            `;
        });
        $('#mapping-body').html(html);
    });

    $('#btn-back-to-1').on('click', function() {
        $('#step-2').removeClass('active');
        $('#step-1').addClass('active');
        $('#badge-step-2').removeClass('active');
        $('#badge-step-1').removeClass('completed').addClass('active');
    });

    // Ejecución final
    $('#btn-execute').on('click', function() {
        Swal.fire({
            title: '¿Confirmar Importación?',
            text: "Se reemplazará la categoría seleccionada con estos nuevos datos.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, importar ahora',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                executeImport();
            }
        });
    });

    function executeImport() {
        const mapping = {};
        $('#mapping-body tr').each(function() {
            const check = $(this).find('.col-include');
            if (check.is(':checked')) {
                const idx = check.data('idx');
                const dbName = $(this).find('.col-db-name').val();
                mapping[idx] = dbName;
            }
        });

        const table = $('#destTable').val() || $('#newTableName').val();
        const sheet = $('#excelSheet').val();

        Swal.fire({
            title: 'Procesando datos...',
            text: 'Importando registros al inventario CMDB.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api_action.php',
            type: 'POST',
            data: {
                action: 'execute_mapped_import',
                csrf_token: csrfToken,
                tempFile: tempFileName,
                tableName: table,
                sheetName: sheet,
                mapping: mapping
            },
            success: function(resp) {
                if (resp.success) {
                    const targetTable = resp.tableName || table;
                    $('#result-content').html(`
                        <p class="lead mb-4">La operación se completó exitosamente.</p>
                        <div class="row justify-content-center">
                            <div class="col-md-4">
                                <div class="p-4 bg-light rounded shadow-sm border">
                                    <h4 class="font-weight-bold text-success">${resp.added}</h4>
                                    <p class="text-muted mb-0">Registros Importados</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-4 bg-light rounded shadow-sm border">
                                    <h4 class="font-weight-bold text-info">${targetTable.replace('sheet_', '')}</h4>
                                    <p class="text-muted mb-0">Categoría Destino</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5">
                            <a href="cmdb.php?name=${encodeURIComponent(targetTable)}" class="btn btn-success btn-lg px-5 shadow-sm">
                                <i class="fas fa-table mr-2"></i> Ver Inventario Actualizado
                            </a>
                        </div>
                    `);

                    $('#step-2').removeClass('active');
                    $('#step-3').addClass('active');
                    $('#badge-step-2').removeClass('active').addClass('completed');
                    $('#badge-step-3').addClass('active');
                    window.scrollTo(0,0);
                    
                    if (resp.errors && resp.errors.length > 0) {
                        toastr.warning('Algunas filas tuvieron errores menores.');
                    }
                } else {
                    Swal.fire('Error Fatal', resp.error, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Ocurrió un error crítico durante la importación.', 'error');
            }
        });
    }
});
</script>
</body>
</html>