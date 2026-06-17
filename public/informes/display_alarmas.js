// display_alarmas.js
function renderAlarmReport(data, containerElement) {
    const resumen = data.resumen_general;
    const por_equipo = data.por_equipo;
    const por_tipo = data.por_tipo_alarma;
    const lista_alarmas = data.lista_alarmas;

    const severityNames = [
        "No clasificado",
        "Información",
        "Advertencia",
        "Promedio",
        "Alta",
        "Desastre"
    ];

    const severityBadges = [
        "badge-severity-0",
        "badge-severity-1",
        "badge-severity-2",
        "badge-severity-3",
        "badge-severity-4",
        "badge-severity-5"
    ];

    const severityColors = [
        "#6c757d", // No clasificado (Gris)
        "#17a2b8", // Información (Azul claro)
        "#ffc107", // Advertencia (Amarillo)
        "#fd7e14", // Promedio (Naranja)
        "#e83e8c", // Alta (Rosado/Fucsia)
        "#dc3545"  // Desastre (Rojo)
    ];

    // Limitar listas muy largas para no sobrecargar el navegador en la vista
    const topTipos = por_tipo.slice(0, 15); // Top 15 tipos de alarmas
    const listLimit = 100;
    const alarmasMostradas = lista_alarmas.slice(0, listLimit);

    let htmlContent = `
        <div class="text-center mb-4">
            <h3 class="font-weight-bold text-primary mb-2"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Informe de Distribución de Alarmas</h3>
            <p class="text-muted">
                Período: <span class="font-weight-bold text-dark">${resumen.fecha_inicio_analisis}</span> al 
                <span class="font-weight-bold text-dark">${resumen.fecha_fin_analisis}</span> | 
                Grupo: <span class="badge badge-secondary font-weight-bold p-2">${document.getElementById('hostgroup').value || 'Todos los Grupos'}</span>
            </p>
        </div>

        <!-- Tarjetas de Resumen Rápido -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="info-box shadow-sm" style="border-left: 5px solid #dc3545;">
                    <span class="info-box-icon bg-danger text-white"><i class="fas fa-bell"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Total Alarmas</span>
                        <span class="info-box-number h4 font-weight-bold mb-0">${resumen.total_alarmas}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="info-box shadow-sm" style="border-left: 5px solid #17a2b8;">
                    <span class="info-box-icon bg-info text-white"><i class="fas fa-desktop"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Equipos Afectados</span>
                        <span class="info-box-number h4 font-weight-bold mb-0">${resumen.total_equipos_afectados}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="info-box shadow-sm" style="border-left: 5px solid #fd7e14;">
                    <span class="info-box-icon bg-warning text-dark"><i class="fas fa-exclamation-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Gravedad Promedio</span>
                        <span class="info-box-number h4 font-weight-bold mb-0">${resumen.total_alarmas > 0 ? getAverageSeverityName(resumen.por_severidad) : 'N/A'}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="info-box shadow-sm" style="border-left: 5px solid #6c757d;">
                    <span class="info-box-icon bg-secondary text-white"><i class="fas fa-calendar-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Tipos de Alarmas</span>
                        <span class="info-box-number h4 font-weight-bold mb-0">${por_tipo.length}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila de Gráficos Distribuidos -->
        <div class="row mb-4">
            <!-- Gráfico 1: Por Gravedad -->
            <div class="col-md-6">
                <div class="card card-outline card-danger shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-chart-pie mr-2 text-danger"></i>Alarmas por Gravedad (Severidad)</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="severityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico 2: Top Equipos -->
            <div class="col-md-6">
                <div class="card card-outline card-primary shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-chart-bar mr-2 text-primary"></i>Top 10 Equipos con Más Alarmas</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="topHostsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla 1: Distribución Detallada por Equipos -->
        <div class="card card-outline card-primary mb-4 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-table mr-2 text-primary"></i>Distribución de Gravedad por Equipo</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 text-center" style="font-size:0.9rem">
                        <thead class="thead-dark">
                            <tr>
                                <th class="text-left">Nombre de Equipo</th>
                                <th>No clasificado</th>
                                <th>Información</th>
                                <th>Advertencia</th>
                                <th>Promedio</th>
                                <th>Alta</th>
                                <th>Desastre</th>
                                <th class="bg-primary text-white font-weight-bold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${por_equipo.length === 0 ? `
                                <tr><td colspan="8" class="text-muted py-4">No se registraron alarmas en el período.</td></tr>
                            ` : por_equipo.map(eq => `
                                <tr>
                                    <td class="text-left font-weight-bold text-dark">${eq.name}</td>
                                    <td><span class="badge badge-light border">${eq.severities[0]}</span></td>
                                    <td><span class="badge badge-info">${eq.severities[1]}</span></td>
                                    <td><span class="badge badge-warning">${eq.severities[2]}</span></td>
                                    <td><span class="badge badge-secondary" style="background-color: #fd7e14; color: white;">${eq.severities[3]}</span></td>
                                    <td><span class="badge badge-danger" style="background-color: #e83e8c; color: white;">${eq.severities[4]}</span></td>
                                    <td><span class="badge badge-danger">${eq.severities[5]}</span></td>
                                    <td class="bg-light font-weight-bold text-primary">${eq.total}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Tabla 2: Tipos de Alarma más Comunes -->
            <div class="col-lg-6 mb-4">
                <div class="card card-outline card-warning shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-list-ol mr-2 text-warning"></i>Tipos de Alarmas Más Frecuentes</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0" style="font-size:0.85rem">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Alarma / Descripción</th>
                                        <th>Gravedad</th>
                                        <th class="text-center">Eventos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${topTipos.length === 0 ? `
                                        <tr><td colspan="3" class="text-muted text-center py-4">Sin datos de alarmas.</td></tr>
                                    ` : topTipos.map(t => `
                                        <tr>
                                            <td class="font-weight-bold text-dark text-wrap">${t.name}</td>
                                            <td><span class="badge ${severityBadges[t.severity]} font-weight-bold p-1">${severityNames[t.severity]}</span></td>
                                            <td class="text-center font-weight-bold text-primary">${t.count}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla 3: Últimos Eventos de Alarma -->
            <div class="col-lg-6 mb-4">
                <div class="card card-outline card-danger shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-history mr-2 text-danger"></i>Historial de Eventos de Alarma</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                            <table class="table table-hover table-striped mb-0" style="font-size:0.85rem">
                                <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th>Fecha / Hora</th>
                                        <th>Equipo</th>
                                        <th>Alarma</th>
                                        <th>Gravedad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${alarmasMostradas.length === 0 ? `
                                        <tr><td colspan="4" class="text-muted text-center py-4">No hay eventos registrados en el período.</td></tr>
                                    ` : alarmasMostradas.map(al => `
                                        <tr>
                                            <td class="text-nowrap">${al.date}</td>
                                            <td class="font-weight-bold text-dark">${al.host}</td>
                                            <td class="text-danger text-wrap">${al.name}</td>
                                            <td><span class="badge ${severityBadges[al.severity]} font-weight-bold p-1">${severityNames[al.severity]}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        ${lista_alarmas.length > listLimit ? `
                            <div class="card-footer bg-light text-center py-2">
                                <small class="text-muted font-weight-bold">Mostrando los primeros ${listLimit} de ${resumen.total_alarmas} eventos totales.</small>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;

    containerElement.innerHTML = htmlContent;

    // --- RENDERIZADO DE GRÁFICOS CON CHART.JS ---

    // 1. Gráfico de Rosca de Severidades
    const severityLabels = severityNames.filter((_, idx) => resumen.por_severidad[idx] > 0 || resumen.por_severidad[String(idx)] > 0);
    const severityData = severityNames.map((_, idx) => resumen.por_severidad[idx] ?? resumen.por_severidad[String(idx)] ?? 0).filter(count => count > 0);
    const filterColors = severityColors.filter((_, idx) => (resumen.por_severidad[idx] ?? resumen.por_severidad[String(idx)] ?? 0) > 0);

    const ctxSeverity = document.getElementById('severityChart').getContext('2d');
    if (window.sevChart instanceof Chart) {
        window.sevChart.destroy();
    }

    if (severityData.length > 0) {
        window.sevChart = new Chart(ctxSeverity, {
            type: 'doughnut',
            data: {
                labels: severityLabels,
                datasets: [{
                    data: severityData,
                    backgroundColor: filterColors,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { weight: 'bold' }
                        }
                    }
                }
            }
        });
    } else {
        // Mostrar mensaje si no hay alarmas
        ctxSeverity.font = "16px sans-serif";
        ctxSeverity.fillStyle = "#6c757d";
        ctxSeverity.textAlign = "center";
        ctxSeverity.fillText("No hay alarmas para graficar", ctxSeverity.canvas.width / 2, ctxSeverity.canvas.height / 2);
    }

    // 2. Gráfico de Barras Horizontales de Top 10 Equipos
    const topHosts = por_equipo.slice(0, 10);
    const hostLabels = topHosts.map(eq => eq.name);
    const hostData = topHosts.map(eq => eq.total);

    const ctxHosts = document.getElementById('topHostsChart').getContext('2d');
    if (window.hostsChart instanceof Chart) {
        window.hostsChart.destroy();
    }

    if (hostData.length > 0) {
        window.hostsChart = new Chart(ctxHosts, {
            type: 'bar',
            data: {
                labels: hostLabels,
                datasets: [{
                    label: 'Cantidad de Alarmas',
                    data: hostData,
                    backgroundColor: 'rgba(255, 92, 5, 0.75)',
                    borderColor: 'rgba(255, 92, 5, 1)',
                    borderWidth: 1.5,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // Hace las barras horizontales
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        title: { display: true, text: 'Total de Alarmas' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    } else {
        ctxHosts.font = "16px sans-serif";
        ctxHosts.fillStyle = "#6c757d";
        ctxHosts.textAlign = "center";
        ctxHosts.fillText("No hay alarmas para graficar", ctxHosts.canvas.width / 2, ctxHosts.canvas.height / 2);
    }
}

/**
 * Helper para calcular la severidad promedio ponderada y retornar su nombre.
 */
function getAverageSeverityName(por_severidad) {
    let totalScore = 0;
    let totalAlarms = 0;
    for (let i = 0; i <= 5; i++) {
        const count = por_severidad[i] ?? por_severidad[String(i)] ?? 0;
        totalScore += count * i;
        totalAlarms += count;
    }
    if (totalAlarms === 0) return 'Ninguna';
    const avg = Math.round(totalScore / totalAlarms);
    
    const severityNames = [
        "No clasificado",
        "Información",
        "Advertencia",
        "Promedio",
        "Alta",
        "Desastre"
    ];
    return severityNames[avg] || 'Ninguna';
}
