<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "Respaldos Veeam - Análisis de Datos";
$page_icon = "fas fa-hdd text-info";
$hide_content_header = true;
require_once __DIR__ . '/../../../partials/header.php';
?>

<!-- jQuery UI CSS -->
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<!-- jQuery UI JS -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

<!-- Estilos específicos de Pivot/C3 -->
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.11/c3.min.css">
<link rel="stylesheet" type="text/css" href="veeam_dist/pivot.css">

<!-- Scripts de Pivot/C3 -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.11/c3.min.js"></script>
<script type="text/javascript" src="veeam_dist/pivot.js"></script>
<script type="text/javascript" src="veeam_dist/c3_renderers.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                    <li class="breadcrumb-item active">Respaldos Veeam</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Summary Row -->
<div id="summaryRow" class="row mb-4" style="display: none;">
    <!-- Total Card -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-tasks text-white"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-uppercase font-weight-bold text-muted" style="font-size: 0.85rem;">Total Procesados</span>
                <span id="statTotal" class="info-box-number font-weight-bold text-dark" style="font-size: 1.5rem;">0</span>
            </div>
        </div>
    </div>
    <!-- OK Card -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-check-circle text-white"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-uppercase font-weight-bold text-muted" style="font-size: 0.85rem;">Exitosos (OK)</span>
                <span id="statSuccess" class="info-box-number font-weight-bold text-success" style="font-size: 1.5rem;">0</span>
            </div>
        </div>
    </div>
    <!-- Failed Card -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-danger"><i class="fas fa-times-circle text-white"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-uppercase font-weight-bold text-muted" style="font-size: 0.85rem;">Fallidos (Fails)</span>
                <span id="statFailed" class="info-box-number font-weight-bold text-danger" style="font-size: 1.5rem;">0</span>
            </div>
        </div>
    </div>
    <!-- Warning Card -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle text-white"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-uppercase font-weight-bold text-muted" style="font-size: 0.85rem;">Advertencias</span>
                <span id="statWarning" class="info-box-number font-weight-bold text-warning" style="font-size: 1.5rem;">0</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4 col-lg-3">
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Controles</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="startDatePicker" class="font-weight-bold">Fecha de Inicio:</label>
                    <input type="text" id="startDatePicker" class="form-control" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="endDatePicker" class="font-weight-bold">Fecha Fin:</label>
                    <input type="text" id="endDatePicker" class="form-control" autocomplete="off">
                </div>
                <button id="analyzeData" class="btn btn-primary btn-block font-weight-bold shadow-sm mb-2"><i class="fas fa-chart-line mr-2"></i>Analizar Datos</button>
                <button id="exportExcel" class="btn btn-success btn-block font-weight-bold shadow-sm mb-3"><i class="fas fa-file-excel mr-2"></i>Exportar a Excel</button>
                <hr>
                <a href="veeam_manage_tasks.php" class="btn btn-outline-info btn-block font-weight-bold shadow-sm"><i class="fas fa-tasks mr-2"></i>Gestionar Tareas</a>
            </div>
        </div>

        <!-- Distribution Chart Card -->
        <div class="card card-default shadow-sm mt-3" id="chartCard" style="display: none;">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-secondary"><i class="fas fa-chart-pie mr-2"></i>Distribución de Estados</h3>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center" style="position: relative; height: 260px; padding: 10px;">
                <canvas id="veeamChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-8 col-lg-9">
        <div class="card card-default shadow-sm">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-secondary"><i class="fas fa-th mr-2"></i>Tabla Dinámica (Pivot Grid)</h3>
            </div>
            <div class="card-body">
                <div id="output" class="overflow-auto" style="min-height: 400px;">
                    <p class="text-center text-muted">Haga clic en 'Analizar Datos' para comenzar...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pvtTable {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .pvtTable th, .pvtTable td {
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
        white-space: nowrap;
    }
    .pvtTable th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #444;
    }
    .pvtTable tr:nth-child(even) {
        background-color: #fdfdfd;
    }
    .pvtTable .pvtVal {
        text-align: center;
    }
    #output .pvtVal.success {
        background-color: #d4edda !important;
        color: #155724 !important;
        font-weight: bold;
    }
    #output .pvtVal.failure {
        background-color: #f8d7da !important;
        color: #721c24 !important;
        font-weight: bold;
    }
</style>

<script>
    $(function() {
        var derivers = $.pivotUtilities.derivers;
        var renderers = $.extend($.pivotUtilities.renderers, $.pivotUtilities.c3_renderers);

        $("#startDatePicker").datepicker({
            dateFormat: 'yy-mm-dd',
            onSelect: function(selectedDate) {
                $("#endDatePicker").datepicker("option", "minDate", selectedDate);
            }
        });
        $("#endDatePicker").datepicker({
            dateFormat: 'yy-mm-dd',
            onSelect: function(selectedDate) {
                $("#startDatePicker").datepicker("option", "maxDate", selectedDate);
            }
        });

        var today = new Date();
        var firstDayOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        var lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);

        $("#startDatePicker").datepicker("setDate", firstDayOfLastMonth);
        $("#endDatePicker").datepicker("setDate", lastDayOfLastMonth);

        var veeamChartInstance = null;

        function renderChart(ok, failed, warning) {
            var ctx = document.getElementById('veeamChart').getContext('2d');
            if (veeamChartInstance) {
                veeamChartInstance.destroy();
            }
            
            veeamChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['OK', 'Failed', 'Warning'],
                    datasets: [{
                        data: [ok, failed, warning],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        function fetchData(startDate, endDate) {
            $("#output").html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Cargando datos de respaldos...</p></div>');

            $.getJSON('veeam_fetch_data.php', { startDate: startDate, endDate: endDate }, function(data) {
                // Calculate stats
                var total = data.length;
                var ok = 0;
                var failed = 0;
                var warning = 0;

                data.forEach(function(item) {
                    if (item.Succeeded === 'OK') {
                        ok++;
                    } else if (item.Succeeded === 'Failed') {
                        failed++;
                    } else if (item.Succeeded === 'Warning') {
                        warning++;
                    }
                });

                $("#statTotal").text(total);
                $("#statSuccess").text(ok);
                $("#statFailed").text(failed);
                $("#statWarning").text(warning);
                $("#summaryRow").slideDown();

                renderChart(ok, failed, warning);
                $("#chartCard").slideDown();

                $("#output").pivotUI(data, {
                    rows: ["job_name", "vm_name", "HORA", "FRECUENCIA"],
                    cols: ["year", "mes", "dia", "dia_semana"],
                    vals: ["Succeeded"],
                    aggregatorName: "Last",
                    rendererName: "Table",
                    rendererOptions: {
                        table: {
                            clickCallback: function(e, value, filters, pivotData) {
                                var names = [];
                                pivotData.forEachMatchingRecord(filters, function(record) {
                                    names.push("Job: " + record.job_name, "VM: " + record.vm_name, "VM status: " + record.vm_status, "Fecha: " + record.year + "-" + record.mes + "-" + record.dia, "Inicio: " + record.vm_start_time_str, "Hora prevista: " + record.HORA, "----------------------------- ");
                                });
                                alert("Detalle del Respaldo:\n" + names.join("\n"));
                            }
                        }
                    },
                    onRefresh: function(config) {
                        $('#output td').each(function() {
                            var cellText = $(this).text().trim();
                            if (cellText === "OK") {
                                $(this).addClass("success");
                                $(this).removeClass("failure");
                            } else if (cellText === "Failed") {
                                $(this).addClass("failure");
                                $(this).removeClass("success");
                            } else {
                                $(this).removeClass("success failure");
                            }
                        });
                    }
                });
            }).fail(function() {
                $("#output").html('<div class="alert alert-danger text-center"><i class="fas fa-exclamation-triangle mr-2"></i>Error al cargar los datos. Verifique la conexión o el script PHP.</div>');
            });
        }

        fetchData($("#startDatePicker").val(), $("#endDatePicker").val());

        $('#analyzeData').on('click', function() {
            var selectedStartDate = $('#startDatePicker').val();
            var selectedEndDate = $('#endDatePicker').val();
            fetchData(selectedStartDate, selectedEndDate);
        });

        $("#exportExcel").click(function() {
            var table = document.querySelector(".pvtTable");
            if (!table) {
                alert("No hay datos para exportar.");
                return;
            }
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, "RespaldoDatos");
            XLSX.writeFile(wb, "Respaldo_Veeam.xlsx");
        });
    });
</script>

<?php
require_once __DIR__ . '/../../../partials/footer.php';
?>