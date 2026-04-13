/**
 * CMDB VILASECA - Network Topology Generator with Go.js
 */

let myDiagram = null;
let allGroups = [];
let currentMapping = {}; // Nested structure for filters

function initTopology() {
    initDiagram();
    loadFilters();
    setupEventListeners();

    // Initialize Select2
    // Initialize Select2
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
        layout: $(go.TreeLayout, {
            angle: 90,
            nodeSpacing: 50,
            layerSpacing: 80
        }),
        initialContentAlignment: go.Spot.Center
    });

    // Node Template
    myDiagram.nodeTemplate = $(go.Node, "Vertical",
        {
            locationSpot: go.Spot.Center,
            doubleClick: (e, obj) => {
                const data = obj.data;
                if (data.hostid) {
                    window.open(`https://app.vilaseca.com.ec/zabbix/zabbix.php?action=host.edit&hostid=${data.hostid}`, '_blank');
                }
            }
        },
        $(go.Panel, "Auto",
            $(go.Shape, "Rectangle",
                { fill: "white", stroke: "#ccc", strokeWidth: 1 },
                new go.Binding("stroke", "status", s => s == 1 ? "#dc3545" : "#28a745") // Red if disabled
            ),
            $(go.Picture,
                { width: 40, height: 40, margin: 10 },
                new go.Binding("source", "type", (t, node) => getIconForType(t, node.part.data.name))
            )
        ),
        $(go.TextBlock,
            { margin: 5, font: "bold 10pt Sans-Serif", textAlign: "center", maxSize: new go.Size(120, NaN) },
            new go.Binding("text", "name")
        ),
        $(go.TextBlock,
            { margin: 2, font: "8pt Sans-Serif", stroke: "#666" },
            new go.Binding("text", "ip")
        )
    );

    // Hub Node Template (Central Hub - NOT a Group)
    myDiagram.nodeTemplateMap.add("Hub",
        $(go.Node, "Vertical",
            { 
                locationSpot: go.Spot.Center,
                isLayoutPositioned: false, // Pin it
                location: new go.Point(0, 0),
                isTreeExpanded: true
            },
            $(go.Panel, "Auto",
                $(go.Shape, "Circle", { fill: "#f8f9fa", stroke: "#007bff", strokeWidth: 2, width: 85, height: 85 }),
                $(go.Picture, { source: "https://img.icons8.com/color/96/data-configuration.png", width: 60, height: 60 }),
                $("TreeExpanderButton", { alignment: go.Spot.TopRight, alignmentFocus: go.Spot.Center })
            ),
            $(go.TextBlock, { margin: 5, font: "bold 13pt sans-serif", stroke: "#333", textAlign: "center" },
                new go.Binding("text", "name"))
        )
    );

    // Group Template (Not used in this mode, but keep as fallback)
    myDiagram.groupTemplate = $(go.Group, "Vertical",
        { isSubGraphExpanded: true },
        $(go.Panel, "Auto",
            $(go.Shape, "Rectangle", { fill: "rgba(0,123,255,0.05)", stroke: "#007bff", strokeDashArray: [4, 2] }),
            $(go.Placeholder, { padding: 10 })
        )
    );

    // Link Template
    myDiagram.linkTemplate = $(go.Link,
        { 
            routing: go.Link.Normal,
            toShortLength: 4,
            fromShortLength: 4 
        },
        $(go.Shape, { strokeWidth: 2.2, stroke: "#2c3e50" }), // Deep dark blue
        $(go.Shape, { toArrow: "Standard", stroke: null, fill: "#2c3e50" })
    );
}

function getIconForType(type, name) {
    const base = 'https://img.icons8.com/color/48/';
    const n = (name || '').toLowerCase();
    
    if (type === 'Neighbor' || type === 'vecino' || type === 'Externo') {
        return 'https://img.icons8.com/color/48/external-link.png';
    }
    
    // Switch icon for core network devices based on name
    if (n.includes('sw') || n.includes('switch') || n.includes('core')) return base + 'switch.png';
    if (n.includes('fw') || n.includes('firewall')) return base + 'firewall.png';
    
    return base + 'server.png'; // Unified server/device icon
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
                opt.value = g.name; // Use name as search token
                opt.textContent = g.name;
                select.append(opt);
            });
            select.trigger('change');
        });
}

function processGroups(groups) {
    currentMapping = {};
    
    groups.forEach(g => {
        // Zabbix nested groups usually use / (e.g., Parent/Child/Grandchild)
        // Global nomenclature uses _ (e.g., PAIS_CIUDAD_CLIENTE)
        
        let pais, ciudad, cliente, sub;
        
        // Strategy: First normalize slashes to underscores to treat them as levels
        // but only if they don't already have underscores. 
        // Or better: handle them separately.
        
        const name = g.name;
        const parts = name.split('_');

        if (parts.length >= 2) {
            // Case 1: Standard underscore nomenclature PAIS_CIUDAD_...
            pais = parts[0];
            ciudad = parts[1] || 'GENERAL';
            cliente = parts[2] || 'GENERAL';
            sub = name;
        } else if (name.includes('/')) {
            // Case 2: Zabbix Nested hierarchy Parent/Child
            const nested = name.split('/');
            pais = nested[0];
            ciudad = nested[1] || 'GENERAL';
            cliente = nested[2] || 'GENERAL';
            sub = name;
        } else {
            // Case 3: Simple names
            pais = 'N/A';
            ciudad = 'GENERAL';
            cliente = 'GENERAL';
            sub = name;
        }

        if (!currentMapping[pais]) currentMapping[pais] = {};
        if (!currentMapping[pais][ciudad]) currentMapping[pais][ciudad] = {};
        if (!currentMapping[pais][ciudad][cliente]) currentMapping[pais][ciudad][cliente] = {};
        
        currentMapping[pais][ciudad][cliente][sub] = g.groupid;
    });
}

function populateDropdown(id, items) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">Seleccione ${id.split('-')[0].charAt(0).toUpperCase() + id.split('-')[0].slice(1)}</option>`;
    items.sort().forEach(item => {
        const opt = document.createElement('option');
        opt.value = item;
        opt.textContent = item;
        el.appendChild(opt);
    });
    el.disabled = false;
    $(el).trigger('change');
}

function setupEventListeners() {
    const s = $('#subgrupo-select');
    const btn = $('#generate-btn');

    s.on('change', () => {
        btn.prop('disabled', !s.val());
    });

    $('#layout-select').on('change', function() {
        changeLayout($(this).val());
    });

    btn.on('click', () => renderGraph());
}

function changeLayout(type) {
    const $ = go.GraphObject.make;
    let newLayout;

    switch(type) {
        case 'tree':
            newLayout = $(go.TreeLayout, { angle: 90, nodeSpacing: 50, layerSpacing: 80 });
            break;
        case 'network':
            newLayout = $(go.LayeredDigraphLayout, { direction: 0, layerSpacing: 100, columnSpacing: 50 });
            break;
        case 'star':
            newLayout = $(go.CircularLayout, { 
                radius: 200, 
                arrangement: go.CircularLayout.ConstantSpacing 
            });
            break;
        case 'grid':
            newLayout = $(go.GridLayout, { wrappingColumn: 4, spacing: new go.Size(50, 50) });
            break;
        default:
            newLayout = $(go.TreeLayout, { angle: 90 });
    }

    myDiagram.startTransaction("change Layout");
    myDiagram.layout = newLayout;
    
    const routing = (type === 'tree' || type === 'network') ? 'orthogonal' : 'normal';

    // Update the Hub node positioning
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

    // Update link routing based on layout
    myDiagram.model.linkDataArray.forEach(l => {
        myDiagram.model.setDataProperty(l, "routingType", routing);
    });

    myDiagram.commitTransaction("change Layout");

    setTimeout(() => {
        centerOnHub();
    }, 300); // Slightly longer for layout animations
}

function renderGraph() {
    const loader = document.getElementById('diagram-loader');
    loader.classList.remove('d-none');
    
    const sub = $('#subgrupo-select').val();

    const params = new URLSearchParams({
        action: 'get_topology',
        subgrupo: sub
    });

    const titleEl = document.getElementById('diagram-title');
    if (titleEl) titleEl.textContent = sub || 'Topología General';

    fetch(`api_topology.php?${params.toString()}`)
        .then(r => r.json())
        .then(js => {
            loader.classList.add('d-none');
            if (!js.success) return Swal.fire('Error', js.error, 'error');
            
            if (!js.hosts || js.hosts.length === 0) {
                return Swal.fire({
                    icon: 'info',
                    title: 'Grupo Vacío',
                    text: 'No se encontraron equipos asociados a este grupo o a sus subgrupos en Zabbix.',
                    confirmButtonText: 'Entendido'
                });
            }
            const nodeDataArray = [];
            const linkDataArray = [];
            const hostKeys = new Set();
            
            // Unique key for the visual container
            const groupKey = 'group_' + sub;

            // 1. Process Hosts in Group
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
                    hostid: h.hostid
                });
            });

            const currentLayout = $('#layout-select').val();
            const routingType = (currentLayout === 'tree' || currentLayout === 'network') ? 'orthogonal' : 'normal';

            // Add the Group Hub Node
            nodeDataArray.push({ 
                key: groupKey, 
                name: sub, 
                category: "Hub" 
            });

            // 2. Process Real Links from CMDB Relaciones
            if (js.links && js.links.length > 0) {
                js.links.forEach(l => {
                    const fromKey = 'host_' + l.source;
                    const toKey = 'host_' + l.target;

                    ensureNodeExists(l.source, fromKey, nodeDataArray, hostKeys);
                    ensureNodeExists(l.target, toKey, nodeDataArray, hostKeys);
                    
                    linkDataArray.push({ 
                        from: fromKey, 
                        to: toKey, 
                        text: l.type,
                        routingType: routingType
                    });
                });
            } else {
                // FALLBACK: Automatic Star Topology 
                if (nodeDataArray.length > 1) {
                    nodeDataArray.forEach(n => {
                        if (n.category !== "Hub") {
                            linkDataArray.push({ 
                                from: groupKey, 
                                to: n.key, 
                                text: 'Enlace',
                                routingType: routingType 
                            });
                        }
                    });
                }
            }

            console.log('Final Topology Model:', { nodes: nodeDataArray, links: linkDataArray });
            myDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);

            // Initial pinning if in star mode
            if (currentLayout === 'star') {
                const hub = myDiagram.findNodeForKey(groupKey);
                if (hub) {
                    hub.isLayoutPositioned = false;
                    hub.location = new go.Point(0, 0);
                }
            }

            // Small delay to allow layout to settle before centering
            setTimeout(() => {
                centerOnHub();
            }, 100);
        });
}

function centerOnHub() {
    const hub = myDiagram.nodes.filter(n => n.category === 'Hub').first();
    if (hub) {
        myDiagram.centerRect(hub.actualBounds);
    } else {
        myDiagram.zoomToFit();
    }
}

function ensureNodeExists(name, key, nodeArray, keySet) {
    if (!keySet.has(key)) {
        nodeArray.push({
            key: key,
            name: name,
            type: 'Neighbor',
            isNeighbor: true,
            ip: 'Externo'
        });
        keySet.add(key);
    }
}

function zoomToFit() {
    myDiagram.zoomToFit();
}
