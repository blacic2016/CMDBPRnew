// display_report.js
function renderReport(data, containerElement) {
    const resumen = data.resumen_general;
    const detalle_diario_por_host = data.detalle_diario_por_host;
    const eventos_de_problema = data.eventos_de_problema;

    let htmlContent = `
        <div class="text-center mb-4">
            <h3 class="font-weight-bold text-primary mb-2"><i class="fas fa-file-invoice mr-2"></i>Informe de Disponibilidad de Equipos</h3>
            <p class="text-muted">
                Período: <span class="font-weight-bold text-dark">${resumen.fecha_inicio_analisis}</span> al 
                <span class="font-weight-bold text-dark">${resumen.fecha_fin_analisis}</span> | 
                Grupo: <span class="badge badge-secondary font-weight-bold p-2">${document.getElementById('hostgroup').value}</span>
            </p>
        </div>

        <!-- Tarjetas de Resumen Rápido -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-desktop"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Total Equipos</span>
                        <span class="info-box-number h4 font-weight-bold mb-0">${resumen.total_hosts_analizados}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Disponibilidad Promedio</span>
                        <span class="info-box-number h4 font-weight-bold mb-0 text-success">${resumen.promedio_ok.toFixed(2)}%</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-uppercase small font-weight-bold">Inactividad Promedio</span>
                        <span class="info-box-number h4 font-weight-bold mb-0 text-danger">${resumen.promedio_problem.toFixed(2)}%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Disponibilidad Diaria -->
        <div class="card card-outline card-primary mb-4 shadow-sm">
            <div class="card-header">
                <h3 class="card-title font-weight-bold"><i class="fas fa-chart-bar mr-2"></i>Disponibilidad Promedio Diaria (%)</h3>
            </div>
            <div class="card-body">
                <div style="height: 320px; position: relative;">
                    <canvas id="dailyAvailabilityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla Resumen por Host -->
        <div class="card card-outline card-info mb-4 shadow-sm">
            <div class="card-header">
                <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-2"></i>Resumen por Host</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" style="font-size:0.9rem">
                        <thead class="thead-dark">
                            <tr>
                                <th>Host</th>
                                <th>IP</th>
                                <th class="text-center">% OK</th>
                                <th class="text-center">% Problemas</th>
                                <th>Tiempo OK Estimado</th>
                                <th>Tiempo Problema Estimado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${resumen.por_host.map(host_sum => {
                                const okBadge = host_sum.porcentaje_ok_total < 100 ? 'badge-warning' : 'badge-success';
                                const probBadge = host_sum.porcentaje_problem_total > 0 ? 'badge-danger' : 'badge-success';
                                return `
                                    <tr>
                                        <td class="font-weight-bold">${host_sum.host}</td>
                                        <td><code>${host_sum.ip}</code></td>
                                        <td class="text-center"><span class="badge ${okBadge} p-2">${host_sum.porcentaje_ok_total.toFixed(2)}%</span></td>
                                        <td class="text-center"><span class="badge ${probBadge} p-2">${host_sum.porcentaje_problem_total.toFixed(2)}%</span></td>
                                        <td>${host_sum.tiempo_ok_estimado}</td>
                                        <td>${host_sum.tiempo_problem_estimado}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabla de Eventos de Problema -->
        <div class="card card-outline card-danger mb-4 shadow-sm">
            <div class="card-header">
                <h3 class="card-title font-weight-bold"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Eventos de Problema</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    ${eventos_de_problema.length === 0 ? `
                        <p class="text-center py-4 my-0 text-muted">No se encontraron eventos de caída o problemas en el período.</p>
                    ` : `
                        <table class="table table-hover table-striped mb-0" style="font-size:0.85rem">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>EventID</th>
                                    <th>Host</th>
                                    <th>Trigger</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Duración</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${eventos_de_problema.map((event, index) => `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td><code>${event.eventid || '-'}</code></td>
                                        <td class="font-weight-bold">${event.host || '-'}</td>
                                        <td class="text-danger">${event.trigger || '-'}</td>
                                        <td>${event.inicio || '-'}</td>
                                        <td><span class="badge badge-light border">${event.fin || '-'}</span></td>
                                        <td class="font-weight-bold">${event.duracion || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `}
                </div>
            </div>
        </div>
    `;

    containerElement.innerHTML = htmlContent;

    // --- Chart.js rendering ---
    const groupedByDate = {};
    detalle_diario_por_host.forEach(item => {
        if (!groupedByDate[item.date]) {
            groupedByDate[item.date] = [];
        }
        groupedByDate[item.date].push(item.ok);
    });

    const labels = Object.keys(groupedByDate).sort((a, b) => {
        const parseDate = (dateString) => {
            const [day, month, year] = dateString.split('-').map(Number);
            return new Date(year, month - 1, day);
        };
        return parseDate(a) - parseDate(b);
    });

    const values = labels.map(date => {
        const sum = groupedByDate[date].reduce((acc, val) => acc + val, 0);
        return (sum / groupedByDate[date].length);
    });

    const backgroundColors = values.map(val => val < 100 ? 'rgba(255, 92, 5, 0.7)' : 'rgba(0, 184, 212, 0.7)');
    const borderColors = values.map(val => val < 100 ? 'rgba(255, 92, 5, 1)' : 'rgba(0, 184, 212, 1)');

    const ctx = document.getElementById('dailyAvailabilityChart').getContext('2d');

    if (window.dailyChart instanceof Chart) {
        window.dailyChart.destroy();
    }

    window.dailyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Disponibilidad Promedio Diaria (%)',
                data: values,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: '% Disponibilidad'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Fecha'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + context.raw.toFixed(2) + '%';
                        }
                    }
                }
            }
        }
    });
}
