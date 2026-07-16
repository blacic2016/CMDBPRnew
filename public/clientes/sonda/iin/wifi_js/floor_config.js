const WIFI_ICON_PATH = "M12.016 4.695c-3.13 0-5.955 1.272-7.994 3.328l2.584 2.583c1.385-1.396 3.295-2.257 5.41-2.257 2.115 0 4.025.861 5.41 2.257l2.584-2.583c-2.039-2.056-4.864-3.328-7.994-3.328zm0 5.474c-1.636 0-3.111.666-4.177 1.74l2.584 2.583c.404-.411.964-.666 1.593-.666.629 0 1.189.255 1.593.666l2.584-2.583c-1.066-1.074-2.541-1.74-4.177-1.74zm0 5.474c-.663 0-1.2.537-1.2 1.2s.537 1.2 1.2 1.2 1.2-.537 1.2-1.2-.537-1.2-1.2-1.2zm11.984-10.948l-2.584 2.583c3.425 3.453 3.425 9.06 0 12.515l2.584 2.583c4.85-4.887 4.85-12.802 0-17.681zm-23.968 0c-4.85 4.879-4.85 12.794 0 17.681l2.584-2.583c-3.425-3.454-3.425-9.062 0-12.515l-2.584-2.583z";

let myDiagram, myPalette;
const $ = go.GraphObject.make;
let floorConfigState = {
    floorplans: [],
    currentFloor: null,
    bgPart: null,
    allPositions: []
};

async function initFloorConfig() {

    // Attach UI handlers (always do this if elements exist)
    const btnSave = document.getElementById('btn-save-floor');
    if (btnSave) {
        btnSave.onclick = (e) => {
            e.preventDefault();
            console.log("Save button clicked - state:", floorConfigState.currentFloor);
            saveCurrentState();
        };
    }

    const btnNew = document.getElementById('btn-new-floor');
    if (btnNew) btnNew.onclick = () => createNewFloor();

    const imgInput = document.getElementById('floor-image-input');
    if (imgInput) imgInput.onchange = (e) => handleImageUpload(e);

    const btnZoomIn = document.getElementById('btn-zoom-in');
    if (btnZoomIn) btnZoomIn.onclick = () => myDiagram && myDiagram.commandHandler.increaseZoom();

    const btnZoomOut = document.getElementById('btn-zoom-out');
    if (btnZoomOut) btnZoomOut.onclick = () => myDiagram && myDiagram.commandHandler.decreaseZoom();

    const btnCenter = document.getElementById('btn-center');
    if (btnCenter) btnCenter.onclick = () => myDiagram && myDiagram.zoomToFit();

    if (myDiagram) {
        await loadConfigData();
        return;
    }

    // Initialize Diagram
    myDiagram = $(go.Diagram, "floor-diagram-div", {
        "undoManager.isEnabled": true,
        scrollMode: go.Diagram.InfiniteScrolling,
        allowZoom: true,
        "toolManager.mouseWheelBehavior": go.ToolManager.WheelZoom,
        hasHorizontalScrollbar: true,
        hasVerticalScrollbar: true,
        contentAlignment: go.Spot.Center
    });

    myDiagram.nodeTemplate =
        $(go.Node, "Vertical",
            { locationSpot: go.Spot.Center },
            new go.Binding("location", "loc", go.Point.parse).makeTwoWay(go.Point.stringify),
            $(go.Panel, "Auto",
                $(go.Shape, "Circle", {
                    fill: "rgba(88, 166, 255, 0.1)",
                    stroke: "#58a6ff",
                    strokeWidth: 2,
                    width: 46, height: 46
                }),
                $(go.Shape, {
                    geometryString: WIFI_ICON_PATH,
                    fill: "#58a6ff",
                    stroke: null,
                    width: 24, height: 20
                })
            ),
            $(go.TextBlock, {
                margin: 4, font: "600 11px Inter", stroke: "#c9d1d9",
                background: "rgba(13, 17, 23, 0.8)", textAlign: "center"
            }, new go.Binding("text", "name"))
        );

    myPalette = $(go.Palette, "ap-palette-div", {
        nodeTemplate: myDiagram.nodeTemplate,
        layout: $(go.GridLayout, { wrappingColumn: 2, cellSize: new go.Size(80, 80) })
    });

    await loadConfigData();

    async function loadConfigData() {
        try {
            const [fpResp, posResp] = await Promise.all([
                fetch('wifi_php/api.php?action=get_floorplans'),
                fetch('wifi_php/api.php?action=get_ap_positions')
            ]);
            const fpData = await fpResp.json();
            const posData = await posResp.json();

            if (fpData.success) {
                floorConfigState.floorplans = fpData.floorplans;
                renderFloorList();
            }
            floorConfigState.allPositions = posData.success ? posData.positions : [];
            updatePalette();
        } catch (e) {
            console.error("Error loading config:", e);
        }
    }

    function updatePalette() {
        if (window.appData && window.appData.aps && window.appData.aps.length > 0) {
            myPalette.model.nodeDataArray = window.appData.aps.map(ap => ({
                key: ap.ip,
                name: ap.name,
                ip: ap.ip,
                color: "#58a6ff"
            }));
        } else {
            setTimeout(updatePalette, 1000);
        }
    }

    function renderFloorList() {
        const list = document.getElementById('floor-config-list');
        if (!list) return;
        list.innerHTML = '';
        floorConfigState.floorplans.forEach(fp => {
            const div = document.createElement('div');
            div.className = `floor-item ${floorConfigState.currentFloor?.id === fp.id ? 'active' : ''}`;
            div.innerHTML = `<span>${fp.name}</span> <button class="delete-btn" type="button" title="Eliminar">×</button>`;
            div.onclick = () => selectFloor(fp);

            const delBtn = div.querySelector('.delete-btn');
            delBtn.onclick = (e) => {
                e.stopPropagation();
                if (delBtn.classList.contains('confirm-mode')) {
                    deleteFloor(fp.id);
                } else {
                    delBtn.textContent = '¿Borrar?';
                    delBtn.classList.add('confirm-mode');
                    setTimeout(() => {
                        delBtn.textContent = '×';
                        delBtn.classList.remove('confirm-mode');
                    }, 3000);
                }
            };
            list.appendChild(div);
        });
    }

    window.selectFloor = (fp) => {
        floorConfigState.currentFloor = fp;
        document.getElementById('floor-name').value = fp.name;
        document.getElementById('floor-scale').value = fp.scale || 0.05;
        document.getElementById('grid-meters').value = fp.grid_meters || 5;

        myDiagram.startTransaction("select floor");
        myDiagram.clear();

        if (fp.image_url) {
            const pic = $(go.Picture, { source: fp.image_url, imageStretch: go.GraphObject.None });
            const imgPart = $(go.Part, {
                layerName: "Background", pickable: false, selectable: false, position: new go.Point(0, 0)
            }, pic);
            myDiagram.add(imgPart);
            floorConfigState.bgPart = imgPart;

            const checkLoad = () => {
                if (pic.naturalBounds.width > 0) {
                    myDiagram.startTransaction("set bounds");
                    myDiagram.fixedBounds = pic.naturalBounds;
                    myDiagram.zoomToFit();
                    myDiagram.contentAlignment = go.Spot.Center;
                    myDiagram.commitTransaction("set bounds");
                } else {
                    setTimeout(checkLoad, 100);
                }
            };
            checkLoad();
        }

        if (fp.model_json) {
            myDiagram.model = go.Model.fromJson(fp.model_json);
        } else {
            const floorAps = floorConfigState.allPositions.filter(p => p.floor_id === fp.id);
            myDiagram.model.nodeDataArray = floorAps.map(p => ({
                key: p.ap_ip, name: p.ap_ip, loc: `${p.x} ${p.y}`
            }));
        }
        myDiagram.commitTransaction("select floor");
        renderFloorList();
    };
}

function createNewFloor() {
    floorConfigState.currentFloor = { id: '', name: 'Piso Nuevo', scale: 0.05, grid_meters: 5 };
    document.getElementById('floor-name').value = 'Piso Nuevo';
    if (myDiagram) myDiagram.clear();
}

async function saveCurrentState() {
    if (!floorConfigState.currentFloor) {
        alert("Primero crea un nuevo piso o selecciona uno de la lista.");
        return;
    }

    if (typeof showModal === 'function') showModal("Guardando...", "<div style='text-align:center'><p>Enviando datos al servidor...</p><div class='spinner'></div></div>");

    const name = document.getElementById('floor-name').value;
    const exists = floorConfigState.floorplans.find(fp => fp.name === name && fp.id !== floorConfigState.currentFloor.id);
    if (exists && !floorConfigState.currentFloor.id) {
        if (!confirm(`Ya existe un plano llamado "${name}". ¿Desea actualizarlo?`)) {
            if (document.getElementById('modal-overlay')) document.getElementById('modal-overlay').classList.remove('active');
            return;
        }
    }

    const formData = new FormData();
    formData.append('id', floorConfigState.currentFloor.id);
    formData.append('name', name);
    formData.append('scale', document.getElementById('floor-scale').value);
    formData.append('grid_meters', document.getElementById('grid-meters').value);
    formData.append('ap_size', document.getElementById('ap-icon-size').value);
    // Send existing image_url to preserve it when not uploading a new image
    formData.append('image_url', floorConfigState.currentFloor.image_url || '');
    if (myDiagram) formData.append('model_json', myDiagram.model.toJson());

    const imageInput = document.getElementById('floor-image-input');
    if (imageInput && imageInput.files[0]) formData.append('image', imageInput.files[0]);

    try {
        const fpResp = await fetch('wifi_php/api.php?action=save_floorplan', { method: 'POST', body: formData });
        const fpRes = await fpResp.json();
        console.log("Save floorplan response:", fpRes);

        if (fpRes.success) {
            const floorId = fpRes.id;
            const positions = [];
            if (myDiagram) {
                myDiagram.nodes.each(node => {
                    if (node.data && node.data.key) {
                        positions.push({ ap_ip: node.data.key, x: node.location.x, y: node.location.y });
                    }
                });
            }

            const apFormData = new FormData();
            apFormData.append('floor_id', floorId);
            apFormData.append('positions', JSON.stringify(positions));

            const apResp = await fetch('wifi_php/api.php?action=save_all_ap_positions', { method: 'POST', body: apFormData });
            const apRes = await apResp.json();

            if (apRes.success) {
                if (typeof showModal === 'function') showModal("¡Guardado!", "<p>Todo se ha guardado correctamente.</p>");
                initFloorConfig(); // Refresh
            } else {
                throw new Error(apRes.error);
            }
        }
    } catch (e) {
        console.error("Save Error:", e);
        if (typeof showModal === 'function') showModal("Error", `<p>${e.message}</p>`);
    }
}

async function handleImageUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    // 1. IMMEDIATELY upload the image to the server
    const uploadData = new FormData();
    uploadData.append('image', file);

    try {
        const resp = await fetch('wifi_php/api.php?action=upload_image', { method: 'POST', body: uploadData });
        const result = await resp.json();
        console.log("Image upload result:", result);

        if (!result.success) {
            alert("Error al subir imagen: " + result.error);
            return;
        }

        // 2. Store the server URL in the current floor state
        if (!floorConfigState.currentFloor) {
            floorConfigState.currentFloor = { id: '', name: 'Piso Nuevo', scale: 0.05, grid_meters: 5 };
        }
        floorConfigState.currentFloor.image_url = result.image_url;
        console.log("Image saved to server:", result.image_url);

        // 3. Display in Go.js diagram using the SERVER URL (not DataURL)
        if (myDiagram) {
            myDiagram.startTransaction("add background");
            if (floorConfigState.bgPart) myDiagram.remove(floorConfigState.bgPart);

            const pic = $(go.Picture, { source: result.image_url, imageStretch: go.GraphObject.None });
            const imgPart = $(go.Part, {
                layerName: "Background",
                pickable: false,
                selectable: false,
                position: new go.Point(0, 0)
            }, pic);

            myDiagram.add(imgPart);
            floorConfigState.bgPart = imgPart;
            myDiagram.commitTransaction("add background");

            const checkLoad = () => {
                if (pic.naturalBounds.width > 0) {
                    myDiagram.startTransaction("set bounds");
                    myDiagram.fixedBounds = pic.naturalBounds;
                    myDiagram.zoomToFit();
                    myDiagram.contentAlignment = go.Spot.Center;
                    myDiagram.commitTransaction("set bounds");
                } else {
                    setTimeout(checkLoad, 100);
                }
            };
            checkLoad();
        }
    } catch (err) {
        console.error("Upload error:", err);
        alert("Error de conexión al subir imagen");
    }
}

async function deleteFloor(id) {
    const resp = await fetch(`wifi_php/api.php?action=delete_floorplan&id=${id}`);
    const res = await resp.json();
    if (res.success) initFloorConfig();
}
