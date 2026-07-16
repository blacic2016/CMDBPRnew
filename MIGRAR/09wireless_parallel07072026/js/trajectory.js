let trajState = {
    scale: 0.8,
    offsetX: 0,
    offsetY: 0,
    isDragging: false,
    lastMouseX: 0,
    lastMouseY: 0,
    pathPoints: []
};

function initTrajectory() {
    const canvas = document.getElementById('trajectory-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const searchBtn = document.getElementById('search-trajectory');
    const clientSel = document.getElementById('client-selector');
    const startTimeInput = document.getElementById('traj-start-time');
    const durationSel = document.getElementById('traj-duration');

    // Default start time: 8 hours ago
    const now = new Date();
    now.setHours(now.getHours() - 8);
    startTimeInput.value = now.toISOString().slice(0, 16);

    const updateCanvasSize = () => {
        if (!canvas.parentElement) return;
        canvas.width = canvas.parentElement.clientWidth;
        canvas.height = canvas.parentElement.clientHeight;
        if (trajState.pathPoints.length > 0) draw();
    };

    updateCanvasSize();
    window.addEventListener('resize', updateCanvasSize);

    searchBtn.onclick = async () => {
        const clientId = clientSel.value;
        if (!clientId) {
            alert("Por favor seleccione un cliente");
            return;
        }

        const startTs = Math.floor(new Date(startTimeInput.value).getTime() / 1000);
        const duration = parseInt(durationSel.value);

        searchBtn.disabled = true;
        searchBtn.textContent = "Buscando...";

        try {
            const response = await fetch(`php/api.php?action=trajectory&clientId=${clientId}&startTime=${startTs}&duration=${duration}`);
            const result = await response.json();
            if (result.success) {
                processTrajectoryData(result);
                draw();
            }
        } catch (e) {
            console.error("Error fetching trajectory:", e);
        } finally {
            searchBtn.disabled = false;
            searchBtn.textContent = "Analizar Trayectoria";
        }
    };

    const lastLocBtn = document.getElementById('last-location');
    if (lastLocBtn) {
        lastLocBtn.onclick = () => {
            const clientId = clientSel.value;
            if (!clientId) {
                alert("Por favor seleccione un cliente");
                return;
            }

            const client = appData.clients.find(c => (c.mac || c.key) === clientId);
            if (!client || !client.apIP) {
                alert("No se encontró ubicación actual (el equipo podría estar offline)");
                return;
            }

            const mockResult = {
                success: true,
                apHistory: [{ clock: Math.floor(Date.now() / 1000), value: client.apIP }],
                snrHistory: [{ clock: Math.floor(Date.now() / 1000), value: client.snr || 10 }],
                originalData: client
            };
            processTrajectoryData(mockResult);
            draw();
        };
    }

    function processTrajectoryData(data) {
        trajState.pathPoints = [];
        const { apHistory, snrHistory } = data;
        const clientId = data.clientId || (data.originalData ? (data.originalData.mac || data.originalData.key) : null);

        snrHistory.forEach(snrPoint => {
            const clock = parseInt(snrPoint.clock);
            const snr = parseInt(snrPoint.value);

            let activeApIp = null;
            for (let i = apHistory.length - 1; i >= 0; i--) {
                if (parseInt(apHistory[i].clock) <= clock) {
                    activeApIp = apHistory[i].value;
                    break;
                }
            }

            if (activeApIp) {
                const ap = appData.aps.find(a => a.ip === activeApIp);
                if (ap) {
                    trajState.pathPoints.push({
                        clock,
                        snr,
                        apIp: activeApIp,
                        apName: ap.name,
                        originalData: data.originalData || appData.clients.find(c => (c.mac || c.key) === clientId)
                    });
                }
            }
        });

        // Populate Sidebar with Latest Data
        const sidebar = document.getElementById('trajectory-details-sidebar');
        const client = appData.clients.find(c => (c.mac || c.key) === clientId) || data.originalData;
        if (sidebar && client) {
            let html = `<h4>Última Data Obtenida</h4>`;
            for (const [key, val] of Object.entries(client)) {
                if (typeof val !== 'object' && val !== null) {
                    const label = key.replace(/([A-Z])/g, ' $1').trim();
                    html += `<div class="detail-row"><span class="detail-label">${label}:</span><span class="detail-value">${val}</span></div>`;
                }
            }
            sidebar.innerHTML = html;
        }

        // Ensure we center properly
        trajState.scale = 1.0;
        trajState.offsetX = canvas.width / 2;
        trajState.offsetY = canvas.height / 2;
    }


    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const style = getComputedStyle(document.body);
        const textColor = style.getPropertyValue('--text-primary').trim();
        const lineColor = style.getPropertyValue('--line-color').trim();

        ctx.save();
        ctx.translate(trajState.offsetX, trajState.offsetY);
        ctx.scale(trajState.scale, trajState.scale);

        const usedApIps = [...new Set(trajState.pathPoints.map(p => p.apIp))];
        const apPositions = {};

        usedApIps.forEach((ip, idx) => {
            const ap = appData.aps.find(a => a.ip === ip);
            const angle = (idx / usedApIps.length) * Math.PI * 2;
            const radius = usedApIps.length === 1 ? 0 : 250;
            const x = Math.cos(angle) * radius;
            const y = Math.sin(angle) * radius;
            apPositions[ip] = { x, y, name: ap ? ap.name : ip };

            if (typeof drawCoverageZones === 'function') drawCoverageZones(ctx, trajState.scale, x, y);

            ctx.beginPath();
            ctx.arc(x, y, 12, 0, Math.PI * 2);
            ctx.fillStyle = '#58a6ff';
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2 / trajState.scale;
            ctx.stroke();

            ctx.fillStyle = textColor;
            ctx.font = 'bold 12px Inter';
            ctx.textAlign = 'center';
            ctx.fillText(apPositions[ip].name, x, y - 25);
        });

        if (trajState.pathPoints.length > 1) {
            ctx.beginPath();
            ctx.setLineDash([5 / trajState.scale, 5 / trajState.scale]);
            ctx.strokeStyle = lineColor;
            ctx.lineWidth = 1.5 / trajState.scale;
            trajState.pathPoints.forEach((p, i) => {
                const apPos = apPositions[p.apIp];
                const dist = typeof mapSnrToPreciseDist === 'function' ? mapSnrToPreciseDist(p.snr) : 100;
                const angle = (p.clock % 3600) / 3600 * Math.PI * 2;
                const px = apPos.x + Math.cos(angle) * dist;
                const py = apPos.y + Math.sin(angle) * dist;
                p.px = px; p.py = py;
                if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
            });
            ctx.stroke();
            ctx.setLineDash([]);
        } else if (trajState.pathPoints.length === 1) {
            const p = trajState.pathPoints[0];
            const apPos = apPositions[p.apIp];
            const dist = typeof mapSnrToPreciseDist === 'function' ? mapSnrToPreciseDist(p.snr) : 100;
            const px = apPos.x; // Central for single point if radius is 0
            const py = apPos.y - dist; // Offset by SNR distance
            p.px = px; p.py = py;
        }

        trajState.pathPoints.forEach((p, i) => {
            ctx.beginPath();
            ctx.arc(p.px, p.py, 5, 0, Math.PI * 2);
            ctx.fillStyle = typeof getSnrColor === 'function' ? getSnrColor(p.snr) : '#3fb950';
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 1 / trajState.scale;
            ctx.stroke();

            const isHandover = i > 0 && trajState.pathPoints[i - 1].apIp !== p.apIp;
            if (i === 0 || i === trajState.pathPoints.length - 1 || isHandover) {
                ctx.fillStyle = textColor;
                ctx.font = '10px Inter';
                const date = new Date(p.clock * 1000);
                const timeStr = `${date.getHours()}:${date.getMinutes().toString().padStart(2, '0')}`;
                ctx.fillText(timeStr, p.px, p.py + 15);
                ctx.fillText(`SNR: ${p.snr}`, p.px, p.py + 26);
            }
        });

        ctx.restore();
        drawTrajectoryLegend();
    }

    function drawTrajectoryLegend() {
        const legend = document.getElementById('trajectory-legend');
        if (!legend) return;
        legend.innerHTML = '';
        if (typeof ZONES !== 'undefined') {
            ZONES.forEach(z => {
                const item = document.createElement('div');
                item.style.display = 'flex'; item.style.alignItems = 'center'; item.style.gap = '5px';
                item.innerHTML = `<div style="width:12px; height:12px; border-radius:3px; background:${z.color.replace('0.2', '0.8')}"></div><span style="font-size:10px; color:var(--text-primary)">${z.label}</span>`;
                legend.appendChild(item);
            });
        }
    }

    canvas.onwheel = (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newScale = Math.max(0.1, Math.min(5, trajState.scale + delta));
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        trajState.offsetX -= (mouseX - trajState.offsetX) * (newScale / trajState.scale - 1);
        trajState.offsetY -= (mouseY - trajState.offsetY) * (newScale / trajState.scale - 1);
        trajState.scale = newScale;
        draw();
    };

    canvas.onmousedown = (e) => {
        trajState.isDragging = true;
        trajState.lastMouseX = e.clientX;
        trajState.lastMouseY = e.clientY;
        canvas.style.cursor = 'grabbing';
    };

    canvas.onmousemove = (e) => {
        if (!trajState.isDragging) return;
        trajState.offsetX += (e.clientX - trajState.lastMouseX);
        trajState.offsetY += (e.clientY - trajState.lastMouseY);
        trajState.lastMouseX = e.clientX;
        trajState.lastMouseY = e.clientY;
        draw();
    };

    canvas.onmouseup = () => {
        trajState.isDragging = false;
        canvas.style.cursor = 'grab';
    };
    canvas.onmouseleave = () => {
        trajState.isDragging = false;
        canvas.style.cursor = 'grab';
    };

    canvas.onclick = (e) => {
        if (Math.abs(e.clientX - trajState.lastMouseX) > 5 || Math.abs(e.clientY - trajState.lastMouseY) > 5) return;
        const rect = canvas.getBoundingClientRect();
        const mouseX = (e.clientX - rect.left - trajState.offsetX) / trajState.scale;
        const mouseY = (e.clientY - rect.top - trajState.offsetY) / trajState.scale;

        const clickedPoint = trajState.pathPoints.find(p => Math.sqrt((p.px - mouseX) ** 2 + (p.py - mouseY) ** 2) < 15 / trajState.scale);
        if (clickedPoint) {
            const data = clickedPoint.originalData;
            const title = `Detalles del Equipo: ${data.name || 'Desconocido'}`;
            let detailsHtml = '';
            for (const [key, val] of Object.entries(data)) {
                if (typeof val !== 'object') {
                    detailsHtml += `<div class="detail-row"><span class="detail-label">${key}:</span><span class="detail-value">${val}</span></div>`;
                }
            }
            showModal(title, `
                <div class="modal-details" style="max-height: 400px; overflow-y: auto;">
                    <div class="detail-row"><span class="detail-label">Captura:</span><span class="detail-value">${new Date(clickedPoint.clock * 1000).toLocaleString()}</span></div>
                    <div class="detail-row"><span class="detail-label">SNR Histórico:</span><span class="detail-value">${clickedPoint.snr}</span></div>
                    <hr style="border: 0; border-top: 1px solid var(--line-color); margin: 10px 0;"/>
                    ${detailsHtml}
                </div>
            `);
        }
    };
}
