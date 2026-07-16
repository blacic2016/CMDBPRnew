<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "Informe de Disponibilidad Zabbix - Individual";
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
                    <li class="breadcrumb-item active">Disponibilidad</li>
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
                    <li class="nav-item"><a class="nav-link active" href="disponibilidad.php">Vista por Equipos</a></li>
                    <li class="nav-item"><a class="nav-link" href="disponibilidad_grupos.php">Vista por Grupos</a></li>
                </ul>
            </div>
            <div class="card-body" style="background-color: #f4f7f6;">
                <div class="main-content-container mx-auto" style="max-width: 1200px;">
                    <div class="form-container" style="background-color: #ffffff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); text-align: center; width: 100%; margin: 10px 0; border-top: 5px solid #007bff;">
                        <h2 class="text-3xl font-bold mb-4" style="color: #2c3e50;">Parámetros del Informe</h2>
                        <form id="reportForm">
                            <div class="row">
                                <div class="col-md-4 text-left mb-3">
                                    <label for="startDatePicker" class="font-weight-bold text-secondary">Fecha de Inicio:</label>
                                    <input type="text" id="startDatePicker" required class="form-control" autocomplete="off">
                                </div>
                                <div class="col-md-4 text-left mb-3">
                                    <label for="endDatePicker" class="font-weight-bold text-secondary">Fecha Fin:</label>
                                    <input type="text" id="endDatePicker" required class="form-control" autocomplete="off">
                                </div>
                                <div class="col-md-4 text-left mb-3">
                                    <label for="hostgroup" class="font-weight-bold text-secondary">Grupo de Host:</label>
                                    <select name="hostgroup" id="hostgroup" required class="form-control">
                                        <option value="">Cargando grupos...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="button-group mt-3 d-flex justify-content-center gap-3">
                                <button type="submit" id="generateReportButton" class="btn btn-primary btn-lg px-5 font-weight-bold shadow-sm">GENERAR INFORME</button>
                                <button type="button" id="pdfExportButton" class="btn btn-danger btn-lg px-5 font-weight-bold shadow-sm" style="display: none;">EXPORTAR A PDF</button>
                            </div>
                        </form>
                        <div id="loading-message" class="mt-3 text-info font-weight-bold" style="display:none;">Generando informe, por favor espere...</div>
                        <div id="error-message" class="alert alert-danger mt-3" style="display: none;"></div>
                    </div>

                    <div id="reportContainer" class="report-container mt-4" style="display: none; background-color: #ffffff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-top: 5px solid #007bff; width: 100%;">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .ok-status {
        background-color: #d4edda !important;
        color: #155724 !important;
        font-weight: 600;
    }
    .problem-status {
        background-color: #f8d7da !important;
        color: #721c24 !important;
        font-weight: 600;
    }
    .report-container table {
        background-color: #ffffff; 
        border: 1px solid #e0e0e0;
        width: 100%;
    }
    .report-container th {
        background-color: #e8f0fe;
        color: #34495e;
        padding: 10px;
        border: 1px solid #e0e0e0;
    }
    .report-container td {
        padding: 10px;
        border: 1px solid #e0e0e0;
    }
    .report-container tr:nth-child(even) {
        background-color: #f9f9f9;
    }
</style>

<script src="display_report.js"></script>
<script>
    $(function() {
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

        const hostgroupSelect = document.getElementById('hostgroup');
        const errorMessageDiv = document.getElementById('error-message');
        const pdfExportButton = document.getElementById('pdfExportButton');
        const generateReportButton = document.getElementById('generateReportButton');
        const reportForm = document.getElementById('reportForm');
        const loadingMessage = document.getElementById('loading-message');
        const reportContainer = document.getElementById('reportContainer');

        async function fetchHostGroups() {
            try {
                const response = await fetch('get_hostgroups.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const hostGroups = await response.json();

                hostgroupSelect.innerHTML = '<option value="">Seleccione un grupo de host</option>';
                if (hostGroups.length > 0) {
                    hostGroups.forEach(group => {
                        const option = document.createElement('option');
                        option.value = group;
                        option.textContent = group;
                        hostgroupSelect.appendChild(option);
                    });
                } else {
                    hostgroupSelect.innerHTML = '<option value="">No se encontraron grupos de host</option>';
                    hostgroupSelect.disabled = true;
                }
            } catch (error) {
                errorMessageDiv.textContent = `Error al cargar grupos de host: ${error.message}.`;
                errorMessageDiv.style.display = 'block';
                hostgroupSelect.innerHTML = '<option value="">Error al cargar</option>';
                hostgroupSelect.disabled = true;
            }
        }

        fetchHostGroups();

        reportForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            pdfExportButton.style.display = 'none'; 
            loadingMessage.style.display = 'block';
            errorMessageDiv.style.display = 'none';
            reportContainer.style.display = 'none';
            reportContainer.innerHTML = '';
            generateReportButton.disabled = true;

            const startDate = $('#startDatePicker').val();
            const endDate = $('#endDatePicker').val();
            const hostgroup = hostgroupSelect.value;

            if (!hostgroup || !startDate || !endDate) {
                errorMessageDiv.textContent = "Por favor, complete todos los campos.";
                errorMessageDiv.style.display = 'block';
                loadingMessage.style.display = 'none';
                generateReportButton.disabled = false;
                return;
            }

            try {
                const response = await fetch('process_report.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ startDate, endDate, hostgroup })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}. Details: ${errorText}`);
                }

                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error);
                }

                renderReport(data, reportContainer); 
                reportContainer.style.display = 'block';
                pdfExportButton.style.display = 'inline-block'; 
            } catch (error) {
                errorMessageDiv.textContent = `Error al generar el informe: ${error.message}`;
                errorMessageDiv.style.display = 'block';
                pdfExportButton.style.display = 'none'; 
            } finally {
                loadingMessage.style.display = 'none';
                generateReportButton.disabled = false;
            }
        });

        pdfExportButton.addEventListener('click', async () => {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('reportContainer');
            
            pdfExportButton.disabled = true;
            pdfExportButton.textContent = 'Creando PDF...';

            try {
                await new Promise(resolve => setTimeout(resolve, 500));

                html2canvas(element, {
                    scale: 2, 
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff' 
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

                    const groupName = hostgroupSelect.value.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                    const startDate = $('#startDatePicker').val();
                    const endDate = $('#endDatePicker').val();
                    doc.save(`Informe_Disponibilidad_${groupName}_${startDate}_a_${endDate}.pdf`);
                });
            } catch(error) {
                alert('Error al generar el PDF: ' + error.message);
            } finally {
                pdfExportButton.disabled = false;
                pdfExportButton.textContent = 'EXPORTAR A PDF';
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/../../../partials/footer.php';
?>