let myDiagram = null;

$(document).ready(function() {
    initDiagram();
    loadGraphData();
});

function initDiagram() {
    const $ = go.GraphObject.make;

    myDiagram = $(go.Diagram, "myDiagramDiv", {
        "undoManager.isEnabled": true,
        // Layout para los nodos root o grupos principales
        layout: $(go.LayeredDigraphLayout, { 
            direction: 90, 
            layerSpacing: 50, 
            columnSpacing: 50 
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
        $(go.Shape, "RoundedRectangle",
            { fill: "#ffffff", strokeWidth: 2, stroke: "#3498db", parameter1: 5 },
            new go.Binding("stroke", "status", s => s === 'Activo' ? "#2ecc71" : (s === 'Pasivo' ? '#f39c12' : "#e74c3c"))
        ),
        $(go.Panel, "Horizontal", { margin: 8 },
            $(go.Picture, { width: 24, height: 24, source: "https://img.icons8.com/color/48/server.png" }),
            $(go.TextBlock, { margin: new go.Margin(0, 0, 0, 8), font: "bold 10pt sans-serif", stroke: "#2c3e50" },
                new go.Binding("text", "name"))
        )
    );

    // Plantilla para las Categorías (Grupos Anidados)
    myDiagram.groupTemplate = $(go.Group, "Auto",
        {
            layout: $(go.LayeredDigraphLayout, { direction: 0, columnSpacing: 10 }),
            isSubGraphExpanded: true,
            subGraphExpandedChanged: function(g) {
                if (g.isSubGraphExpanded) g.layout.invalidateLayout();
            }
        },
        $(go.Shape, "RoundedRectangle",
            { fill: "rgba(236, 240, 241, 0.5)", stroke: "#bdc3c7", strokeWidth: 2, parameter1: 10 }
        ),
        $(go.Panel, "Vertical",
            { defaultAlignment: go.Spot.Left, margin: 4 },
            $(go.Panel, "Horizontal",
                { defaultAlignment: go.Spot.Top },
                $("SubGraphExpanderButton"),
                $(go.TextBlock,
                    { font: "bold 12pt sans-serif", margin: 4, stroke: "#34495e" },
                    new go.Binding("text", "name"))
            ),
            $(go.Placeholder, { padding: new go.Margin(10, 10) })
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
                new go.Binding("text", "type"))
        )
    );
}

function loadGraphData() {
    $('#diagram-loader').removeClass('d-none');
    
    let url = 'api_ci.php?action=get_ci_business_view';
    if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
        url += '&ci_id=' + FOCUS_CI_ID;
    }

    $.get(url, function(res) {
        $('#diagram-loader').addClass('d-none');
        if (res.success) {
            let nodeDataArray = [];
            let linkDataArray = [];
            
            // 1. Crear Grupos (Categorías)
            res.data.categories.forEach(cat => {
                nodeDataArray.push({
                    key: 'cat_' + cat.id,
                    name: cat.name,
                    isGroup: true,
                    group: cat.parent_id ? 'cat_' + cat.parent_id : undefined
                });
            });
            
            // 2. Crear Nodos (CIs)
            res.data.cis.forEach(ci => {
                nodeDataArray.push({
                    key: 'ci_' + ci.id,
                    name: ci.hostname,
                    status: ci.status,
                    group: ci.category_id ? 'cat_' + ci.category_id : undefined
                });
            });
            
            // 3. Crear Enlaces (Relaciones)
            res.data.relationships.forEach(rel => {
                linkDataArray.push({
                    from: 'ci_' + rel.source_id,
                    to: 'ci_' + rel.target_id,
                    type: rel.relation_type
                });
            });
            
            myDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);

            if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
                // Seleccionar el nodo enfocado al terminar de cargar
                setTimeout(() => {
                    showRelations('ci_' + FOCUS_CI_ID);
                    let node = myDiagram.findNodeForKey('ci_' + FOCUS_CI_ID);
                    if (node !== null) {
                        myDiagram.centerRect(node.actualBounds);
                    }
                }, 100);
            }
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        $('#diagram-loader').addClass('d-none');
        Swal.fire('Error', 'Error al cargar los datos de la red', 'error');
        console.error(jqXHR.responseText);
    });
}

function zoomToFit() {
    if (myDiagram) myDiagram.zoomToFit();
}

function showRelations(ciKey) {
    // Resaltar los nodos vinculados al hacer clic
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
