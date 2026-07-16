let topologyState = {
    scale: 1,
    offsetX: 0,
    offsetY: 0,
    isDragging: false,
    lastMouseX: 0,
    lastMouseY: 0,
    nodes: []
};

const ZONES = [
    { min: 40, max: 100, dMin: 15, dMax: 45, color: 'rgba(63, 185, 80, 0.2)', label: 'Ex (>40)' },
    { min: 35, max: 40, dMin: 45, dMax: 78, color: 'rgba(63, 185, 80, 0.1)', label: 'MB (35-40)' },
    { min: 30, max: 35, dMin: 78, dMax: 110, color: 'rgba(210, 153, 34, 0.12)', label: 'B (30-35)' },
    { min: 25, max: 30, dMin: 110, dMax: 145, color: 'rgba(210, 153, 34, 0.08)', label: 'R (25-30)' },
    { min: 20, max: 25, dMin: 145, dMax: 180, color: 'rgba(248, 81, 73, 0.08)', label: 'Baj (20-25)' },
    { min: 0, max: 20, dMin: 180, dMax: 220, color: 'rgba(248, 81, 73, 0.04)', label: 'MBaj (<20)' }
];

function mapSnrToPreciseDist(snr) {
    for (const zone of ZONES) {
        if (snr >= zone.min) {
            const range = zone.max - zone.min;
            const distRange = zone.dMax - zone.dMin;
            const pct = range === 0 ? 0 : (snr - zone.min) / range;
            return zone.dMax - (pct * distRange);
        }
    }
    return 220;
}

function getSnrColor(snr) {
    for (const zone of ZONES) {
        if (snr >= zone.min) return zone.color.replace('0.2', '0.8').replace('0.1', '0.8');
    }
    return 'rgba(248, 81, 73, 0.8)';
}

function drawCoverageZones(ctx, scale, x, y) {
    [...ZONES].reverse().forEach(z => {
        ctx.beginPath();
        ctx.arc(x, y, z.dMax, 0, Math.PI * 2);
        ctx.fillStyle = z.color;
        ctx.fill();
        ctx.strokeStyle = z.color.replace(/0\.\d+\)/, '0.3)');
        ctx.lineWidth = 0.5 / scale;
        ctx.stroke();
    });
}

function initTopology() {
    const canvas = document.getElementById('topology-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    canvas.width = canvas.parentElement.clientWidth;
    canvas.height = canvas.parentElement.clientHeight;

    function processTopologyData() {
        topologyState.nodes = [];
        const apSelector = document.getElementById('ap-selector');
        
        let selectedApIps = [];
        if (apSelector) {
            selectedApIps = Array.from(apSelector.selectedOptions).map(opt => opt.value);
        }
        if (selectedApIps.length === 0) {
            selectedApIps = ['ALL'];
        }

        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;

        let displayAps = appData.aps;
        if (!selectedApIps.includes('ALL')) {
            displayAps = appData.aps.filter(ap => selectedApIps.includes(ap.ip));
        }

        if (displayAps.length > 0) {
            const radius = Math.min(canvas.width, canvas.height) * 0.35;
            displayAps.forEach((ap, index) => {
                const angle = (index / displayAps.length) * Math.PI * 2;
                const x = centerX + (displayAps.length === 1 ? 0 : Math.cos(angle) * radius);
                const y = centerY + (displayAps.length === 1 ? 0 : Math.sin(angle) * radius);

                topologyState.nodes.push({
                    type: 'ap', id: ap.mac, name: ap.name, ip: ap.ip, x, y, originalData: ap
                });

                const apClients = appData.clients.filter(c => c.apIP === ap.ip);
                apClients.forEach((client, cIndex) => {
                    const snr = client.snr || 10;
                    const preciseDist = mapSnrToPreciseDist(snr);
                    const cAngle = (cIndex / apClients.length) * Math.PI * 2;

                    topologyState.nodes.push({
                        type: 'client', id: client.mac || client.key, name: client.name || 'Desconocido',
                        ip: client.ip || 'N/A', x: x + Math.cos(cAngle) * preciseDist, y: y + Math.sin(cAngle) * preciseDist,
                        snr: snr, originalData: client
                    });
                });
            });
        }

        updateTopClients(selectedApIps);
    }

    const style = getComputedStyle(document.body);
    const lineColor = style.getPropertyValue('--line-color').trim();
    const textColor = style.getPropertyValue('--text-primary').trim();
    const isLight = !document.body.classList.contains('dark-theme');

    function drawLegend() {
        const legendX = 20;
        const legendY = canvas.height - 30;
        const patchSize = 10;
        ctx.font = '9px Inter';
        ctx.textAlign = 'left';
        ZONES.forEach((z, i) => {
            const x = legendX + (i * 90);
            ctx.fillStyle = z.color.replace(/0\.\d+\)/, '0.7)');
            ctx.fillRect(x, legendY, patchSize, patchSize);
            ctx.fillStyle = textColor;
            ctx.fillText(z.label, x + 14, legendY + 9);
        });
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.save();
        ctx.translate(topologyState.offsetX, topologyState.offsetY);
        ctx.scale(topologyState.scale, topologyState.scale);

        topologyState.nodes.filter(n => n.type === 'ap').forEach(ap => drawCoverageZones(ctx, topologyState.scale, ap.x, ap.y));

        topologyState.nodes.forEach(node => {
            if (node.type === 'client') {
                const ap = topologyState.nodes.find(n => n.type === 'ap' && n.ip === node.originalData.apIP);
                if (ap) {
                    ctx.beginPath(); ctx.moveTo(node.x, node.y); ctx.lineTo(ap.x, ap.y);
                    ctx.strokeStyle = lineColor; ctx.setLineDash([5 / topologyState.scale, 5 / topologyState.scale]);
                    ctx.lineWidth = 0.5 / topologyState.scale; ctx.stroke(); ctx.setLineDash([]);
                }
            }
        });

        topologyState.nodes.forEach(node => {
            ctx.beginPath();
            if (node.type === 'ap') {
                ctx.arc(node.x, node.y, 10, 0, Math.PI * 2);
                ctx.fillStyle = isLight ? '#ff5c05' : '#00B8D4';
            } else {
                ctx.arc(node.x, node.y, 5, 0, Math.PI * 2);
                ctx.fillStyle = typeof getSnrColor === 'function' ? getSnrColor(node.snr) : '#3fb950';
            }
            ctx.fill();
            ctx.fillStyle = textColor;
            ctx.font = `${node.type === 'ap' ? 'bold ' : ''}${11 / topologyState.scale}px Inter`;
            ctx.fillText(node.name, node.x + 14 / topologyState.scale, node.y + 4 / topologyState.scale);
        });
        ctx.restore();
        drawLegend();
    }

    canvas.onwheel = (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newScale = Math.max(0.1, Math.min(5, topologyState.scale + delta));
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        topologyState.offsetX -= (mouseX - topologyState.offsetX) * (newScale / topologyState.scale - 1);
        topologyState.offsetY -= (mouseY - topologyState.offsetY) * (newScale / topologyState.scale - 1);
        topologyState.scale = newScale;
        draw();
    };

    canvas.onmousedown = (e) => {
        topologyState.isDragging = true;
        topologyState.lastMouseX = e.clientX;
        topologyState.lastMouseY = e.clientY;
        canvas.style.cursor = 'grabbing';
    };

    canvas.onmousemove = (e) => {
        if (!topologyState.isDragging) return;
        topologyState.offsetX += (e.clientX - topologyState.lastMouseX);
        topologyState.offsetY += (e.clientY - topologyState.lastMouseY);
        topologyState.lastMouseX = e.clientX;
        topologyState.lastMouseY = e.clientY;
        draw();
    };

    canvas.onmouseup = () => {
        topologyState.isDragging = false;
        canvas.style.cursor = 'grab';
    };
    canvas.onmouseleave = () => {
        topologyState.isDragging = false;
        canvas.style.cursor = 'grab';
    };

    canvas.onclick = (e) => {
        if (Math.abs(e.clientX - topologyState.lastMouseX) > 5 || Math.abs(e.clientY - topologyState.lastMouseY) > 5) return;
        const rect = canvas.getBoundingClientRect();
        const mouseX = (e.clientX - rect.left - topologyState.offsetX) / topologyState.scale;
        const mouseY = (e.clientY - rect.top - topologyState.offsetY) / topologyState.scale;
        const clickedNode = topologyState.nodes.find(n => Math.sqrt((n.x - mouseX) ** 2 + (n.y - mouseY) ** 2) < 15 / topologyState.scale);
        if (clickedNode) {
            const data = clickedNode.originalData;
            const title = clickedNode.type === 'ap' ? `Access Point: ${data.name}` : `Cliente: ${clickedNode.name}`;
            let detailsHtml = '';
            if (clickedNode.type === 'ap') {
                for (const [key, val] of Object.entries(data.metrics || {})) {
                    detailsHtml += `<div class="detail-row"><span class="detail-label">${key}:</span><span class="detail-value">${val.value}</span></div>`;
                }
            } else {
                for (const [key, val] of Object.entries(data)) {
                    if (typeof val !== 'object') {
                        detailsHtml += `<div class="detail-row"><span class="detail-label">${key}:</span><span class="detail-value">${val}</span></div>`;
                    }
                }
            }
            showModal(title, `
                <div class="modal-details" style="max-height: 400px; overflow-y: auto;">
                    <div class="detail-row"><span class="detail-label">IP:</span><span class="detail-value">${data.ip || 'N/A'}</span></div>
                    <div class="detail-row"><span class="detail-label">MAC:</span><span class="detail-value">${data.mac || 'N/A'}</span></div>
                    <hr style="border: 0; border-top: 1px solid var(--line-color); margin: 10px 0;"/>
                    ${detailsHtml}
                </div>
            `);
        }
    };

    processTopologyData();
    draw();
}

function updateTopClients(selectedApIps) {
    const listContainer = document.getElementById('top-clients-list');
    const summaryContainer = document.getElementById('ap-summary-dashboard');
    if (!listContainer) return;

    // Register search/limit listeners once
    const searchInput = document.getElementById('top-clients-search');
    const limitSelect = document.getElementById('top-clients-limit');

    if (searchInput && !searchInput.dataset.listener) {
        searchInput.addEventListener('input', () => {
            const apSelector = document.getElementById('ap-selector');
            let selApIps = [];
            if (apSelector) {
                selApIps = Array.from(apSelector.selectedOptions).map(opt => opt.value);
            }
            updateTopClients(selApIps);
        });
        searchInput.dataset.listener = 'true';
    }

    if (limitSelect && !limitSelect.dataset.listener) {
        limitSelect.addEventListener('change', () => {
            const apSelector = document.getElementById('ap-selector');
            let selApIps = [];
            if (apSelector) {
                selApIps = Array.from(apSelector.selectedOptions).map(opt => opt.value);
            }
            updateTopClients(selApIps);
        });
        limitSelect.dataset.listener = 'true';
    }

    if (!window.appData || !window.appData.clients || !window.appData.aps) {
        listContainer.innerHTML = `
            <div class="empty-sidebar-state">
                <div class="icon">📡</div>
                <p>Cargando datos de clientes...</p>
            </div>
        `;
        return;
    }

    // Filter clients based on selected APs
    let filteredClients = window.appData.clients;
    if (selectedApIps && selectedApIps.length > 0 && !selectedApIps.includes('ALL')) {
        filteredClients = window.appData.clients.filter(c => selectedApIps.includes(c.apIP));
    }

    // Filter by search query if any
    const searchQuery = searchInput ? searchInput.value.toLowerCase() : '';
    if (searchQuery) {
        filteredClients = filteredClients.filter(c => 
            (c.name || '').toLowerCase().includes(searchQuery) || 
            (c.ip || '').includes(searchQuery) ||
            (c.mac || '').toLowerCase().includes(searchQuery)
        );
    }

    // Filter APs based on selection
    let selectedAps = window.appData.aps;
    if (selectedApIps && selectedApIps.length > 0 && !selectedApIps.includes('ALL')) {
        selectedAps = window.appData.aps.filter(ap => selectedApIps.includes(ap.ip));
    }

    // Calculate totals for summary
    const totalApThroughput = filteredClients.reduce((sum, c) => sum + (c.txThroughput || 0) + (c.rxThroughput || 0), 0);
    
    let radio1Total = 0;
    let radio2Total = 0;
    selectedAps.forEach(ap => {
        const r1Val = ap.metrics && ap.metrics['AP Radio 1 Client Num'] ? parseInt(ap.metrics['AP Radio 1 Client Num'].value) : 0;
        const r2Val = ap.metrics && ap.metrics['AP Radio 2 Client Num'] ? parseInt(ap.metrics['AP Radio 2 Client Num'].value) : 0;
        radio1Total += isNaN(r1Val) ? 0 : r1Val;
        radio2Total += isNaN(r2Val) ? 0 : r2Val;
    });
    const totalRadioClients = radio1Total + radio2Total;

    // Populate AP Summary Dashboard
    if (summaryContainer) {
        let apDetailsHtml = '';
        selectedAps.forEach(ap => {
            const apClients = window.appData.clients.filter(c => c.apIP === ap.ip);
            const apBw = apClients.reduce((sum, c) => sum + (c.txThroughput || 0) + (c.rxThroughput || 0), 0);
            
            const r1 = ap.metrics && ap.metrics['AP Radio 1 Client Num'] ? parseInt(ap.metrics['AP Radio 1 Client Num'].value) : 0;
            const r2 = ap.metrics && ap.metrics['AP Radio 2 Client Num'] ? parseInt(ap.metrics['AP Radio 2 Client Num'].value) : 0;
            
            apDetailsHtml += `
                <div class="ap-radio-detail-item">
                    <div class="ap-detail-name" title="${ap.name}">${ap.name}</div>
                    <div class="ap-detail-bw">${formatThroughput(apBw)}</div>
                    <div class="ap-detail-radios">
                        <span>R1: ${isNaN(r1) ? 0 : r1}</span>
                        <span>R2: ${isNaN(r2) ? 0 : r2}</span>
                    </div>
                </div>
            `;
        });

        const formattedBw = formatThroughput(totalApThroughput);
        summaryContainer.innerHTML = `
            <div class="ap-summary-title">Resumen de Selección</div>
            <div class="summary-stats-grid">
                <div class="summary-stat-card full-width">
                    <span class="summary-stat-label">Ancho de Banda Total</span>
                    <span class="summary-stat-value large">${formattedBw}</span>
                </div>
                <div class="summary-stat-card">
                    <span class="summary-stat-label">Clientes Totales</span>
                    <span class="summary-stat-value">${filteredClients.length}</span>
                </div>
                <div class="summary-stat-card">
                    <span class="summary-stat-label">Equipos en Radios</span>
                    <span class="summary-stat-value">${totalRadioClients}</span>
                </div>
            </div>
            
            <div class="radio-breakdown">
                <div class="radio-badge">
                    <span class="radio-badge-title">Radio 1 (5 GHz)</span>
                    <span class="radio-badge-value">${radio1Total}</span>
                </div>
                <div class="radio-badge">
                    <span class="radio-badge-title">Radio 2 (2.4 GHz)</span>
                    <span class="radio-badge-value">${radio2Total}</span>
                </div>
            </div>

            <div class="ap-summary-title">Clientes por Radio y AP</div>
            <div class="ap-radio-detail-list">
                ${apDetailsHtml || '<div style="padding:0.5rem;text-align:center;font-size:0.75rem;color:var(--text-secondary)">Ningún AP seleccionado</div>'}
            </div>
        `;
    }

    // Sort clients by bandwidth consumption (txThroughput + rxThroughput) descending
    filteredClients.sort((a, b) => {
        const usageA = (a.txThroughput || 0) + (a.rxThroughput || 0);
        const usageB = (b.txThroughput || 0) + (b.rxThroughput || 0);
        return usageB - usageA;
    });

    // Take top clients based on selected limit
    const limitVal = limitSelect ? limitSelect.value : '15';
    const limit = limitVal === 'ALL' ? filteredClients.length : parseInt(limitVal, 10);
    const topClients = filteredClients.slice(0, limit);

    if (topClients.length === 0) {
        listContainer.innerHTML = `
            <div class="empty-sidebar-state">
                <div class="icon">📡</div>
                <p>No hay clientes conectados a los APs seleccionados.</p>
            </div>
        `;
        return;
    }

    // Find the max consumption to set progress bar percentages
    const maxUsage = topClients.reduce((max, c) => {
        const usage = (c.txThroughput || 0) + (c.rxThroughput || 0);
        return Math.max(max, usage);
    }, 0) || 1;

    listContainer.innerHTML = '';
    topClients.forEach((client, index) => {
        const totalThroughput = (client.txThroughput || 0) + (client.rxThroughput || 0);
        const pct = Math.min(100, (totalThroughput / maxUsage) * 100);
        
        // Find AP name for this client
        const ap = window.appData.aps.find(a => a.ip === client.apIP);
        const apName = ap ? ap.name : (client.apIP || 'AP Desconocido');

        // Create card element
        const card = document.createElement('div');
        card.className = 'client-rank-card';
        card.style.animationDelay = `${index * 40}ms`;

        // Format throughput
        const formattedTotal = formatThroughput(totalThroughput);
        const formattedTx = formatThroughput(client.txThroughput || 0);
        const formattedRx = formatThroughput(client.rxThroughput || 0);
        
        // Format total bytes as well
        const totalBytes = (client.txBytes || 0) + (client.rxBytes || 0);
        const formattedBytes = formatBytes(totalBytes);

        card.innerHTML = `
            <div class="rank-badge">${index + 1}</div>
            <div class="client-info">
                <div class="client-name-row">
                    <span class="client-name" title="${client.name || 'Desconocido'}">${client.name || 'Desconocido'}</span>
                    <span class="client-bw-val">${formattedTotal}</span>
                </div>
                <div class="client-meta-row">
                    <span class="client-ip">${client.ip || 'N/A'}</span>
                    <span class="client-ap" title="Access Point: ${apName}">📍 ${apName}</span>
                </div>
                <div class="bw-bar-container">
                    <div class="bw-bar" style="width: ${pct}%"></div>
                </div>
                <div class="bw-details-row">
                    <span>Subida (Tx): ${formattedTx}</span>
                    <span>Bajada (Rx): ${formattedRx}</span>
                </div>
                <div class="bytes-row">
                    <span>Total: ${formattedBytes}</span>
                    <span>SNR: ${client.snr || 0} dB</span>
                </div>
            </div>
        `;

        // Click listener to show details modal
        card.onclick = () => {
            if (typeof showModal === 'function') {
                const title = `📱 Cliente: ${client.name || 'Desconocido'}`;
                let html = '<div class="modal-details" style="max-height:400px;overflow-y:auto;">';
                html += `<div class="detail-row"><span class="detail-label">IP:</span><span class="detail-value">${client.ip || 'N/A'}</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">MAC:</span><span class="detail-value">${client.mac || 'N/A'}</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">Access Point IP:</span><span class="detail-value">${client.apIP || 'N/A'}</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">Access Point Name:</span><span class="detail-value">${apName}</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">SNR:</span><span class="detail-value">${client.snr || 0} dB</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">Ancho Banda Actual (Tx/Rx):</span><span class="detail-value">${formattedTotal} (${formattedTx} Tx / ${formattedRx} Rx)</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">Total Datos (Tx/Rx):</span><span class="detail-value">${formattedBytes} (${formatBytes(client.txBytes || 0)} Tx / ${formatBytes(client.rxBytes || 0)} Rx)</span></div>`;
                html += '</div>';
                showModal(title, html);
            }
        };

        listContainer.appendChild(card);
    });
}

function formatThroughput(bps) {
    if (bps <= 0) return '0 bps';
    const k = 1000;
    const m = k * k;
    const g = m * k;
    
    if (bps >= g) {
        return (bps / g).toFixed(2) + ' Gbps';
    } else if (bps >= m) {
        return (bps / m).toFixed(2) + ' Mbps';
    } else if (bps >= k) {
        return (bps / k).toFixed(2) + ' Kbps';
    } else {
        return bps.toFixed(0) + ' bps';
    }
}

function formatBytes(bytes) {
    if (bytes <= 0) return '0 B';
    const k = 1024;
    const m = k * k;
    const g = m * k;
    const t = g * k;
    
    if (bytes >= t) {
        return (bytes / t).toFixed(2) + ' TB';
    } else if (bytes >= g) {
        return (bytes / g).toFixed(2) + ' GB';
    } else if (bytes >= m) {
        return (bytes / m).toFixed(2) + ' MB';
    } else if (bytes >= k) {
        return (bytes / k).toFixed(2) + ' KB';
    } else {
        return bytes.toFixed(0) + ' B';
    }
}
