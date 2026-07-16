// display_grupos.js
function renderGroupsReport(data, startDate, endDate) {
    const resumen = data.resumen_general;
    const grupos = data.grupos;

    // --- 1. Renderizar tarjetas de resumen (Widgets) ---
    const summaryGrid = document.getElementById('summaryGrid');
    
    // Asignar color de badge según la disponibilidad general
    let avgOkClass = 'zabbix-badge-ok';
    if (resumen.promedio_ok < 95) {
        avgOkClass = 'zabbix-badge-danger';
    } else if (resumen.promedio_ok < 99.5) {
        avgOkClass = 'zabbix-badge-warning';
    }

    let avgProblemClass = resumen.promedio_problem > 0 ? 'zabbix-badge-danger' : 'zabbix-badge-ok';

    summaryGrid.innerHTML = `
        <div class="zabbix-widget">
            <div class="zabbix-widget-header">Grupos Totales</div>
            <div class="zabbix-widget-content">
                ${resumen.total_grupos}
                <div class="zabbix-widget-subtext">Cargados de config</div>
            </div>
        </div>
        <div class="zabbix-widget">
            <div class="zabbix-widget-header">Grupos Activos</div>
            <div class="zabbix-widget-content">
                ${resumen.grupos_monitoreados}
                <div class="zabbix-widget-subtext">Con hosts monitoreados</div>
            </div>
        </div>
        <div class="zabbix-widget">
            <div class="zabbix-widget-header">Disponibilidad Promedio</div>
            <div class="zabbix-widget-content">
                <span class="zabbix-badge ${avgOkClass}" style="font-size: 20px; padding: 4px 12px;">
                    ${resumen.promedio_ok.toFixed(4)}%
                </span>
                <div class="zabbix-widget-subtext">Tiempo OK: ${resumen.tiempo_promedio_ok_estimado}</div>
            </div>
        </div>
        <div class="zabbix-widget">
            <div class="zabbix-widget-header">Inactividad Promedio</div>
            <div class="zabbix-widget-content">
                <span class="zabbix-badge ${avgProblemClass}" style="font-size: 20px; padding: 4px 12px;">
                    ${resumen.promedio_problem.toFixed(4)}%
                </span>
                <div class="zabbix-widget-subtext">Tiempo Caído: ${resumen.tiempo_promedio_problem_estimado}</div>
            </div>
        </div>
    `;

    // --- 2. Renderizar la Tabla de Grupos ---
    const tableBody = document.getElementById('groupsTableBody');
    tableBody.innerHTML = '';

    grupos.forEach(group => {
        // Clases de badge de disponibilidad
        let okBadgeClass = 'zabbix-badge-ok';
        if (group.porcentaje_ok < 95) {
            okBadgeClass = 'zabbix-badge-danger';
        } else if (group.porcentaje_ok < 99.5) {
            okBadgeClass = 'zabbix-badge-warning';
        }

        let probBadgeClass = group.porcentaje_problem > 0 ? 'zabbix-badge-danger' : 'zabbix-badge-ok';

        const tr = document.createElement('tr');
        
        // Enlace para drill-down a la página de host index.php pasándole parámetros
        const drillDownUrl = `index.php?hostgroup=${encodeURIComponent(group.name)}&startDate=${startDate}&endDate=${endDate}`;

        tr.innerHTML = `
            <td>
                <a href="${drillDownUrl}" target="_blank" class="text-link font-semibold">${group.name}</a>
            </td>
            <td class="text-center font-mono">${group.total_hosts}</td>
            <td class="text-center">
                <span class="zabbix-badge ${okBadgeClass}">${group.porcentaje_ok.toFixed(4)}%</span>
            </td>
            <td class="text-center">
                <span class="zabbix-badge ${probBadgeClass}">${group.porcentaje_problem.toFixed(4)}%</span>
            </td>
            <td class="font-mono text-gray-600">${group.tiempo_ok_formateado}</td>
            <td class="font-mono text-gray-600">${group.tiempo_problem_formateado}</td>
            <td class="text-center">
                <a href="${drillDownUrl}" target="_blank" class="zabbix-btn zabbix-btn-primary" style="height: 20px; line-height: 1; padding: 2px 8px; font-size: 10px;">
                    Ver Equipos
                </a>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    // --- 3. Renderizar el gráfico horizontal de barras de Chart.js ---
    const ctx = document.getElementById('groupsChart').getContext('2d');

    // Destruir gráfico previo si existe para evitar problemas de solapamiento
    if (window.groupsChartInstance instanceof Chart) {
        window.groupsChartInstance.destroy();
    }

    // Preparar datos para el gráfico
    const labels = grupos.map(g => g.name);
    const availabilities = grupos.map(g => g.porcentaje_ok);

    // Colores dinámicos para las barras
    const backgroundColors = availabilities.map(val => {
        if (val >= 99.5) return 'rgba(46, 125, 50, 0.65)';     // Verde Zabbix
        if (val >= 95.0) return 'rgba(239, 108, 0, 0.65)';     // Naranja Zabbix
        return 'rgba(198, 40, 40, 0.65)';                       // Rojo Zabbix
    });

    const borderColors = availabilities.map(val => {
        if (val >= 99.5) return 'rgba(46, 125, 50, 1)';
        if (val >= 95.0) return 'rgba(239, 108, 0, 1)';
        return 'rgba(198, 40, 40, 1)';
    });

    window.groupsChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Disponibilidad del Grupo (%)',
                data: availabilities,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 1,
                barThickness: 15
            }]
        },
        options: {
            indexAxis: 'y', // Gráfico horizontal para legibilidad de nombres
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: '% Disponibilidad (ICMP Ping)'
                    },
                    grid: {
                        color: '#dfe4e8'
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` Disponibilidad: ${context.raw.toFixed(4)}%`;
                        }
                    }
                }
            }
        }
    });
}
