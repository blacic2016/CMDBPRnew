let myDiagram = null;

const faUnicodeMap = {
    'fa-cube': '\uf1b2',
    'fa-server': '\uf233',
    'fa-globe': '\uf0ac',
    'fa-building': '\uf1ad',
    'fa-network-wired': '\uf6ff',
    'fa-user-shield': '\uf3f4',
    'fa-laptop': '\uf109',
    'fa-mobile-alt': '\uf3cd',
    'fa-database': '\uf1c0',
    'fa-desktop': '\uf108',
    'fa-shield-alt': '\uf3ed',
    'fa-hdd': '\uf0a0',
    'fa-print': '\uf02f',
    'fa-ethernet': '\uf796',
    'fa-wifi': '\uf1eb',
    'fa-key': '\uf084',
    'fa-users': '\uf0c0',
    'fa-envelope': '\uf0e0',
    'fa-phone': '\uf095',
    'fa-cogs': '\uf085',
    'fa-folder': '\uf07b',
    'fa-file-alt': '\uf15c',
    'fa-door-open': '\uf52b',
    'fa-city': '\uf64f',
    'fa-microchip': '\uf2db'
};

function getUnicodeIcon(iconClass) {
    if (!iconClass) return '\uf1b2';
    let parts = iconClass.split(' ');
    let cleanClass = parts[parts.length - 1].trim();
    return faUnicodeMap[cleanClass] || '\uf1b2';
}

$(document).ready(function() {
    initDiagram();
    loadGraphData();
});

function initDiagram() {
    const $ = go.GraphObject.make;

    myDiagram = $(go.Diagram, "myDiagramDiv", {
        "undoManager.isEnabled": true,
        layout: $(go.LayeredDigraphLayout, { 
            direction: 90, 
            layerSpacing: 60, 
            columnSpacing: 60 
        }),
        initialContentAlignment: go.Spot.Center
    });

    // Plantilla para los CIs (Nodos)
    myDiagram.nodeTemplate = $(go.Node, "Auto",
        { 
            locationSpot: go.Spot.Center,
            click: (e, obj) => {
                showRelations(obj.data.key);
            }
        },
        new go.Binding("location", "loc", go.Point.parse).makeTwoWay(go.Point.stringify),
        new go.Binding("visible", "visible"),
        $(go.Shape, "RoundedRectangle",
            { fill: "#ffffff", strokeWidth: 1.5, stroke: "#bdc3c7", parameter1: 8 },
            new go.Binding("stroke", "status", s => s === 'Activo' ? "#2ecc71" : (s === 'Pasivo' ? '#f39c12' : "#e74c3c"))
        ),
        $(go.Panel, "Vertical", { margin: 10, defaultAlignment: go.Spot.Left },
            // Mini-título: Categoría de cada CI
            $(go.TextBlock, 
                { 
                    font: "italic bold 7.5pt sans-serif", 
                    stroke: "#7f8c8d", 
                    margin: new go.Margin(0, 0, 4, 0) 
                },
                new go.Binding("text", "categoryName", name => name ? name.toUpperCase() : "")
            ),
            // Fila horizontal para Icono, Nombre del CI, y Botón Expander
            $(go.Panel, "Horizontal",
                $(go.TextBlock, 
                    { 
                        font: '900 13pt "Font Awesome 5 Free"', 
                        stroke: "#3498db",
                        margin: new go.Margin(0, 6, 0, 0),
                        alignment: go.Spot.Center
                    },
                    new go.Binding("text", "icon", getUnicodeIcon),
                    new go.Binding("stroke", "status", s => s === 'Activo' ? "#2ecc71" : (s === 'Pasivo' ? '#f39c12' : "#e74c3c"))
                ),
                $(go.TextBlock, 
                    { 
                        font: "bold 10pt sans-serif", 
                        stroke: "#2c3e50" 
                    },
                    new go.Binding("text", "name")
                ),
                $(go.Shape, { width: 10, height: 0, fill: "transparent", stroke: null }),
                $("Button",
                    {
                        alignment: go.Spot.Right,
                        click: (e, obj) => toggleNodeRelations(obj.part)
                    },
                    $(go.TextBlock,
                        { 
                            font: "bold 8pt sans-serif", 
                            stroke: "#555555",
                            margin: new go.Margin(0, 2, 0, 2)
                        },
                        new go.Binding("text", "relationsCollapsed", collapsed => collapsed ? "+" : "-")
                    )
                )
            )
        )
    );

    // Plantilla de Relaciones (Enlaces)
    myDiagram.linkTemplate = $(go.Link,
        { routing: go.Link.AvoidsNodes, curve: go.Link.JumpOver, corner: 5 },
        $(go.Shape, { strokeWidth: 1.5, stroke: "#7f8c8d" }),
        $(go.Shape, { toArrow: "Standard", stroke: null, fill: "#7f8c8d" }),
        $(go.Panel, "Auto",
            $(go.Shape, "RoundedRectangle", { fill: "#ffffff", stroke: null }),
            $(go.TextBlock, { margin: 3, font: "8pt sans-serif", stroke: "#2c3e50" },
                new go.Binding("text", "type")
            )
        )
    );
}

function toggleNodeRelations(node) {
    let diagram = node.diagram;
    diagram.startTransaction("toggle relations");
    
    let collapsed = node.data.relationsCollapsed;
    diagram.model.setDataProperty(node.data, "relationsCollapsed", !collapsed);
    
    node.findLinksConnected().each(l => {
        let other = l.getOtherNode(node);
        if (other !== null && other.data) {
            diagram.model.setDataProperty(other.data, "visible", collapsed);
        }
    });
    
    diagram.commitTransaction("toggle relations");
}

function loadGraphData() {
    $('#diagram-loader').removeClass('d-none').addClass('d-flex').show();
    
    let url = 'api_ci.php?action=get_ci_business_view';
    if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
        url += '&ci_id=' + FOCUS_CI_ID;
    }

    $.get(url, function(res) {
        $('#diagram-loader').removeClass('d-flex').addClass('d-none').hide();
        if (res.success) {
            let nodeDataArray = [];
            let linkDataArray = [];
            
            // Build a set of visible CI IDs initially
            let visibleCIs = new Set();
            if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
                visibleCIs.add(FOCUS_CI_ID);
                // Neighbors start hidden to show only the central equipment first
            } else {
                res.data.cis.forEach(ci => visibleCIs.add(ci.id));
            }
            
            // 1. Crear Nodos (CIs)
            res.data.cis.forEach(ci => {
                let isVisible = visibleCIs.has(ci.id);
                nodeDataArray.push({
                    key: 'ci_' + ci.id,
                    name: ci.hostname,
                    status: ci.status,
                    icon: ci.icon,
                    categoryName: ci.category_name,
                    visible: isVisible,
                    relationsCollapsed: true
                });
            });
            
            // 2. Crear Enlaces (Relaciones entre CIs)
            res.data.relationships.forEach(rel => {
                linkDataArray.push({
                    from: 'ci_' + rel.source_id,
                    to: 'ci_' + rel.target_id,
                    type: rel.relation_type
                });
            });
            
            myDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);

            setTimeout(() => {
                if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
                    showRelations('ci_' + FOCUS_CI_ID);
                    let node = myDiagram.findNodeForKey('ci_' + FOCUS_CI_ID);
                    if (node !== null) {
                        myDiagram.centerRect(node.actualBounds);
                    }
                }
            }, 150);

        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        $('#diagram-loader').removeClass('d-flex').addClass('d-none').hide();
        Swal.fire('Error', 'Error al cargar los datos de la red', 'error');
        console.error(jqXHR.responseText);
    });
}

function zoomToFit() {
    if (myDiagram) myDiagram.zoomToFit();
}

function showRelations(ciKey) {
    myDiagram.clearSelection();
    let node = myDiagram.findNodeForKey(ciKey);
    if (node !== null) {
        myDiagram.select(node);
        node.findNodesConnected().each(n => {
            n.isSelected = true;
        });
        node.findLinksConnected().each(l => {
            l.isSelected = true;
        });
    }
}
