// display_grupos.js
function renderGroupsReport(data, startDate, endDate) {
    const resumen = data.resumen_general;
    const grupos = data.grupos;

    // --- 1. Renderizar tarjetas de resumen (Widgets) ---
    const summaryGrid = document.getElementById('summaryGrid');
    
    let avgOkClass = 'badge-success';
    if (resumen.promedio_ok < 95) {
        avgOkClass = 'badge-danger';
    } else if (resumen.promedio_ok < 99.5) {
        avgOkClass = 'badge-warning';
    }

    let avgProblemClass = resumen.promedio_problem > 0 ? 'badge-danger' : 'badge-success';

    summaryGrid.innerHTML = `
        <div class="col-md-3">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-navy"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-uppercase small font-weight-bold">Grupos Configurados</span>
                    <span class="info-box-number h4 font-weight-bold mb-0">${resumen.total_grupos}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-info"><i class="fas fa-server"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-uppercase small font-weight-bold">Grupos Activos</span>
                    <span class="info-box-number h4 font-weight-bold mb-0">${resumen.grupos_monitoreados}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-uppercase small font-weight-bold">Disponibilidad Promedio</span>
                    <div>
                        <span class="badge ${avgOkClass} p-2 font-weight-bold">${resumen.promedio_ok.toFixed(4)}%</span>
                    </div>
                    <small class="text-muted">Tiempo OK: ${resumen.tiempo_promedio_ok_estimado}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-uppercase small font-weight-bold">Inactividad Promedio</span>
                    <div>
                        <span class="badge ${avgProblemClass} p-2 font-weight-bold">${resumen.promedio_problem.toFixed(4)}%</span>
                    </div>
                    <small class="text-muted">Caído: ${resumen.tiempo_promedio_problem_estimado}</small>
                </div>
            </div>
        </div>
    `;

    // --- 2. Renderizar la Tabla de Grupos ---
    const tableBody = document.getElementById('groupsTableBody');
    tableBody.innerHTML = '';

    grupos.forEach(group => {
        let okBadgeClass = 'badge-success';
        if (group.porcentaje_ok < 95) {
            okBadgeClass = 'badge-danger';
        } else if (group.porcentaje_ok < 99.5) {
            okBadgeClass = 'badge-warning';
        }

        let probBadgeClass = group.porcentaje_problem > 0 ? 'badge-danger' : 'badge-success';

        const tr = document.createElement('tr');
        const drillDownUrl = `index.php?hostgroup=${encodeURIComponent(group.name)}&startDate=${startDate}&endDate=${endDate}`;

        tr.innerHTML = `
            <td>
                <a href="${drillDownUrl}" class="text-primary font-weight-bold"><i class="fas fa-search mr-1 small text-muted"></i>${group.name}</a>
            </td>
            <td class="text-center font-weight-bold">${group.total_hosts}</td>
            <td class="text-center">
                <span class="badge ${okBadgeClass} p-2">${group.porcentaje_ok.toFixed(4)}%</span>
            </td>
            <td class="text-center">
                <span class="badge ${probBadgeClass} p-2">${group.porcentaje_problem.toFixed(4)}%</span>
            </td>
            <td><code>${group.tiempo_ok_formateado}</code></td>
            <td><code>${group.tiempo_problem_formateado}</code></td>
            <td class="text-center">
                <a href="${drillDownUrl}" class="btn btn-xs btn-outline-primary font-weight-bold shadow-sm">
                    Ver Equipos
                </a>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    // --- 3. Renderizar el gráfico horizontal de barras de Chart.js ---
    const ctx = document.getElementById('groupsChart').getContext('2d');

    if (window.groupsChartInstance instanceof Chart) {
        window.groupsChartInstance.destroy();
    }

    const labels = grupos.map(g => g.name);
    const availabilities = grupos.map(g => g.porcentaje_ok);

    const backgroundColors = availabilities.map(val => {
        if (val >= 99.5) return 'rgba(0, 184, 212, 0.65)';
        if (val >= 95.0) return 'rgba(255, 92, 5, 0.65)';
        return 'rgba(220, 53, 69, 0.65)';
    });

    const borderColors = availabilities.map(val => {
        if (val >= 99.5) return 'rgba(0, 184, 212, 1)';
        if (val >= 95.0) return 'rgba(255, 92, 5, 1)';
        return 'rgba(220, 53, 69, 1)';
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
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: '% Disponibilidad (ICMP Ping)'
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
