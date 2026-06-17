// display_report.js
function renderReport(data, containerElement) {
    const resumen = data.resumen_general;
    const detalle_diario_por_host = data.detalle_diario_por_host;
    const eventos_de_problema = data.eventos_de_problema;

    // MODIFICACIÓN CLAVE: Actualizar el encabezado para reflejar el rango de fechas
    let htmlContent = `
        <h1 class="text-3xl mb-6 text-center">Informe de Disponibilidad de Equipos</h1>
        <p class="text-center text-lg mb-8">
            Informe generado para el período del 
            <span class="font-bold">${resumen.fecha_inicio_analisis}</span> al 
            <span class="font-bold">${resumen.fecha_fin_analisis}</span>.
            Para el grupo de hosts: <span class="font-bold">${document.getElementById('hostgroup').value}</span>
        </p>

        <h2 class="text-2xl mb-4">Resumen General</h2>
        <div class="overflow-x-auto mb-8">
            <table class="min-w-full rounded-lg">
                <thead>
                    <tr>
                        <th>Total Hosts</th>
                        <th>Promedio OK (%)</th>
                        <th>Promedio Problemas (%)</th>
                        <th>Fecha Inicio Análisis</th>
                        <th>Fecha Fin Análisis</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>${resumen.total_hosts_analizados}</td>
                        <td class="${resumen.promedio_ok < 100 ? 'problem-status' : 'ok-status'}">
                            ${resumen.promedio_ok.toFixed(2)}%
                        </td>
                        <td class="${resumen.promedio_problem > 0 ? 'problem-status' : 'ok-status'}">
                            ${resumen.promedio_problem.toFixed(2)}%
                        </td>
                        <td>${resumen.fecha_inicio_analisis}</td>
                        <td>${resumen.fecha_fin_analisis}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="text-2xl mb-4">Disponibilidad Promedio Diaria por Host</h2>
        <div class="chart-container mb-8">
            <canvas id="dailyAvailabilityChart" width="1000" height="400"></canvas>
        </div>

        <h2 class="text-2xl mb-4">Resumen por Host</h2>
        <div class="overflow-x-auto mb-8">
            <table class="min-w-full rounded-lg">
                <thead>
                    <tr>
                        <th>Host</th>
                        <th>IP</th>
                        <th>% OK</th>
                        <th>% Problemas</th>
                        <th>Tiempo OK Estimado</th>
                        <th>Tiempo Problema Estimado</th>
                    </tr>
                </thead>
                <tbody>
                    ${resumen.por_host.map(host_sum => `
                        <tr>
                            <td>${host_sum.host}</td>
                            <td>${host_sum.ip}</td>
                            <td class="${host_sum.porcentaje_ok_total < 100 ? 'problem-status' : 'ok-status'}">
                                ${host_sum.porcentaje_ok_total.toFixed(2)}%
                            </td>
                            <td class="${host_sum.porcentaje_problem_total > 0 ? 'problem-status' : 'ok-status'}">
                                ${host_sum.porcentaje_problem_total.toFixed(2)}%
                            </td>
                            <td>${host_sum.tiempo_ok_estimado}</td>
                            <td>${host_sum.tiempo_problem_estimado}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <h2 class="text-2xl mb-4">Eventos de Problema</h2>
        <div class="overflow-x-auto mb-8"> ${eventos_de_problema.length === 0 ? `
                <p class="text-center py-4">No se encontraron eventos de problema para el período seleccionado.</p>
            ` : `
                <table class="min-w-full rounded-lg text-sm"> <thead>
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
                                <td>${event.eventid || '-'}</td>
                                <td>${event.host || '-'}</td>
                                <td>${event.trigger || '-'}</td>
                                <td>${event.inicio || '-'}</td>
                                <td>${event.fin || '-'}</td>
                                <td>${event.duracion || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
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
        // Parse date strings in dd-mm-yyyy format to Date objects for correct sorting
        const parseDate = (dateString) => {
            const [day, month, year] = dateString.split('-').map(Number);
            return new Date(year, month - 1, day);
        };
        return parseDate(a) - parseDate(b);
    });

    const values = labels.map(date => {
        const sum = groupedByDate[date].reduce((acc, val) => acc + val, 0);
        return (sum / groupedByDate[date].length); // No .toFixed() here, let Chart.js handle precision
    });

    const backgroundColors = values.map(val => val < 100 ? 'rgba(255, 165, 0, 0.7)' : 'rgba(76, 175, 80, 0.7)');
    const borderColors = values.map(val => val < 100 ? 'rgba(255, 140, 0, 1)' : 'rgba(46, 125, 50, 1)');

    const ctx = document.getElementById('dailyAvailabilityChart').getContext('2d');

    // Destroy existing chart if it exists to prevent issues when re-rendering
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