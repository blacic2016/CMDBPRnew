/**
 * CMDB VILASECA - Network Topology Generator with Go.js
 */

let myDiagram = null;
let allGroups = [];
let currentMapping = {}; 

function initTopology() {
    initDiagram();
    loadFilters();
    setupEventListeners();

    if ($.fn.select2) {
        $('.select2bs4').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
    }
}

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initTopology();
} else {
    document.addEventListener('DOMContentLoaded', initTopology);
}

function initDiagram() {
    const $ = go.GraphObject.make;

    myDiagram = $(go.Diagram, "myDiagramDiv", {
        "undoManager.isEnabled": true,
        layout: $(go.ForceDirectedLayout, {
            defaultSpringLength: 50,
            defaultElectricalCharge: -150
        }),
        initialContentAlignment: go.Spot.Center
    });

    // Overview Minimap
    myOverview = $(go.Overview, "myOverviewDiv", { 
        observed: myDiagram,
        contentAlignment: go.Spot.Center 
    });

    // --- NODE TEMPLATES ---

    // Hub Node Template (Level 0 - Central)
    myDiagram.nodeTemplateMap.add("Hub",
        $(go.Node, "Vertical",
            { isTreeExpanded: true, locationSpot: go.Spot.Center },
            $(go.Panel, "Auto",
                $(go.Shape, "Circle", { fill: "#4B79A1", stroke: "#283E51", strokeWidth: 2, width: 90, height: 90 }),
                $(go.Picture, { source: "https://img.icons8.com/color/96/network-hub.png", width: 55, height: 55 }),
                $("TreeExpanderButton", { alignment: go.Spot.TopRight })
            ),
            $(go.TextBlock, { margin: 5, font: "bold 12pt sans-serif", stroke: "#2c3e50" },
                new go.Binding("text", "name"))
        )
    );

    // Port Template (Level 2 - Interfaces)
    myDiagram.nodeTemplateMap.add("Port",
        $(go.Node, "Horizontal",
            {
                doubleClick: (e, obj) => {
                    const d = obj.data;
                    const baseUrl = window.zabbixBaseUrl || 'http://172.32.1.50/zabbix/';
                    // Construir URL de monitoreo filtrada por nombre de interfaz y host
                    const url = `${baseUrl}zabbix.php?action=latest.view&name=Interface%20${encodeURIComponent(d.name)}&hostids%5B%5D=${d.hostid}&filter_set=1`;
                    window.open(url, '_blank');
                }
            },
            $(go.Shape, "Circle", 
                { width: 12, height: 12, stroke: null },
                new go.Binding("fill", "status", s => s == 1 ? "#28a745" : "#BDC3C7")
            ),
            $(go.TextBlock, { margin: new go.Margin(0, 0, 0, 4), font: "8pt sans-serif", stroke: "#7F8C8D" },
                new go.Binding("text", "name"))
        )
    );

    // Standard Node Template (Level 1 - Hosts)
    myDiagram.nodeTemplate = $(go.Node, "Vertical",
        {
            locationSpot: go.Spot.Center,
            isTreeExpanded: false,
            doubleClick: (e, obj) => {
                showHostDetails(obj.data);
            }
        },
        $(go.Panel, "Auto",
            $(go.Shape, "RoundedRectangle",
                { fill: "#FFF", strokeWidth: 2, parameter1: 10 },
                new go.Binding("stroke", "status", s => s == 1 ? "#dc3545" : "#00C9FF") // Cyan border if active
            ),
            $(go.Panel, "Vertical", { margin: 8 },
                $(go.Picture,
                    { width: 45, height: 45 },
                    new go.Binding("source", "type", (t, node) => getIconForType(t, node.part.data.name))
                ),
                $("TreeExpanderButton", { 
                    alignment: go.Spot.BottomRight, 
                    visible: false,
                    "ButtonBorder.fill": "white"
                }, new go.Binding("visible", "hasPorts"))
            )
        ),
        $(go.TextBlock, { margin: 4, font: "bold 9pt sans-serif", stroke: "#34495E" },
            new go.Binding("text", "name")),
        $(go.TextBlock, { font: "7pt monospace", stroke: "#95A5A6" },
            new go.Binding("text", "ip"))
    );

    // --- LINK TEMPLATES ---

    myDiagram.linkTemplate = $(go.Link,
        { routing: go.Link.Normal, toShortLength: 3, fromShortLength: 3 },
        $(go.Shape, { strokeWidth: 1.5, stroke: "#BDC3C7" }), 
        $(go.Shape, { toArrow: "Chevron", stroke: null, fill: "#BDC3C7", scale: 0.8 }),
        $(go.TextBlock, { segmentOffset: new go.Point(0, -10), font: "7pt sans-serif", stroke: "#95A5A6" },
            new go.Binding("text", "text"))
    );

    myDiagram.linkTemplateMap.add("PortLink",
        $(go.Link,
            { routing: go.Link.Normal },
            $(go.Shape, { stroke: "#00C9FF", strokeWidth: 1, strokeDashArray: [3, 3] })
        )
    );
}

function getIconForType(type, name) {
    const base = 'https://img.icons8.com/color/48/';
    const n = (name || '').toLowerCase();
    if (type === 'Neighbor' || type === 'vecino' || type === 'Externo') return 'https://img.icons8.com/color/48/external-link.png';
    if (n.includes('sw') || n.includes('switch') || n.includes('core')) return base + 'switch.png';
    if (n.includes('fw') || n.includes('firewall')) return base + 'firewall.png';
    return base + 'server.png';
}

function loadFilters() {
    fetch('api_topology.php?action=get_groups')
        .then(r => r.json())
        .then(js => {
            if (!js.success) return console.error(js.error);
            allGroups = js.result;
            const select = $('#subgrupo-select');
            select.empty().append('<option value="">Seleccione un Grupo</option>');
            allGroups.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.name;
                opt.textContent = g.name;
                select.append(opt);
            });
            select.trigger('change');
        });
}

function setupEventListeners() {
    $('#subgrupo-select').on('change', () => {
        $('#generate-btn').prop('disabled', !$('#subgrupo-select').val());
    });
    $('#layout-select').on('change', function() { changeLayout($(this).val()); });
    $('#generate-btn').on('click', () => renderGraph());
}

function changeLayout(type) {
    const $ = go.GraphObject.make;
    let newLayout;
    switch(type) {
        case 'tree': newLayout = $(go.TreeLayout, { angle: 90, nodeSpacing: 50, layerSpacing: 80 }); break;
        case 'network': newLayout = $(go.LayeredDigraphLayout, { direction: 0, layerSpacing: 100, columnSpacing: 50 }); break;
        case 'star': newLayout = $(go.CircularLayout, { radius: 200, arrangement: go.CircularLayout.ConstantSpacing }); break;
        case 'grid': newLayout = $(go.GridLayout, { wrappingColumn: 4, spacing: new go.Size(50, 50) }); break;
        default: newLayout = $(go.TreeLayout, { angle: 90 });
    }
    myDiagram.startTransaction("change Layout");
    myDiagram.layout = newLayout;
    const routing = (type === 'tree' || type === 'network') ? 'orthogonal' : 'normal';
    myDiagram.nodes.each(node => {
        if (node.category === 'Hub') {
            if (type === 'star') {
                node.isLayoutPositioned = false;
                node.location = new go.Point(0, 0);
            } else {
                node.isLayoutPositioned = true;
            }
        }
    });
    myDiagram.model.linkDataArray.forEach(l => {
        myDiagram.model.setDataProperty(l, "routingType", routing);
    });
    myDiagram.commitTransaction("change Layout");
    setTimeout(() => { centerOnHub(); }, 300);
}

function renderGraph() {
    const loader = document.getElementById('diagram-loader');
    loader.classList.remove('d-none');
    const sub = $('#subgrupo-select').val();
    const params = new URLSearchParams({ action: 'get_topology', subgrupo: sub });

    const titleEl = document.getElementById('diagram-title');
    if (titleEl) titleEl.textContent = sub || 'Topología General';

    fetch(`api_topology.php?${params.toString()}`)
        .then(r => r.json())
        .then(js => {
            loader.classList.add('d-none');
            if (!js.success) return Swal.fire('Error', js.error, 'error');
            if (!js.hosts || js.hosts.length === 0) return Swal.fire('Info', 'No se encontraron equipos.', 'info');
            
            const nodeDataArray = [];
            const linkDataArray = [];
            const hostKeys = new Set();
            const groupKey = 'group_' + sub;

            // Hub Node
            nodeDataArray.push({ key: groupKey, name: sub, category: "Hub" });

            // 1. Process Hosts
            js.hosts.forEach(h => {
                const key = 'host_' + h.name;
                const ip = h.interfaces && h.interfaces[0] ? h.interfaces[0].ip : 'N/A';
                const type = h.inventory && h.inventory.type ? h.inventory.type : 'Server';
                hostKeys.add(key);

                nodeDataArray.push({
                    key: key, 
                    name: h.name,
                    ip: ip,
                    status: h.status,
                    type: type,
                    hostid: h.hostid,
                    hasPorts: h.ports && h.ports.length > 0,
                    isTreeExpanded: false
                });

                if (h.ports && h.ports.length > 0) {
                    h.ports.forEach((p, idx) => {
                        const portKey = key + '_p_' + idx;
                        nodeDataArray.push({ 
                            key: portKey, 
                            name: p.name, 
                            status: p.status,
                            hostid: h.hostid, // Pasar hostid del padre para los links
                            category: "Port" 
                        });
                        linkDataArray.push({ from: key, to: portKey, category: "PortLink" });
                    });
                }
            });

            const currentLayout = $('#layout-select').val();
            const routingType = (currentLayout === 'tree' || currentLayout === 'network') ? 'orthogonal' : 'normal';

            // 2. Process Relationships
            if (js.links && js.links.length > 0) {
                js.links.forEach(l => {
                    const fromKey = 'host_' + l.source;
                    const toKey = 'host_' + l.target;
                    ensureNodeExists(l.source, fromKey, nodeDataArray, hostKeys);
                    ensureNodeExists(l.target, toKey, nodeDataArray, hostKeys);
                    linkDataArray.push({ from: fromKey, to: toKey, text: l.type, routingType: routingType });
                });
            } else {
                nodeDataArray.forEach(n => {
                    if (n.category !== "Hub" && n.category !== "Port") {
                        linkDataArray.push({ from: groupKey, to: n.key, text: 'Enlace', routingType: routingType });
                    }
                });
            }

            myDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);
            if (currentLayout === 'star') {
                const hub = myDiagram.findNodeForKey(groupKey);
                if (hub) { hub.isLayoutPositioned = false; hub.location = new go.Point(0, 0); }
            }
            setTimeout(() => { centerOnHub(); }, 100);
        });
}

function centerOnHub() {
    const hub = myDiagram.nodes.filter(n => n.category === 'Hub').first();
    if (hub) myDiagram.centerRect(hub.actualBounds);
    else myDiagram.zoomToFit();
}

function ensureNodeExists(name, key, nodeArray, keySet) {
    if (!keySet.has(key)) {
        nodeArray.push({ key: key, name: name, type: 'Neighbor', ip: 'Externo' });
        keySet.add(key);
    }
}

function zoomToFit() { myDiagram.zoomToFit(); }
function zoomIn() { myDiagram.commandHandler.increaseZoom(); }
function zoomOut() { myDiagram.commandHandler.decreaseZoom(); }

function showHostDetails(data) {
    if (data.category === "Hub" || data.category === "Port") return;

    const baseUrl = window.zabbixBaseUrl || 'http://172.32.1.50/zabbix/';
    const statusHtml = data.status == 0 
        ? '<span class="badge badge-success px-3">Activo</span>' 
        : '<span class="badge badge-danger px-3">Inactivo/Deshabilitado</span>';

    Swal.fire({
        title: `<div class="text-left font-weight-bold" style="font-size: 1.2rem; color: #2c3e50;">
                    <i class="fas fa-microchip mr-2 text-primary"></i>Ficha del Equipo
                </div>`,
        html: `
            <div class="text-left py-2" style="font-family: inherit;">
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded" style="border-left: 4px solid #00C9FF;">
                    <img src="${getIconForType(data.type, data.name)}" style="width: 50px; height: 50px;" class="mr-3">
                    <div>
                        <h4 class="mb-0 font-weight-bold" style="color: #2c3e50;">${data.name}</h4>
                        <code class="text-muted">${data.ip}</code>
                    </div>
                </div>
                
                <div class="row small mb-3">
                    <div class="col-6">
                        <label class="mb-0 text-muted">Estado Zabbix</label>
                        <div>${statusHtml}</div>
                    </div>
                    <div class="col-6">
                        <label class="mb-0 text-muted">Tipo Dispositivo</label>
                        <div class="font-weight-bold">${data.type || 'N/A'}</div>
                    </div>
                </div>

                <div class="alert alert-info py-2 small mb-4">
                    <i class="fas fa-info-circle mr-1"></i> Información técnica vinculada desde Zabbix y la CMDB.
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="${baseUrl}zabbix.php?action=latest.view&hostids%5B%5D=${data.hostid}&filter_set=1" target="_blank" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-chart-line mr-1"></i> Monitoreo
                    </a>
                    <a href="${baseUrl}zabbix.php?action=host.edit&hostid=${data.hostid}" target="_blank" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-cog mr-1"></i> Configuración
                    </a>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        width: '450px',
        customClass: {
            popup: 'rounded-lg shadow-lg'
        }
    });
}
