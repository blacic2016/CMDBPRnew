let selectedAnalysisApMac = null;

function initApAnalysis() {
    const gridContainer = document.getElementById('ap-analysis-grid');
    if (!gridContainer) return;

    if (!window.appData || !window.appData.aps || !window.appData.clients) {
        gridContainer.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <p>Cargando datos de Access Points y Clientes...</p>
            </div>
        `;
        return;
    }

    const searchInput = document.getElementById('ap-analysis-search');
    const statusSelect = document.getElementById('ap-analysis-status');

    // Register search/filter listeners once
    if (searchInput && !searchInput.dataset.listener) {
        searchInput.addEventListener('input', renderAnalysis);
        searchInput.dataset.listener = 'true';
    }
    if (statusSelect && !statusSelect.dataset.listener) {
        statusSelect.addEventListener('change', renderAnalysis);
        statusSelect.dataset.listener = 'true';
    }

    renderAnalysis();
}

function calculateApMetrics(ap) {
    const clients = window.appData.clients.filter(c => c.apIP === ap.ip);
    
    // 1. CPU
    const cpu = ap.metrics && ap.metrics['AP CPU utilizacion'] ? parseFloat(ap.metrics['AP CPU utilizacion'].value) : 0;
    
    // 2. Memory (Assume total AP memory is 1GB for calculation of utilization if we only have Free Memory)
    const totalMem = 1073741824; // 1 GB
    const freeMemVal = ap.metrics && ap.metrics['AP Memory Free'] ? parseFloat(ap.metrics['AP Memory Free'].value) : totalMem;
    const usedMem = Math.max(0, totalMem - freeMemVal);
    const memPct = Math.round((usedMem / totalMem) * 100);

    // 3. Radios Status
    const r1Status = ap.metrics && ap.metrics['AP Radio 1 Status'] ? parseInt(ap.metrics['AP Radio 1 Status'].value) : 1;
    const r2Status = ap.metrics && ap.metrics['AP Radio 2 Status'] ? parseInt(ap.metrics['AP Radio 2 Status'].value) : 1;

    // 4. Client details
    const totalClients = clients.length;
    const lowSnrClients = clients.filter(c => c.snr < 15);
    const lowSnrCount = lowSnrClients.length;
    const lowSnrPct = totalClients > 0 ? Math.round((lowSnrCount / totalClients) * 100) : 0;

    // 5. Total Throughput
    const totalBw = clients.reduce((sum, c) => sum + (c.txThroughput || 0) + (c.rxThroughput || 0), 0);

    // 6. Calculate Health Score
    let score = 100;
    const anomalies = [];
    const warnings = [];

    if (cpu > 80) {
        score -= 30;
        anomalies.push(`🔴 CPU Crítico (${cpu}%)`);
    } else if (cpu > 60) {
        score -= 15;
        warnings.push(`⚠️ CPU Alto (${cpu}%)`);
    }

    if (memPct > 85) {
        score -= 20;
        anomalies.push(`🔴 Memoria Crítica (${memPct}%)`);
    } else if (memPct > 70) {
        score -= 10;
        warnings.push(`⚠️ Memoria Alta (${memPct}%)`);
    }

    if (r1Status !== 1) {
        score -= 25;
        anomalies.push(`🔴 Radio 1 Inactivo`);
    }
    if (r2Status !== 1) {
        score -= 25;
        anomalies.push(`🔴 Radio 2 Inactivo`);
    }

    if (totalClients > 40) {
        score -= 20;
        anomalies.push(`🔴 Congestión: ${totalClients} clientes`);
    } else if (totalClients > 20) {
        score -= 8;
        warnings.push(`⚠️ Tráfico Alto: ${totalClients} clientes`);
    }

    if (lowSnrPct > 35 && totalClients > 2) {
        score -= 15;
        anomalies.push(`🔴 Clientes Pegados: ${lowSnrPct}% con SNR baja`);
    } else if (lowSnrPct > 15 && totalClients > 2) {
        score -= 8;
        warnings.push(`⚠️ Ruido/Baja Señal: ${lowSnrPct}% con SNR baja`);
    }

    score = Math.max(0, score);

    let status = 'HEALTHY';
    if (score < 60) {
        status = 'CRITICAL';
    } else if (score < 85) {
        status = 'WARNING';
    }

    return {
        cpu,
        memPct,
        freeMem: freeMemVal,
        r1Status,
        r2Status,
        totalClients,
        lowSnrCount,
        lowSnrPct,
        totalBw,
        score,
        status,
        anomalies,
        warnings,
        clients
    };
}

function renderAnalysis() {
    const gridContainer = document.getElementById('ap-analysis-grid');
    const searchVal = document.getElementById('ap-analysis-search')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('ap-analysis-status')?.value || 'ALL';

    if (!gridContainer) return;
    gridContainer.innerHTML = '';

    const processedAps = window.appData.aps.map(ap => {
        return { ap, analysis: calculateApMetrics(ap) };
    });

    // 1. Calculate and update global health dashboard
    let sumScore = 0;
    let criticalCount = 0;
    let totalLowSnr = 0;
    let totalBwGlobal = 0;

    processedAps.forEach(item => {
        sumScore += item.analysis.score;
        if (item.analysis.status === 'CRITICAL') criticalCount++;
        totalLowSnr += item.analysis.lowSnrCount;
        totalBwGlobal += item.analysis.totalBw;
    });

    const avgScore = processedAps.length > 0 ? Math.round(sumScore / processedAps.length) : 0;
    
    // Update global cards
    const gHealth = document.getElementById('global-health-score');
    const gCritical = document.getElementById('global-critical-count');
    const gLowSnr = document.getElementById('global-low-snr-count');
    const gBw = document.getElementById('global-total-bw');

    if (gHealth) {
        gHealth.textContent = `${avgScore}%`;
        gHealth.className = 'summary-value ' + (avgScore >= 85 ? 'healthy-text' : (avgScore >= 60 ? 'warning-text' : 'critical-text'));
    }
    if (gCritical) {
        gCritical.textContent = criticalCount;
        gCritical.className = 'summary-value ' + (criticalCount > 0 ? 'critical-text' : '');
    }
    if (gLowSnr) gLowSnr.textContent = totalLowSnr;
    if (gBw) gBw.textContent = formatThroughput(totalBwGlobal);

    // 2. Filter APs
    const filteredAps = processedAps.filter(item => {
        const matchesSearch = item.ap.name.toLowerCase().includes(searchVal) || item.ap.ip.includes(searchVal);
        const matchesStatus = statusFilter === 'ALL' || item.analysis.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    if (filteredAps.length === 0) {
        gridContainer.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <p>No se encontraron Access Points con los filtros aplicados.</p>
            </div>
        `;
        return;
    }

    // Sort: lower scores first (problems first)
    filteredAps.sort((a, b) => a.analysis.score - b.analysis.score);

    // 3. Render grid cards
    filteredAps.forEach(item => {
        const { ap, analysis } = item;
        const scoreColor = analysis.score >= 85 ? '#2ecc71' : (analysis.score >= 60 ? '#f39c12' : '#ff6b6b');
        const dashOffset = 2 * Math.PI * 25 * (1 - analysis.score / 100);

        const card = document.createElement('div');
        card.className = `ap-analysis-card ${selectedAnalysisApMac === ap.mac ? 'selected' : ''}`;
        card.onclick = () => selectApForAnalysis(ap.mac, analysis, ap);

        card.innerHTML = `
            <div class="health-gauge-container">
                <svg class="health-gauge-svg" viewBox="0 0 60 60">
                    <circle class="gauge-bg" cx="30" cy="30" r="25"></circle>
                    <circle class="gauge-fill" cx="30" cy="30" r="25" 
                            stroke="${scoreColor}" 
                            stroke-dasharray="157.08" 
                            stroke-dashoffset="${dashOffset}"></circle>
                </svg>
                <span class="health-score-text">${analysis.score}</span>
            </div>
            <div class="ap-analysis-info">
                <span class="ap-analysis-name" title="${ap.name}">${ap.name}</span>
                <span class="ap-analysis-ip">${ap.ip}</span>
                <div class="ap-analysis-metrics-summary">
                    <span class="metric-pill">CPU: ${analysis.cpu}%</span>
                    <span class="metric-pill">Clientes: ${analysis.totalClients}</span>
                    ${analysis.anomalies.length > 0 ? `<span class="metric-pill anomaly-pill">${analysis.anomalies.length} Anom.</span>` : ''}
                    ${analysis.warnings.length > 0 && analysis.anomalies.length === 0 ? `<span class="metric-pill warning-pill">${analysis.warnings.length} Advt.</span>` : ''}
                </div>
            </div>
        `;

        gridContainer.appendChild(card);
    });

    // Auto-select first AP if none is selected
    if (!selectedAnalysisApMac && filteredAps.length > 0) {
        const first = filteredAps[0];
        selectApForAnalysis(first.ap.mac, first.analysis, first.ap);
    } else if (selectedAnalysisApMac) {
        // Refresh sidebar detail for selected AP
        const selectedItem = processedAps.find(item => item.ap.mac === selectedAnalysisApMac);
        if (selectedItem) {
            selectApForAnalysis(selectedItem.ap.mac, selectedItem.analysis, selectedItem.ap, true);
        } else {
            document.getElementById('analysis-sidebar-content').innerHTML = `
                <div class="empty-state">Seleccione un AP de la lista para ver su análisis detallado</div>
            `;
        }
    }
}

function selectApForAnalysis(mac, analysis, ap, skipUiSelect = false) {
    selectedAnalysisApMac = mac;

    if (!skipUiSelect) {
        document.querySelectorAll('.ap-analysis-card').forEach(card => {
            card.classList.remove('selected');
        });
        const activeCard = Array.from(document.querySelectorAll('.ap-analysis-card')).find(c => c.textContent.includes(ap.ip));
        if (activeCard) activeCard.classList.add('selected');
    }

    const sidebarContent = document.getElementById('analysis-sidebar-content');
    if (!sidebarContent) return;

    // Generate recommendation cards
    let recsHtml = '';
    
    if (analysis.cpu > 80) {
        recsHtml += `
            <div class="recommendation-card critical">
                <span class="recommendation-icon">🔴</span>
                <div class="recommendation-text">
                    <strong>Sobrecarga de CPU:</strong> El AP está experimentando un procesamiento muy elevado (${analysis.cpu}%). Se recomienda reiniciar el equipo desde Zabbix, revisar procesos colgados o migrar clientes.
                </div>
            </div>
        `;
    }
    if (analysis.memPct > 85) {
        recsHtml += `
            <div class="recommendation-card critical">
                <span class="recommendation-icon">🔴</span>
                <div class="recommendation-text">
                    <strong>Memoria Libre Baja:</strong> La memoria del AP está casi llena (${analysis.memPct}% utilizada). Esto puede provocar inhibición de conexiones o reinicios espontáneos.
                </div>
            </div>
        `;
    }
    if (analysis.r1Status !== 1 || analysis.r2Status !== 1) {
        recsHtml += `
            <div class="recommendation-card critical">
                <span class="recommendation-icon">📡</span>
                <div class="recommendation-text">
                    <strong>Falla de Radiofrecuencia:</strong> Una o más bandas de radio reportan estado inactivo. Verifique la configuración física del puerto de red o si el hardware está degradado.
                </div>
            </div>
        `;
    }
    if (analysis.totalClients > 40) {
        recsHtml += `
            <div class="recommendation-card warning">
                <span class="recommendation-icon">👥</span>
                <div class="recommendation-text">
                    <strong>Congestión de Clientes:</strong> Hay demasiados dispositivos activos (${analysis.totalClients}). Se sugiere aumentar el ancho de banda del canal o desplegar un AP vecino para balancear la densidad.
                </div>
            </div>
        `;
    }
    if (analysis.lowSnrPct > 20) {
        recsHtml += `
            <div class="recommendation-card warning">
                <span class="recommendation-icon">⚠️</span>
                <div class="recommendation-text">
                    <strong>Inhibición por Clientes Pegados (Sticky Clients):</strong> El ${analysis.lowSnrPct}% de los clientes conectados tiene baja señal (SNR < 15). Estos dispositivos obligan al AP a retransmitir constantemente, bajando la velocidad global de todo el AP. Se recomienda habilitar un umbral de desconexión mínima (min-RSSI) o revisar la potencia de transmisión.
                </div>
            </div>
        `;
    }

    if (!recsHtml) {
        recsHtml = `
            <div class="recommendation-card info">
                <span class="recommendation-icon">🟢</span>
                <div class="recommendation-text">
                    <strong>Funcionamiento Óptimo:</strong> El Access Point está operando de forma saludable dentro de todos los parámetros de red y capacidad recomendados.
                </div>
            </div>
        `;
    }

    // Clients list html
    let clientsRows = '';
    analysis.clients.sort((a,b) => (a.txThroughput + a.rxThroughput) - (b.txThroughput + b.rxThroughput));
    analysis.clients.forEach(c => {
        const usage = (c.txThroughput || 0) + (c.rxThroughput || 0);
        clientsRows += `
            <tr>
                <td style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:110px;" title="${c.name}">${c.name}</td>
                <td>${c.ip}</td>
                <td style="font-weight:700; color: ${c.snr >= 25 ? '#2ecc71' : (c.snr >= 15 ? '#f39c12' : '#ff6b6b')}">${c.snr} dB</td>
                <td>${formatThroughput(usage)}</td>
            </tr>
        `;
    });

    sidebarContent.innerHTML = `
        <!-- Health score representation -->
        <div class="diagnostic-section" style="align-items:center; text-align:center; padding: 1rem 0; border-bottom: 1px solid var(--glass-border);">
            <span style="font-size: 0.8rem; font-weight:700; text-transform:uppercase; color:var(--text-secondary);">Salud Inalámbrica</span>
            <span style="font-size:3.5rem; font-weight:900; color: ${analysis.score >= 85 ? '#2ecc71' : (analysis.score >= 60 ? '#f39c12' : '#ff6b6b')}">${analysis.score}%</span>
            <span style="font-size: 0.85rem; font-weight:600; color: var(--text-primary);">${ap.name}</span>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">${ap.ip}</span>
        </div>

        <!-- Diagnostic Meters -->
        <div class="diagnostic-section">
            <span class="diagnostic-title">Parámetros de Rendimiento</span>
            
            <!-- CPU Meter -->
            <div class="diagnostic-meter">
                <div class="meter-header">
                    <span>Uso de CPU</span>
                    <span>${analysis.cpu}%</span>
                </div>
                <div class="meter-bg">
                    <div class="meter-bar" style="width: ${analysis.cpu}%; background: ${analysis.cpu > 80 ? '#ff6b6b' : (analysis.cpu > 60 ? '#f39c12' : '#2ecc71')}"></div>
                </div>
            </div>

            <!-- Memory Meter -->
            <div class="diagnostic-meter">
                <div class="meter-header">
                    <span>Memoria Utilizada</span>
                    <span>${analysis.memPct}%</span>
                </div>
                <div class="meter-bg">
                    <div class="meter-bar" style="width: ${analysis.memPct}%; background: ${analysis.memPct > 85 ? '#ff6b6b' : (analysis.memPct > 70 ? '#f39c12' : '#2ecc71')}"></div>
                </div>
            </div>

            <!-- Client Capacity -->
            <div class="diagnostic-meter">
                <div class="meter-header">
                    <span>Capacidad de Clientes</span>
                    <span>${analysis.totalClients} / 50</span>
                </div>
                <div class="meter-bg">
                    <div class="meter-bar" style="width: ${Math.min(100, (analysis.totalClients / 50) * 100)}%; background: ${analysis.totalClients > 40 ? '#ff6b6b' : (analysis.totalClients > 25 ? '#f39c12' : '#2ecc71')}"></div>
                </div>
            </div>

            <!-- Channel Congestion (Sticky client %) -->
            <div class="diagnostic-meter">
                <div class="meter-header">
                    <span>Dispositivos con Baja Señal</span>
                    <span>${analysis.lowSnrPct}%</span>
                </div>
                <div class="meter-bg">
                    <div class="meter-bar" style="width: ${analysis.lowSnrPct}%; background: ${analysis.lowSnrPct > 35 ? '#ff6b6b' : (analysis.lowSnrPct > 15 ? '#f39c12' : '#2ecc71')}"></div>
                </div>
            </div>
        </div>

        <!-- Recommendations Engine -->
        <div class="diagnostic-section">
            <span class="diagnostic-title">Recomendaciones Técnicas</span>
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                ${recsHtml}
            </div>
        </div>

        <!-- Connected Clients Signal/Usage -->
        <div class="diagnostic-section">
            <span class="diagnostic-title">Clientes Conectados (${analysis.totalClients})</span>
            <div style="max-height: 250px; overflow-y:auto; border: 1px solid var(--glass-border); border-radius: 8px;">
                <table class="analysis-clients-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>IP Address</th>
                            <th>Señal</th>
                            <th>Tráfico</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${clientsRows || '<tr><td colspan="4" style="text-align:center; color:var(--text-secondary)">No hay clientes en este AP</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}
