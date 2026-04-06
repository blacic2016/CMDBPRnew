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
$page_title = 'Importación Granular CMDB';
$sheet_tables = listSheetTables();
$preselected_table = $_GET['table'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
  <style>
    .step-card { display: none; }
    .step-card.active { display: block; }
    .wizard-steps { display: flex; justify-content: space-between; margin-bottom: 2rem; }
    .wizard-step { text-align: center; flex: 1; position: relative; }
    .wizard-step::after { content: ''; position: absolute; top: 15px; left: 50%; width: 100%; height: 2px; background: #dee2e6; z-index: 1; }
    .wizard-step:last-child::after { display: none; }
    .step-num { width: 30px; height: 30px; line-height: 30px; border-radius: 50%; background: #dee2e6; display: inline-block; position: relative; z-index: 2; font-weight: bold; }
    .wizard-step.active .step-num { background: #007bff; color: white; }
    .wizard-step.completed .step-num { background: #28a745; color: white; }
    .step-label { display: block; margin-top: 5px; font-size: 0.85rem; color: #6c757d; }
    .wizard-step.active .step-label { color: #007bff; font-weight: bold; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li></ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#"><i class="far fa-user"></i> <?= htmlspecialchars($user['username'] ?? 'Usuario') ?></a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión</a>
        </div>
      </li>
    </ul>
  </nav>

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><?= htmlspecialchars($page_title) ?></h1></div></section>

    <section class="content">
      <div class="container-fluid">

        <!-- Wizard Header -->
        <div class="card p-4 mb-4 shadow-sm">
            <div class="wizard-steps">
                <div class="wizard-step active" id="badge-step-1">
                    <span class="step-num">1</span>
                    <span class="step-label">Archivo y Tabla</span>
                </div>
                <div class="wizard-step" id="badge-step-2">
                    <span class="step-num">2</span>
                    <span class="step-label">Pestaña y Mapeo</span>
                </div>
                <div class="wizard-step" id="badge-step-3">
                    <span class="step-num">3</span>
                    <span class="step-label">Resultado</span>
                </div>
            </div>
        </div>

        <!-- STEP 1: Archivo y Tabla -->
        <div class="card card-primary card-outline shadow-sm step-card active" id="step-1">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-file-upload mr-2"></i> Paso 1: Selección de Origen</h3></div>
            <div class="card-body">
                <form id="form-step-1">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Tabla Destino (en CMDB)</label>
                            <select name="tableName" id="destTable" class="form-control select2" required>
                                <option value="">-- Seleccionar Tabla --</option>
                                <?php foreach ($sheet_tables as $table): ?>
                                    <option value="<?= htmlspecialchars($table) ?>" <?= ($preselected_table === $table ? 'selected' : '') ?>><?= htmlspecialchars(ucfirst(str_replace('sheet_', '', $table))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="newTableContainer" class="<?= $preselected_table ? 'd-none' : '' ?>">
                                <small class="text-muted">Si quieres crear una nueva, escribe el nombre abajo.</small>
                                <input type="text" id="newTableName" class="form-control mt-2" placeholder="Nombre para nueva tabla (Ej: laptops)">
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Archivo Excel</label>
                            <div class="custom-file">
                                <input type="file" name="file" class="custom-file-input" id="excelFile" accept=".xlsx, .xls" required>
                                <label class="custom-file-label" for="excelFile">Elegir archivo...</label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> NOTA IMPORTANTE</h5>
                        Este proceso <b>REEMPLAZARÁ</b> la tabla seleccionada y su estructura con los nuevos datos y el mapeo que definas en el siguiente paso.
                    </div>
                </form>
            </div>
            <div class="card-footer text-right">
                <button type="button" class="btn btn-primary" id="btn-to-step-2">
                    Continuar al Mapeo <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: Pestaña y Mapeo -->
        <div class="card card-primary card-outline shadow-sm step-card" id="step-2">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-columns mr-2"></i> Paso 2: Configurar Columnas</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Elegir Pestaña del Excel</label>
                    <select id="excelSheet" class="form-control mb-4"></select>
                </div>
                
                <div id="mapping-container">
                    <h5>Mapeo de Columnas</h5>
                    <p class="text-muted">Desmarca las columnas que no desees importar. Puedes renombrar las columnas que aparecerán en la base de datos.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;">Importar</th>
                                    <th>Columna Original (Excel)</th>
                                    <th>Ejemplo de Dato</th>
                                    <th>Nombre en DB (Renombrar)</th>
                                </tr>
                            </thead>
                            <tbody id="mapping-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" id="btn-back-to-1"><i class="fas fa-arrow-left mr-1"></i> Volver</button>
                <button type="button" class="btn btn-success" id="btn-execute">
                    <i class="fas fa-play mr-1"></i> Iniciar Importación
                </button>
            </div>
        </div>

        <!-- STEP 3: Resultado -->
        <div class="card card-success card-outline shadow-sm step-card" id="step-3">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-check-circle mr-2"></i> Paso 3: Resultado Final</h3></div>
            <div class="card-body text-center py-5" id="result-content">
                <!-- Se llena con JS -->
            </div>
            <div class="card-footer text-center">
                <a href="import.php" class="btn btn-primary">Realizar otra importación</a>
                <a href="dashboard.php" class="btn btn-secondary ml-2">Ir al Dashboard</a>
            </div>
        </div>

      </div>
    </section>
  </div>
  <footer class="main-footer"><strong>CMDB Vilaseca &copy; 2024-2026.</strong> All rights reserved.</footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

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

    // Paso 1 -> Paso 2 (Subida y Descubrimiento)
    $('#btn-to-step-2').on('click', function() {
        const file = $('#excelFile')[0].files[0];
        const table = $('#destTable').val() || $('#newTableName').val();

        if (!file || !table) {
            alert("Por favor selecciona un archivo y una tabla de destino.");
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_excel_metadata');
        formData.append('csrf_token', csrfToken);
        formData.append('file', file);

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...');

        $.ajax({
            url: 'api_action.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp) {
                if (resp.success) {
                    tempFileName = resp.tempFile;
                    excelMetadata = resp.metadata;
                    
                    // Llenar selector de hojas
                    let options = '';
                    excelMetadata.forEach((m, idx) => {
                        options += `<option value="${m.sheetName}" data-idx="${idx}">${m.sheetName}</option>`;
                    });
                    $('#excelSheet').html(options).trigger('change');

                    // Cambiar de paso
                    $('#step-1').removeClass('active');
                    $('#step-2').addClass('active');
                    $('#badge-step-1').removeClass('active').addClass('completed');
                    $('#badge-step-2').addClass('active');
                } else {
                    alert("Error: " + resp.error);
                }
            },
            complete: () => {
                $('#btn-to-step-2').prop('disabled', false).html('Continuar al Mapeo <i class="fas fa-arrow-right ml-1"></i>');
            }
        });
    });

    // Cambio de hoja en Paso 2
    $('#excelSheet').on('change', function() {
        const idx = $(this).find(':selected').data('idx');
        const meta = excelMetadata[idx];
        let html = '';

        meta.columns.forEach((col, cIdx) => {
            if (!col) return;
            // Sanitizar nombre sugerido
            let suggestion = col.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
            if (suggestion === 'id') suggestion = 'item_id';

            html += `
                <tr>
                    <td class="text-center"><input type="checkbox" class="col-include" checked data-idx="${cIdx}"></td>
                    <td><b>${col}</b></td>
                    <td class="text-muted"><small>${meta.sample[cIdx] || '-'}</small></td>
                    <td><input type="text" class="form-control form-control-sm col-db-name" value="${suggestion}"></td>
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

    // Paso 2 -> Paso 3 (Ejecución)
    $('#btn-execute').on('click', function() {
        if (!confirm('¿Estás seguro? Esta acción recreará la tabla y borrará los datos anteriores.')) return;

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

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Importando...');

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
                    $('#result-content').html(`
                        <div class="display-4 text-success mb-3"><i class="fas fa-check-circle"></i></div>
                        <h2>¡Importación Finalizada!</h2>
                        <p class="lead">Se han importado <b>${resp.added}</b> registros en la tabla <b>${table}</b>.</p>
                        ${resp.errors.length > 0 ? `<div class="alert alert-warning text-left mt-4"><h5>Errores menores:</h5><small>${resp.errors.join('<br>')}</small></div>` : ''}
                    `);
                    $('#step-2').removeClass('active');
                    $('#step-3').addClass('active');
                    $('#badge-step-2').removeClass('active').addClass('completed');
                    $('#badge-step-3').addClass('active');
                } else {
                    alert("Error fatal: " + resp.error);
                }
            },
            complete: () => {
                $('#btn-execute').prop('disabled', false).html('<i class="fas fa-play mr-1"></i> Iniciar Importación');
            }
        });
    });
});
</script>
</body>
</html>