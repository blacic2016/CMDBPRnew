<?php
/**
 * grupos.php (Vista por Grupos - Disponibilidad)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/grupos.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();

$page_title = 'Disponibilidad por Grupos (ICMP Ping)';
require_once __DIR__ . '/../partials/header.php';
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
        <a href="index.php" class="nav-report-link mr-3"><i class="fas fa-desktop mr-2"></i>Vista por Equipos</a>
        <a href="grupos.php" class="nav-report-link active mr-3"><i class="fas fa-layer-group mr-2"></i>Vista por Grupos</a>
        <a href="alarmas.php" class="nav-report-link mr-3"><i class="fas fa-exclamation-triangle mr-2"></i>Distribución de Alarmas</a>
        <a href="alcance.php" class="nav-report-link"><i class="fas fa-chart-line mr-2"></i>Alcance y Plantillas</a>
    </div>

    <!-- Filtro de Parámetros -->
    <div class="card filter-card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-filter mr-2 text-primary"></i>Filtro de Parámetros - Vista Consolidada</h5>
        </div>
        <div class="card-body">
            <form id="reportForm">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="startDatePicker" class="small font-weight-bold">Fecha de Inicio</label>
                        <input type="text" id="startDatePicker" required class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="endDatePicker" class="small font-weight-bold">Fecha Fin</label>
                        <input type="text" id="endDatePicker" required class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-4 d-flex align-items-end form-group">
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
                <i class="fas fa-spinner fa-spin mr-2"></i> Generando informe consolidado de grupos, por favor espere...
            </div>
            <div id="error-message" class="alert alert-danger font-weight-bold text-xs mt-3" style="display: none;"></div>
        </div>
    </div>

    <!-- Contenedor del Reporte Renders -->
    <div id="reportContainer" style="display: none;">
        <!-- Fila de Tarjetas de Resumen (Se inyecta por JS) -->
        <div id="summaryGrid" class="row mb-4"></div>

        <div class="row">
            <!-- Gráfico Horizontal de Comparación de Grupos -->
            <div class="col-lg-6 mb-4">
                <div class="card card-outline card-primary shadow-sm h-100 mb-0">
                    <div class="card-header bg-white">
                        <h5 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-chart-bar mr-2"></i>Comparación de Disponibilidad</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 400px; position: relative;">
                            <canvas id="groupsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Detalles de Canales/Grupos -->
            <div class="col-lg-6 mb-4">
                <div class="card card-outline card-info shadow-sm h-100 mb-0">
                    <div class="card-header bg-white">
                        <h5 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-table mr-2"></i>Resumen de Disponibilidad por Grupos</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0" style="font-size:0.85rem">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Canal / Grupo</th>
                                        <th class="text-center">Equipos</th>
                                        <th class="text-center">% OK</th>
                                        <th class="text-center">% Caído</th>
                                        <th>Tiempo OK</th>
                                        <th>Tiempo Caído</th>
                                        <th class="text-center">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody id="groupsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery UI JS for Datepicker -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<!-- Display groups report script -->
<script src="display_grupos.js"></script>

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

        // Valores por defecto
        var today = new Date();
        var firstDayOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        var lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);

        $("#startDatePicker").datepicker("setDate", firstDayOfLastMonth);
        $("#endDatePicker").datepicker("setDate", lastDayOfLastMonth);

        const errorMessageDiv = document.getElementById('error-message');
        const pdfExportButton = document.getElementById('pdfExportButton');
        const generateReportButton = document.getElementById('generateReportButton');
        const reportForm = document.getElementById('reportForm');
        const loadingMessage = document.getElementById('loading-message');
        const reportContainer = document.getElementById('reportContainer');

        reportForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            pdfExportButton.style.display = 'none'; 
            loadingMessage.style.display = 'block';
            errorMessageDiv.style.display = 'none';
            reportContainer.style.display = 'none';
            generateReportButton.disabled = true;

            const startDate = $('#startDatePicker').val();
            const endDate = $('#endDatePicker').val();

            if (!startDate || !endDate) {
                errorMessageDiv.textContent = "Por favor, complete todos los campos.";
                errorMessageDiv.style.display = 'block';
                loadingMessage.style.display = 'none';
                generateReportButton.disabled = false;
                return;
            }

            try {
                const response = await fetch('process_grupos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ startDate, endDate })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}. Details: ${errorText}`);
                }

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                renderGroupsReport(data, startDate, endDate); 
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

                    const startDate = $('#startDatePicker').val();
                    const endDate = $('#endDatePicker').val();
                    doc.save(`Informe_Disponibilidad_Consolidado_Grupos_${startDate}_a_${endDate}.pdf`);
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
