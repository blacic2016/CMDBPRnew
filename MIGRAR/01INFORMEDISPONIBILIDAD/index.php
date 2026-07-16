<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leterago - Informe de Disponibilidad</title>
    
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    
    <style>
        /* Diseño limpio, fondo totalmente blanco */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6; /* Blanco/Gris muy claro */
            color: #333333; /* Texto Negro/Gris oscuro */
            display: flex;
            justify-content: flex-start;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            flex-direction: column;
            padding: 20px;
        }
        /* Contenedor principal sin fondo oscuro */
        .main-content-container { 
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f4f7f6; 
        }

        .header-logo-container {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 0 10px;
        }
        .header-logo-container img {
            height: 60px;
            width: auto;
        }
        .header-logo-container h1 {
            color: #007bff;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .form-container, .report-container {
            background-color: #ffffff; /* Fondo Blanco */
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); /* Sombra suave */
            text-align: center;
            width: 100%;
            margin: 10px 0;
            border-top: 5px solid #007bff;
        }
        .form-container {
            max-width: 900px;
        }
        h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 2rem;
        }
        label {
            color: #555;
            font-weight: 600;
            text-align: left;
        }
        input[type="text"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            background-color: #ffffff;
            color: #333;
        }
        .date-inputs-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            width: 100%; 
            justify-content: space-between;
        }
        .date-input-control {
            flex: 1; 
            min-width: 200px;
            text-align: left;
        }
        .date-input-control select {
            /* Mantiene la simetría */
            height: 43px;
        }

        /* ESTILO DE BOTONES MÁS GRANDES Y PROMINENTES */
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        .button-group button {
            padding: 16px 35px; /* Más padding para hacerlos más grandes */
            font-size: 18px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        #generateReportButton {
            background-color: #007bff; /* Azul primario de Leterago */
            color: white;
        }
        #generateReportButton:hover {
            background-color: #0056b3;
        }
        #pdfExportButton {
            background-color: #dc3545; /* Rojo para PDF */
            color: white;
        }
        #pdfExportButton:hover {
            background-color: #c82333;
        }
        
        /* Estilos de Tablas (Claros) */
        .report-container table {
            background-color: #ffffff; 
            border: 1px solid #e0e0e0;
        }
        .report-container th {
            background-color: #e8f0fe; /* Azul claro para cabeceras */
            color: #34495e;
        }
        .report-container tr:nth-child(even) {
            background-color: #f9f9f9; /* Bandas sutiles */
        }
        /* Colores de estado con buen contraste en fondo blanco */
        .ok-status {
            background-color: #d4edda !important; /* Verde claro */
            color: #155724 !important; /* Verde oscuro */
            font-weight: 600;
        }
        .problem-status {
            background-color: #f8d7da !important; /* Rojo claro */
            color: #721c24 !important; /* Rojo oscuro */
            font-weight: 600;
        }
        .chart-container {
            background-color: #ffffff; 
        }
        
    </style>
</head>
<body>
    <div class="main-content-container">
        <div class="header-logo-container">
            <img src="https://www.leterago.com.ec/wp-content/uploads/2023/09/leterago_colorDistorcionado.svg" alt="Leterago Logo">
            <div class="text-right">
                <h1 class="text-blue-600 font-bold text-2xl">Informe de Disponibilidad Zabbix</h1>
                <div class="flex justify-end gap-2 mt-1 text-xs">
                    <span class="font-bold text-gray-800">Vista por Equipos</span>
                    <span class="text-gray-400">|</span>
                    <a href="grupos.php" class="text-blue-600 hover:underline font-semibold">Vista por Grupos</a>
                </div>
            </div>
        </div>

        <div class="form-container">
            <h2 class="text-3xl">Parámetros del Informe</h2>
            <form id="reportForm">
                <div class="date-inputs-group">
                    <div class="date-input-control">
                        <label for="startDatePicker">Fecha de Inicio:</label>
                        <input type="text" id="startDatePicker" required class="focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="date-input-control">
                        <label for="endDatePicker">Fecha Fin:</label>
                        <input type="text" id="endDatePicker" required class="focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="date-input-control">
                        <label for="hostgroup">Grupo de Host:</label>
                        <select name="hostgroup" id="hostgroup" required class="focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Cargando grupos...</option>
                        </select>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" id="generateReportButton">GENERAR INFORME</button>
                    <button type="button" id="pdfExportButton" style="display: none;">EXPORTAR A PDF</button>
                </div>
            </form>
            <div id="loading-message">Generando informe, por favor espere...</div>
            <div id="error-message" class="error-message" style="display: none;"></div>
        </div>

        <div id="reportContainer" class="report-container" style="display: none;">
        </div>
    </div>
    
    <script src="display_report.js"></script>
    <script>
        // Lógica de inicialización y manejo de eventos (Mismos que antes, solo el CSS cambia la apariencia)
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

            // Establecer fechas iniciales por defecto
            var today = new Date();
            var firstDayOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            var lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);

            $("#startDatePicker").datepicker("setDate", firstDayOfLastMonth);
            $("#endDatePicker").datepicker("setDate", lastDayOfLastMonth);


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

                    // Éxito: Mostrar informe y botón PDF
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

            // Lógica para exportar a PDF
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
                        // Configurar el color de fondo para que coincida con el tema blanco
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
                            position = heightLeft - imgImgHeight;
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
                    pdfExportButton.textContent = 'EXPORTAR A PDF';
                }
            });

        });
    </script>
</body>
</html>