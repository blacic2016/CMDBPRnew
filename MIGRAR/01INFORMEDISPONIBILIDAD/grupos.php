<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zabbix - Disponibilidad por Grupo de Hosts</title>
    
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f3f6f8;
            color: #1f2c33;
            margin: 0;
            padding: 0;
        }

        /* Zabbix Top Navigation Bar */
        .zabbix-navbar {
            background-color: #2f3c43;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .zabbix-navbar-left {
            display: flex;
            align-items: center;
            height: 100%;
        }

        .zabbix-logo-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 30px;
        }

        .zabbix-logo-badge {
            background-color: #d82c2c;
            color: #ffffff;
            font-weight: 800;
            font-size: 18px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 2px;
            line-height: 1;
        }

        .zabbix-logo-text {
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.5px;
        }

        .zabbix-nav-links {
            display: flex;
            gap: 1px;
            height: 100%;
        }

        .zabbix-nav-link {
            display: flex;
            align-items: center;
            padding: 0 16px;
            color: #e0e4e7;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            height: 100%;
            border-bottom: 3px solid transparent;
        }

        .zabbix-nav-link:hover {
            background-color: #3e4e56;
            color: #ffffff;
        }

        .zabbix-nav-link.active {
            background-color: #1f2c33;
            color: #ffffff;
            border-bottom-color: #ff5b5b; /* Zabbix active tab accent */
        }

        .zabbix-navbar-right img {
            height: 25px;
            width: auto;
            opacity: 0.9;
        }

        /* Subnavigation Menu */
        .zabbix-subnav {
            background-color: #ffffff;
            border-bottom: 1px solid #dfe4e8;
            padding: 0 20px;
            display: flex;
            height: 38px;
            align-items: center;
            justify-content: space-between;
        }

        .zabbix-subnav-left {
            display: flex;
            align-items: center;
        }

        .zabbix-subnav-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2c33;
            margin-right: 20px;
        }

        .zabbix-subnav-links {
            display: flex;
            gap: 15px;
        }

        .zabbix-subnav-link {
            font-size: 12px;
            color: #0275b8;
            text-decoration: none;
        }

        .zabbix-subnav-link:hover {
            text-decoration: underline;
        }

        .zabbix-subnav-link.active {
            font-weight: 700;
            color: #1f2c33;
            text-decoration: none;
            cursor: default;
        }

        /* Main Container */
        .zabbix-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* Collapsible Filter Box */
        .zabbix-filter-container {
            border: 1px solid #dfe4e8;
            background-color: #ffffff;
            margin-bottom: 20px;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .zabbix-filter-header {
            background-color: #ebedf0;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #dfe4e8;
            user-select: none;
        }

        .zabbix-filter-body {
            padding: 15px;
        }

        /* Zabbix Form Elements */
        .zabbix-form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .zabbix-label {
            font-size: 11px;
            font-weight: 700;
            color: #4f5f67;
            text-transform: uppercase;
        }

        .zabbix-input {
            border: 1px solid #acbbc2;
            padding: 5px 8px;
            font-size: 12px;
            border-radius: 2px;
            background-color: #ffffff;
            color: #1f2c33;
            outline: none;
            width: 100%;
            height: 28px;
        }

        .zabbix-input:focus {
            border-color: #0275b8;
            box-shadow: 0 0 0 2px rgba(2, 117, 184, 0.15);
        }

        /* Zabbix Buttons */
        .zabbix-btn {
            height: 28px;
            padding: 0 16px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 2px;
            cursor: pointer;
            border: 1px solid #acbbc2;
            background-color: #f3f6f8;
            color: #1f2c33;
            transition: all 0.1s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .zabbix-btn:hover {
            background-color: #e8ecef;
            border-color: #92a2ab;
        }

        .zabbix-btn-primary {
            background-color: #0275b8;
            color: #ffffff;
            border-color: #0275b8;
        }

        .zabbix-btn-primary:hover {
            background-color: #02659e;
            border-color: #02659e;
        }

        .zabbix-btn-danger {
            background-color: #d82c2c;
            color: #ffffff;
            border-color: #d82c2c;
        }

        .zabbix-btn-danger:hover {
            background-color: #bd2525;
            border-color: #bd2525;
        }

        /* Zabbix Table */
        .zabbix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            border: 1px solid #dfe4e8;
            background-color: #ffffff;
        }

        .zabbix-table th {
            background-color: #ebedf0;
            color: #1f2c33;
            font-weight: 700;
            text-align: left;
            padding: 8px 12px;
            border: 1px solid #dfe4e8;
            border-bottom: 2px solid #acbbc2;
            text-transform: uppercase;
            font-size: 11px;
        }

        .zabbix-table td {
            padding: 8px 12px;
            border: 1px solid #dfe4e8;
            color: #1f2c33;
            vertical-align: middle;
        }

        .zabbix-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .zabbix-table tr:hover {
            background-color: #eaf2f8;
        }

        /* Badges */
        .zabbix-badge {
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 2px;
            font-size: 11px;
            display: inline-block;
            text-align: center;
            font-family: monospace;
        }

        .zabbix-badge-ok {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .zabbix-badge-warning {
            background-color: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffe0b2;
        }

        .zabbix-badge-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Summary Box (Widgets) */
        .zabbix-widget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .zabbix-widget {
            background-color: #ffffff;
            border: 1px solid #dfe4e8;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .zabbix-widget-header {
            background-color: #ebedf0;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 11px;
            border-bottom: 1px solid #dfe4e8;
            color: #4f5f67;
            text-transform: uppercase;
        }

        .zabbix-widget-content {
            padding: 18px;
            font-size: 24px;
            font-weight: 700;
            color: #1f2c33;
            text-align: center;
        }

        .zabbix-widget-subtext {
            font-size: 11px;
            color: #748b99;
            margin-top: 5px;
            font-weight: normal;
        }

        /* Chart card */
        .zabbix-card {
            background-color: #ffffff;
            border: 1px solid #dfe4e8;
            border-radius: 2px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .zabbix-card-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 15px;
            border-bottom: 1px solid #dfe4e8;
            padding-bottom: 6px;
            color: #1f2c33;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        /* Loader */
        .zabbix-loader-container {
            display: none;
            text-align: center;
            padding: 40px;
            background-color: #ffffff;
            border: 1px solid #dfe4e8;
            border-radius: 2px;
            margin-bottom: 20px;
        }

        .zabbix-loader {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid rgba(2, 117, 184, 0.15);
            border-radius: 50%;
            border-top-color: #0275b8;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .zabbix-loader-text {
            margin-top: 12px;
            font-size: 12px;
            color: #4f5f67;
            font-weight: 600;
        }

        .text-link {
            color: #0275b8;
            text-decoration: none;
            font-weight: 600;
        }

        .text-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Zabbix Top Navigation Bar -->
    <div class="zabbix-navbar">
        <div class="zabbix-navbar-left">
            <div class="zabbix-logo-container">
                <span class="zabbix-logo-badge">Z</span>
                <span class="zabbix-logo-text">ZABBIX</span>
            </div>
            <div class="zabbix-nav-links">
                <a href="index.php" class="zabbix-nav-link">Disponibilidad por Host</a>
                <a href="grupos.php" class="zabbix-nav-link active">Disponibilidad por Grupo</a>
            </div>
        </div>
        <div class="zabbix-navbar-right">
            <img src="https://www.leterago.com.ec/wp-content/uploads/2023/09/leterago_colorDistorcionado.svg" alt="Leterago Logo">
        </div>
    </div>

    <!-- Zabbix Subnavigation Menu -->
    <div class="zabbix-subnav">
        <div class="zabbix-subnav-left">
            <div class="zabbix-subnav-title">Informes de Disponibilidad</div>
            <div class="zabbix-subnav-links">
                <a href="index.php" class="zabbix-subnav-link">Vista por Equipos</a>
                <span class="text-gray-300">|</span>
                <a href="grupos.php" class="zabbix-subnav-link active">Vista por Grupos</a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="zabbix-container">
        <!-- Collapsible Filter Box -->
        <div class="zabbix-filter-container">
            <div class="zabbix-filter-header" id="filterToggleBtn">
                <span>Filtro de Parámetros</span>
                <span id="filterArrow">▼</span>
            </div>
            <div class="zabbix-filter-body" id="filterBody">
                <form id="reportForm">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <div class="zabbix-form-group">
                            <label for="startDatePicker" class="zabbix-label">Fecha de Inicio:</label>
                            <input type="text" id="startDatePicker" required class="zabbix-input" autocomplete="off">
                        </div>
                        <div class="zabbix-form-group">
                            <label for="endDatePicker" class="zabbix-label">Fecha Fin:</label>
                            <input type="text" id="endDatePicker" required class="zabbix-input" autocomplete="off">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" id="generateReportButton" class="zabbix-btn zabbix-btn-primary flex-1">Aplicar Filtro</button>
                            <button type="button" id="pdfExportButton" class="zabbix-btn zabbix-btn-danger" style="display: none;">Exportar PDF</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loader -->
        <div class="zabbix-loader-container" id="loadingMessage">
            <div class="zabbix-loader"></div>
            <div class="zabbix-loader-text">Procesando disponibilidad de grupos en Zabbix, por favor espere...</div>
        </div>

        <!-- Error Alert -->
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-xs font-semibold" role="alert">
            <span class="block sm:inline" id="errorText"></span>
        </div>

        <!-- Report Container -->
        <div id="reportContainer" style="display: none;">
            <!-- Summary Widget Cards -->
            <div class="zabbix-widget-grid" id="summaryGrid">
                <!-- Will be populated dynamically -->
            </div>

            <!-- Group Availability Table Card -->
            <div class="zabbix-card">
                <div class="zabbix-card-title">Disponibilidad de Canales (Grupos) por ICMP Ping</div>
                <div class="overflow-x-auto">
                    <table class="zabbix-table" id="groupsTable">
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

            <!-- Comparison Chart Card -->
            <div class="zabbix-card">
                <div class="zabbix-card-title">Comparación de Disponibilidad por Grupo (%)</div>
                <div style="height: 420px; position: relative;">
                    <canvas id="groupsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script src="display_grupos.js"></script>
    <script>
        $(function() {
            // Collapsible filter logic
            $('#filterToggleBtn').click(function() {
                $('#filterBody').slideToggle(200, function() {
                    $('#filterArrow').text($(this).is(':visible') ? '▼' : '▲');
                });
            });

            // Date Pickers initialization
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

            // Set default dates to last month
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
                    console.error('Error:', error);
                } finally {
                    loadingMessage.style.display = 'none';
                    generateButton.disabled = false;
                }
            });

            // PDF Export Logic
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
                        backgroundColor: '#f3f6f8'
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
</body>
</html>
