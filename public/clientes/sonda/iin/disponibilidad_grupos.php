<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "Informe de Disponibilidad Zabbix - Grupos";
$page_icon = "fas fa-chart-bar text-success";
$hide_content_header = true;
require_once __DIR__ . '/../../../partials/header.php';
?>

<!-- jQuery UI CSS -->
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<!-- jQuery UI JS -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

<!-- Carga de librerías para exportación y gráficos -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.tailwindcss.com"></script>

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
                    <li class="breadcrumb-item active">Disponibilidad por Grupos</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header d-flex p-0">
                <h3 class="card-title p-3"><i class="fas fa-filter mr-2"></i>Filtros de Disponibilidad</h3>
                <ul class="nav nav-pills ml-auto p-2">
                    <li class="nav-item"><a class="nav-link" href="disponibilidad.php">Vista por Equipos</a></li>
                    <li class="nav-item"><a class="nav-link active" href="disponibilidad_grupos.php">Vista por Grupos</a></li>
                </ul>
            </div>
            <div class="card-body" style="background-color: #f4f7f6;">
                <div class="zabbix-container mx-auto" style="max-width: 1200px;">
                    <!-- Collapsible Filter Box -->
                    <div class="zabbix-filter-container card card-default shadow-sm mb-4">
                        <div class="card-header" id="filterToggleBtn" style="cursor: pointer; user-select: none;">
                            <h3 class="card-title"><i class="fas fa-search mr-2"></i>Filtro de Parámetros</h3>
                            <div class="card-tools">
                                <span id="filterArrow" class="badge badge-primary">▼</span>
                            </div>
                        </div>
                        <div class="card-body" id="filterBody">
                            <form id="reportForm">
                                <div class="row align-items-end">
                                    <div class="col-md-5 mb-3">
                                        <label for="startDatePicker" class="font-weight-bold text-secondary text-uppercase" style="font-size: 11px;">Fecha de Inicio:</label>
                                        <input type="text" id="startDatePicker" required class="form-control" autocomplete="off">
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label for="endDatePicker" class="font-weight-bold text-secondary text-uppercase" style="font-size: 11px;">Fecha Fin:</label>
                                        <input type="text" id="endDatePicker" required class="form-control" autocomplete="off">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <button type="submit" id="generateReportButton" class="btn btn-primary btn-block font-weight-bold">Aplicar Filtro</button>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <button type="button" id="pdfExportButton" class="btn btn-danger font-weight-bold px-4" style="display: none;"><i class="fas fa-file-pdf mr-2"></i>Exportar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Loader -->
                    <div class="zabbix-loader-container card p-5 text-center shadow-sm" id="loadingMessage" style="display: none;">
                        <div class="spinner-border text-primary mx-auto" role="status" style="width: 3rem; height: 3rem;">
                            <span class="sr-only">Cargando...</span>
                        </div>
                        <div class="mt-3 text-secondary font-weight-bold">Procesando disponibilidad de grupos en Zabbix, por favor espere...</div>
                    </div>

                    <!-- Error Alert -->
                    <div id="errorMessage" class="alert alert-danger hidden mb-4" role="alert">
                        <span id="errorText"></span>
                    </div>

                    <!-- Report Container -->
                    <div id="reportContainer" style="display: none;">
                        <!-- Summary Widget Cards -->
                        <div class="row mb-4" id="summaryGrid">
                            <!-- Will be populated dynamically -->
                        </div>

                        <!-- Group Availability Table Card -->
                        <div class="card card-default shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-table mr-2 text-primary"></i>Disponibilidad de Canales (Grupos) por ICMP Ping</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0" id="groupsTable" style="font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th>Grupo de Host (Zabbix)</th>
                                                <th class="text-center" style="width: 100px;">Equipos</th>
                                                <th class="text-center" style="width: 150px;">Disponibilidad (OK)</th>
                                                <th class="text-center" style="width: 150px;">Problemas</th>
                                                <th>Tiempo OK Estimado</th>
                                                <th>Tiempo Inactividad</th>
                                                <th class="text-center" style="width: 120px;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="groupsTableBody">
                                            <!-- Will be populated dynamically -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Comparison Chart Card -->
                        <div class="card card-default shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-chart-line mr-2 text-info"></i>Comparación de Disponibilidad por Grupo (%)</h3>
                            </div>
                            <div class="card-body">
                                <div style="height: 420px; position: relative;">
                                    <canvas id="groupsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .zabbix-badge {
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        display: inline-block;
        text-align: center;
    }
    .zabbix-badge-ok {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .zabbix-badge-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }
    .zabbix-badge-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<script src="display_grupos.js"></script>
<script>
    $(function() {
        $('#filterToggleBtn').click(function() {
            $('#filterBody').slideToggle(200, function() {
                $('#filterArrow').text($(this).is(':visible') ? '▼' : '▲');
            });
        });

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

        const reportForm = document.getElementById('reportForm');
        const loadingMessage = document.getElementById('loadingMessage');
        const reportContainer = document.getElementById('reportContainer');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const pdfExportButton = document.getElementById('pdfExportButton');
        const generateButton = document.getElementById('generateReportButton');

        reportForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            pdfExportButton.style.display = 'none';
            loadingMessage.style.display = 'block';
            errorMessage.classList.add('hidden');
            reportContainer.style.display = 'none';
            generateButton.disabled = true;

            const startDate = $('#startDatePicker').val();
            const endDate = $('#endDatePicker').val();

            try {
                const response = await fetch('process_grupos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ startDate, endDate })
                });

                if (!response.ok) {
                    const errorMsg = await response.text();
                    throw new Error(`Error en el servidor: ${response.status}. Detalles: ${errorMsg}`);
                }

                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error);
                }

                renderGroupsReport(data, startDate, endDate);
                reportContainer.style.display = 'block';
                pdfExportButton.style.display = 'inline-block';
            } catch (error) {
                errorText.textContent = error.message;
                errorMessage.classList.remove('hidden');
            } finally {
                loadingMessage.style.display = 'none';
                generateButton.disabled = false;
            }
        });

        pdfExportButton.addEventListener('click', async () => {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('reportContainer');
            
            pdfExportButton.disabled = true;
            pdfExportButton.textContent = 'Generando PDF...';

            try {
                await new Promise(resolve => setTimeout(resolve, 500));

                html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#f4f7f6'
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const imgWidth = 200;
                    const pageHeight = 295;
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;

                    const doc = new jsPDF('p', 'mm', 'a4');
                    let position = 0;

                    doc.addImage(imgData, 'PNG', 5, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;

                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        doc.addPage();
                        doc.addImage(imgData, 'PNG', 5, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }

                    const startDate = $('#startDatePicker').val();
                    const endDate = $('#endDatePicker').val();
                    doc.save(`Informe_Disponibilidad_Grupos_${startDate}_a_${endDate}.pdf`);
                });
            } catch(error) {
                alert('Error al generar el PDF: ' + error.message);
            } finally {
                pdfExportButton.disabled = false;
                pdfExportButton.textContent = 'Exportar PDF';
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/../../../partials/footer.php';
?>
