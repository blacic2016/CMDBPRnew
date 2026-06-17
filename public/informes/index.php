<?php
/**
 * index.php (Vista por Equipos - Disponibilidad)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/index.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();

$page_title = 'Disponibilidad por Equipos (ICMP Ping)';
require_once __DIR__ . '/../partials/header.php';

// Obtener parámetros si vienen del informe de grupos (Drill-Down)
$drill_group = $_GET['hostgroup'] ?? '';
$drill_start = $_GET['startDate'] ?? '';
$drill_end = $_GET['endDate'] ?? '';
?>

<!-- jQuery UI & Styles for Datepicker -->
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<!-- jsPDF, html2canvas -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .filter-card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    .nav-report-tabs {
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 25px;
    }
    .nav-report-link {
        font-weight: 700;
        color: var(--text-muted);
        padding: 10px 20px;
        display: inline-block;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
    }
    .nav-report-link:hover {
        color: var(--sonda-orange);
        text-decoration: none;
    }
    .nav-report-link.active {
        color: var(--sonda-orange);
        border-bottom-color: var(--sonda-orange);
        text-decoration: none;
    }
</style>

<div class="container-fluid pt-2">
    <!-- Sub-navigation Tabs -->
    <div class="nav-report-tabs d-flex align-items-center">
        <a href="index.php" class="nav-report-link active mr-3"><i class="fas fa-desktop mr-2"></i>Vista por Equipos</a>
        <a href="grupos.php" class="nav-report-link mr-3"><i class="fas fa-layer-group mr-2"></i>Vista por Grupos</a>
        <a href="alarmas.php" class="nav-report-link mr-3"><i class="fas fa-exclamation-triangle mr-2"></i>Distribución de Alarmas</a>
        <a href="alcance.php" class="nav-report-link"><i class="fas fa-chart-line mr-2"></i>Alcance y Plantillas</a>
    </div>

    <!-- Filtro de Parámetros -->
    <div class="card filter-card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-filter mr-2 text-primary"></i>Filtro de Parámetros</h5>
        </div>
        <div class="card-body">
            <form id="reportForm">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="startDatePicker" class="small font-weight-bold">Fecha de Inicio</label>
                        <input type="text" id="startDatePicker" required class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($drill_start); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="endDatePicker" class="small font-weight-bold">Fecha Fin</label>
                        <input type="text" id="endDatePicker" required class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($drill_end); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="hostgroup" class="small font-weight-bold">Grupo de Host</label>
                        <select name="hostgroup" id="hostgroup" required class="form-control select2bs4">
                            <option value="">Cargando grupos...</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end form-group">
                        <button type="submit" id="generateReportButton" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                            <i class="fas fa-sync-alt mr-2"></i> GENERAR INFORME
                        </button>
                        <button type="button" id="pdfExportButton" class="btn btn-danger btn-block py-2 font-weight-bold shadow-sm ml-2 mt-0" style="display: none;">
                            <i class="fas fa-file-pdf mr-2"></i> PDF
                        </button>
                    </div>
                </div>
            </form>
            <div id="loading-message" class="text-center text-primary font-weight-bold my-3" style="display: none;">
                <i class="fas fa-spinner fa-spin mr-2"></i> Generando informe de equipos, por favor espere...
            </div>
            <div id="error-message" class="alert alert-danger font-weight-bold text-xs mt-3" style="display: none;"></div>
        </div>
    </div>

    <!-- Contenedor del Reporte Renders -->
    <div id="reportContainer" class="p-4 filter-card mb-4 bg-white" style="display: none;"></div>
</div>

<!-- jQuery UI JS for Datepicker -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<!-- Display report script -->
<script src="display_report.js"></script>

<script>
    $(function() {
        // Inicializar date pickers
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

        // Valores por defecto si no son drill-down
        var drillGroup = '<?php echo addslashes($drill_group); ?>';
        var drillStart = '<?php echo addslashes($drill_start); ?>';
        var drillEnd = '<?php echo addslashes($drill_end); ?>';

        if (!drillStart || !drillEnd) {
            var today = new Date();
            var firstDayOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            var lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);

            $("#startDatePicker").datepicker("setDate", firstDayOfLastMonth);
            $("#endDatePicker").datepicker("setDate", lastDayOfLastMonth);
        }

        const hostgroupSelect = document.getElementById('hostgroup');
        const errorMessageDiv = document.getElementById('error-message');
        const pdfExportButton = document.getElementById('pdfExportButton');
        const generateReportButton = document.getElementById('generateReportButton');

        // Función para cargar grupos de host
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
                        if (drillGroup && group === drillGroup) {
                            option.selected = true;
                        }
                        hostgroupSelect.appendChild(option);
                    });
                    
                    // Si es un drill-down automático
                    if (drillGroup && drillStart && drillEnd) {
                        $('#reportForm').submit();
                    }
                } else {
                    hostgroupSelect.innerHTML = '<option value="">No se encontraron grupos de host</option>';
                    hostgroupSelect.disabled = true;
                }
            } catch (error) {
                errorMessageDiv.textContent = `Error al cargar grupos de host: ${error.message}.`;
                errorMessageDiv.style.display = 'block';
                hostgroupSelect.innerHTML = '<option value="">Error al cargar</option>';
                hostgroupSelect.disabled = true;
                console.error('Error fetching host groups:', error);
            }
        }

        fetchHostGroups();

        const reportForm = document.getElementById('reportForm');
        const loadingMessage = document.getElementById('loading-message');
        const reportContainer = document.getElementById('reportContainer');

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

        // Exportar a PDF
        pdfExportButton.addEventListener('click', async () => {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('reportContainer');
            
            pdfExportButton.disabled = true;
            pdfExportButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creando...';

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
                console.error('PDF Error:', error);
            } finally {
                pdfExportButton.disabled = false;
                pdfExportButton.innerHTML = '<i class="fas fa-file-pdf mr-2"></i> PDF';
            }
        });

    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
