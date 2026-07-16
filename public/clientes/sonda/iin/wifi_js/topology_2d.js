let topo2dState = {
    floorplans: [],
    currentFloor: null,
    scale: 1.0,
    offsetX: 0,
    offsetY: 0,
    bgImage: null,
    isDragging: false,
    lastMouseX: 0,
    lastMouseY: 0,
    nodes: [], // AP & client nodes for click detection
    allApPositions: []
};

async function initTopology2D() {
    const canvas = document.getElementById('topo-2d-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const floorSelect = document.getElementById('topo-2d-floor-select');

    // Resize canvas to fill container
    function resizeCanvas() {
        canvas.width = canvas.parentElement.clientWidth;
        canvas.height = canvas.parentElement.clientHeight;
        draw2D();
    }

    // Load floor data from API
    async function load2DData() {
        try {
            const fpResp = await fetch('wifi_php/api.php?action=get_floorplans');
            const fpData = await fpResp.json();
            if (fpData.success) {
                topo2dState.floorplans = fpData.floorplans;
                populateFloorSelect();
            }

            // Fetch legacy AP positions as fallback
            const posResp = await fetch('wifi_php/api.php?action=get_ap_positions');
            const posData = await posResp.json();
            if (posData.success) {
                topo2dState.allApPositions = posData.positions;
            }

            if (topo2dState.currentFloor) draw2D();
        } catch (e) {
            console.error("Error loading 2D data:", e);
        }
    }

    function populateFloorSelect() {
        const currentVal = floorSelect.value;
        floorSelect.innerHTML = '<option value="">Seleccione un Piso...</option>';
        topo2dState.floorplans.forEach(fp => {
            const opt = document.createElement('option');
            opt.value = fp.id;
            opt.textContent = fp.name;
            floorSelect.appendChild(opt);
        });
        floorSelect.value = currentVal;
    }

    // When a floor is selected: load its image + parse model_json for AP positions
    floorSelect.onchange = (e) => {
        const fp = topo2dState.floorplans.find(f => f.id === e.target.value);
        if (!fp) {
            topo2dState.bgImage = null;
            topo2dState.currentFloor = null;
            draw2D();
            return;
        }

        topo2dState.currentFloor = fp;

        // Load background image if available (check for null, empty, and "null" string)
        if (fp.image_url && fp.image_url !== 'null') {
            const img = new Image();
            img.src = fp.image_url;
            img.onload = () => {
                topo2dState.bgImage = img;
                centerOnImage();
                draw2D();
            };
            img.onerror = () => {
                topo2dState.bgImage = null;
                centerDefault();
                draw2D();
            };
        } else {
            topo2dState.bgImage = null;
            centerDefault();
            draw2D();
        }
    };

    function centerOnImage() {
        if (!topo2dState.bgImage) return centerDefault();
        const img = topo2dState.bgImage;
        const scaleX = (canvas.width * 0.9) / img.width;
        const scaleY = (canvas.height * 0.9) / img.height;
        topo2dState.scale = Math.min(scaleX, scaleY);
        topo2dState.offsetX = (canvas.width - img.width * topo2dState.scale) / 2;
        topo2dState.offsetY = (canvas.height - img.height * topo2dState.scale) / 2;
    }

    function centerDefault() {
        // Center on AP positions if available
        const fp = topo2dState.currentFloor;
        if (fp) {
            const aps = getAPsFromFloor(fp);
            if (aps.length > 0) {
                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                aps.forEach(ap => {
                    minX = Math.min(minX, ap.x);
                    minY = Math.min(minY, ap.y);
                    maxX = Math.max(maxX, ap.x);
                    maxY = Math.max(maxY, ap.y);
                });
                // Add padding
                const padding = 150;
                minX -= padding; minY -= padding;
                maxX += padding; maxY += padding;
                const w = maxX - minX;
                const h = maxY - minY;
                const scaleX = canvas.width / w;
                const scaleY = canvas.height / h;
                topo2dState.scale = Math.min(scaleX, scaleY);
                topo2dState.offsetX = (canvas.width - w * topo2dState.scale) / 2 - minX * topo2dState.scale;
                topo2dState.offsetY = (canvas.height - h * topo2dState.scale) / 2 - minY * topo2dState.scale;
                return;
            }
        }
        topo2dState.scale = 1;
        topo2dState.offsetX = 50;
        topo2dState.offsetY = 50;
    }

    // Parse AP positions from model_json (Go.js model stored in DB)
    function getAPsFromFloor(fp) {
        const aps = [];
        if (fp.model_json) {
            try {
                const model = JSON.parse(fp.model_json);
                if (model.nodeDataArray) {
                    model.nodeDataArray.forEach(node => {
                        const locParts = (node.loc || "0 0").split(" ");
                        aps.push({
                            key: node.key,
                            name: node.name || node.ip || 'AP',
                            ip: node.ip || '',
                            x: parseFloat(locParts[0]) || 0,
                            y: parseFloat(locParts[1]) || 0
                        });
                    });
                }
            } catch (e) {
                console.error("Error parsing model_json:", e);
            }
        }

        // Fallback: If no model_json, use the legacy positions table
        if (aps.length === 0 && topo2dState.allApPositions) {
            const legacyAps = topo2dState.allApPositions.filter(p => p.floor_id === fp.id);
            legacyAps.forEach(p => {
                aps.push({
                    key: p.id,
                    name: p.name || p.ip || 'AP',
                    ip: p.ip || '',
                    x: parseFloat(p.x) || 0,
                    y: parseFloat(p.y) || 0
                });
            });
        }

        return aps;
    }

    // Main draw function
    function draw2D() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        topo2dState.nodes = [];

        if (!topo2dState.currentFloor) {
            ctx.fillStyle = "#8b949e";
            ctx.textAlign = "center";
            ctx.font = "18px Inter";
            ctx.fillText("Seleccione un piso para visualizar la topología", canvas.width / 2, canvas.height / 2);
            return;
        }

        ctx.save();
        ctx.translate(topo2dState.offsetX, topo2dState.offsetY);
        ctx.scale(topo2dState.scale, topo2dState.scale);

        const fp = topo2dState.currentFloor;

        // 1. Draw background image
        if (topo2dState.bgImage) {
            ctx.drawImage(topo2dState.bgImage, 0, 0);

            // Draw grid overlay
            const imgW = topo2dState.bgImage.width;
            const imgH = topo2dState.bgImage.height;
            const scaleVal = parseFloat(fp.scale) || 0.05;
            const gridMeters = parseFloat(fp.grid_meters) || 5;
            const gridSpacing = gridMeters / scaleVal;

            ctx.beginPath();
            ctx.strokeStyle = 'rgba(173, 216, 230, 0.08)';
            ctx.lineWidth = 0.5 / topo2dState.scale;
            ctx.setLineDash([4, 6]);
            for (let vx = 0; vx <= imgW; vx += gridSpacing) {
                ctx.moveTo(vx, 0); ctx.lineTo(vx, imgH);
            }
            for (let vy = 0; vy <= imgH; vy += gridSpacing) {
                ctx.moveTo(0, vy); ctx.lineTo(imgW, vy);
            }
            ctx.stroke();
            ctx.setLineDash([]);
        }

        // 2. Get AP positions from the stored model_json
        const floorAps = getAPsFromFloor(fp);
        const apSize = parseInt(fp.ap_size) || 12;

        // 3. Draw coverage zones, connections, clients, and APs (like topology.js)
        const liveAPs = window.appData ? window.appData.aps : [];
        const liveClients = window.appData ? window.appData.clients : [];

        floorAps.forEach(apPos => {
            // Try to find live data for this AP (Match by IP first, then name if IP is placeholder)
            let liveAP = liveAPs.find(a => a.ip === apPos.ip);
            if (!liveAP || apPos.ip === '-1' || apPos.ip === '') {
                liveAP = liveAPs.find(a => a.name === apPos.name || a.name?.includes(apPos.name));
            }
            const clients = liveAP ? liveClients.filter(c => c.apIP === liveAP.ip) : [];
            const radius = 15 / topo2dState.scale; // 15px on screen


            // Draw coverage zones (very subtle to avoid masking the floor plan)
            if (typeof ZONES !== 'undefined') {
                [...ZONES].reverse().forEach(z => {
                    ctx.beginPath();
                    // ZONES use meters, we convert to pixels: dist = z.dMax / scaleVal
                    const scaleVal = parseFloat(fp.scale) || 0.05;
                    const radiusPx = z.dMax / scaleVal;
                    ctx.arc(apPos.x, apPos.y, radiusPx, 0, Math.PI * 2);
                    // Make very transparent for the floor view
                    ctx.fillStyle = z.color.replace(/0\.\d+\)/, '0.015)');
                    ctx.fill();
                    ctx.strokeStyle = z.color.replace(/0\.\d+\)/, '0.05)');
                    ctx.lineWidth = 0.5 / topo2dState.scale;
                    ctx.stroke();
                });
            }

            // Draw client connections and nodes
            clients.forEach((client, idx) => {
                const snr = client.snr || 10;
                const preciseDist = typeof mapSnrToPreciseDist === 'function' ? mapSnrToPreciseDist(snr) * 0.4 : 30;
                const angle = (idx / Math.max(clients.length, 1)) * Math.PI * 2;
                const cx = apPos.x + Math.cos(angle) * preciseDist;
                const cy = apPos.y + Math.sin(angle) * preciseDist;

                // Connection line
                ctx.beginPath();
                ctx.moveTo(apPos.x, apPos.y);
                ctx.lineTo(cx, cy);
                ctx.strokeStyle = 'rgba(255,255,255,0.15)';
                ctx.setLineDash([3, 3]);
                ctx.lineWidth = 0.8 / topo2dState.scale;
                ctx.stroke();
                ctx.setLineDash([]);

                // Client dot
                const dotR = 5 / topo2dState.scale;
                ctx.beginPath();
                ctx.arc(cx, cy, dotR, 0, Math.PI * 2);
                ctx.fillStyle = typeof getSnrColor === 'function' ? getSnrColor(snr) : '#3fb950';
                ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,0.8)';
                ctx.lineWidth = 1 / topo2dState.scale;
                ctx.stroke();

                // Client label
                ctx.fillStyle = '#f0f6fc';
                ctx.font = `${10 / topo2dState.scale}px Inter`;
                ctx.textAlign = 'left';
                ctx.fillText(client.name || client.ip || '', cx + 8 / topo2dState.scale, cy + 4 / topo2dState.scale);


                // Store for click detection
                topo2dState.nodes.push({
                    type: 'client', x: cx, y: cy, name: client.name || 'Desconocido',
                    ip: client.ip || 'N/A', snr: snr, originalData: client
                });
            });

            // Draw AP icon
            // Glow
            const glowR = radius * 1.8;
            const gradient = ctx.createRadialGradient(apPos.x, apPos.y, 0, apPos.x, apPos.y, glowR);
            gradient.addColorStop(0, 'rgba(88, 166, 255, 0.4)');
            gradient.addColorStop(1, 'rgba(88, 166, 255, 0)');
            ctx.beginPath();
            ctx.arc(apPos.x, apPos.y, glowR, 0, Math.PI * 2);
            ctx.fillStyle = gradient;
            ctx.fill();

            // Main circle
            ctx.beginPath();
            ctx.arc(apPos.x, apPos.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = '#58a6ff';
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2.5 / topo2dState.scale;
            ctx.stroke();

            // Client count badge
            if (clients.length > 0) {
                const badgeR = 9 / topo2dState.scale;
                ctx.beginPath();
                ctx.arc(apPos.x + radius, apPos.y - radius, badgeR, 0, Math.PI * 2);
                ctx.fillStyle = '#f85149';
                ctx.fill();
                ctx.fillStyle = '#fff';
                ctx.font = `bold ${8 / topo2dState.scale}px Inter`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(clients.length.toString(), apPos.x + radius, apPos.y - radius);
                ctx.textBaseline = 'alphabetic';
            }

            // AP name label
            ctx.fillStyle = liveAP ? "#fff" : "#ff4444";
            ctx.font = `bold ${12 / topo2dState.scale}px Inter`;
            ctx.textAlign = "center";

            // Label text: Use live name if available, else saved name
            const labelText = (liveAP ? liveAP.name : apPos.name) || "AP";
            const textMetrics = ctx.measureText(labelText);
            const textWidth = textMetrics.width;
            const labelHeight = 18 / topo2dState.scale;
            const labelPadding = 6 / topo2dState.scale;

            ctx.fillStyle = 'rgba(13, 17, 23, 0.9)';
            ctx.fillRect(
                apPos.x - textWidth / 2 - labelPadding,
                apPos.y - radius - labelHeight - labelPadding,
                textWidth + labelPadding * 2,
                labelHeight
            );

            ctx.fillStyle = liveAP ? "#e6edf3" : "#ffaaaa";
            ctx.fillText(labelText, apPos.x, apPos.y - radius - labelPadding - 4 / topo2dState.scale);

            // Store for click detection
            topo2dState.nodes.push({
                type: 'ap', x: apPos.x, y: apPos.y, name: labelText, ip: apPos.ip,
                originalData: liveAP || { name: labelText, ip: apPos.ip, metrics: {} },
                clientCount: clients.length,
                isOnline: !!liveAP
            });
        });

        ctx.restore();
        drawLegend();
    }

    function drawLegend() {
        const legend = document.getElementById('topo-2d-legend');
        if (!legend) return;
        legend.innerHTML = '';

        if (typeof ZONES !== 'undefined') {
            ZONES.forEach(z => {
                const item = document.createElement('div');
                item.style.cssText = 'display:flex;align-items:center;gap:5px;margin:2px 0;';
                item.innerHTML = `<div style="width:10px;height:10px;border-radius:2px;background:${z.color.replace(/0\.\d+\)/, '0.7)')}"></div>
                    <span style="font-size:10px;color:var(--text-secondary)">${z.label}</span>`;
                legend.appendChild(item);
            });
        }

        // Show AP count info
        if (topo2dState.currentFloor) {
            const floorAps = getAPsFromFloor(topo2dState.currentFloor);
            const info = document.createElement('div');
            info.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid var(--line-color);font-size:11px;color:var(--text-primary);';
            info.innerHTML = `<strong>${floorAps.length}</strong> APs en este piso`;
            legend.appendChild(info);
        }
    }

    // Click handler: show AP/Client details in modal
    canvas.onclick = (e) => {
        const rect = canvas.getBoundingClientRect();
        const mouseX = (e.clientX - rect.left - topo2dState.offsetX) / topo2dState.scale;
        const mouseY = (e.clientY - rect.top - topo2dState.offsetY) / topo2dState.scale;

        const hitRadius = 15 / topo2dState.scale;
        const clickedNode = topo2dState.nodes.find(n =>
            Math.sqrt((n.x - mouseX) ** 2 + (n.y - mouseY) ** 2) < hitRadius
        );

        if (clickedNode && typeof showModal === 'function') {
            const data = clickedNode.originalData;
            const title = clickedNode.type === 'ap'
                ? `📡 Access Point: ${clickedNode.name}`
                : `📱 Cliente: ${clickedNode.name}`;

            let html = '<div class="modal-details" style="max-height:400px;overflow-y:auto;">';
            html += `<div class="detail-row"><span class="detail-label">IP:</span><span class="detail-value">${clickedNode.ip || 'N/A'}</span></div>`;

            if (clickedNode.type === 'ap') {
                html += `<div class="detail-row"><span class="detail-label">MAC:</span><span class="detail-value">${data.mac || 'N/A'}</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">Clientes:</span><span class="detail-value">${clickedNode.clientCount || 0}</span></div>`;
                html += '<hr style="border:0;border-top:1px solid var(--line-color);margin:10px 0;"/>';

                if (data.metrics) {
                    for (const [key, val] of Object.entries(data.metrics)) {
                        html += `<div class="detail-row"><span class="detail-label">${key}:</span><span class="detail-value">${val.value}</span></div>`;
                    }
                }
            } else {
                html += `<div class="detail-row"><span class="detail-label">MAC:</span><span class="detail-value">${data.mac || 'N/A'}</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">SNR:</span><span class="detail-value">${clickedNode.snr || 'N/A'} dB</span></div>`;
                html += `<div class="detail-row"><span class="detail-label">AP:</span><span class="detail-value">${data.apIP || 'N/A'}</span></div>`;
            }

            html += '</div>';
            showModal(title, html);
        }
    };

    // Zoom with mouse wheel
    canvas.onwheel = (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.05 : 0.05;
        const newScale = Math.max(0.05, Math.min(5, topo2dState.scale + delta));
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        topo2dState.offsetX -= (mouseX - topo2dState.offsetX) * (newScale / topo2dState.scale - 1);
        topo2dState.offsetY -= (mouseY - topo2dState.offsetY) * (newScale / topo2dState.scale - 1);
        topo2dState.scale = newScale;
        draw2D();
    };

    // Pan with mouse drag
    canvas.onmousedown = (e) => {
        topo2dState.isDragging = true;
        topo2dState.lastMouseX = e.clientX;
        topo2dState.lastMouseY = e.clientY;
        canvas.style.cursor = 'grabbing';
    };
    canvas.onmousemove = (e) => {
        if (!topo2dState.isDragging) return;
        topo2dState.offsetX += (e.clientX - topo2dState.lastMouseX);
        topo2dState.offsetY += (e.clientY - topo2dState.lastMouseY);
        topo2dState.lastMouseX = e.clientX;
        topo2dState.lastMouseY = e.clientY;
        draw2D();
    };
    canvas.onmouseup = () => { topo2dState.isDragging = false; canvas.style.cursor = 'grab'; };
    canvas.onmouseleave = () => { topo2dState.isDragging = false; canvas.style.cursor = 'grab'; };

    // Auto-refresh data every 30s
    if (window.topo2dInterval) clearInterval(window.topo2dInterval);
    window.topo2dInterval = setInterval(() => {
        load2DData();
    }, 30000);

    window.addEventListener('resize', resizeCanvas);
    await load2DData();
    resizeCanvas();
}
