/**
 * Topología de Red 3D - Three.js Implementation
 */

let scene, camera, renderer, labelRenderer, controls;
let nodes = [];
let links = [];
const container = document.getElementById('three-container');

function init3D() {
    try {
        // 1. Scene Setup
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0xf8f9fa); // Fondo blanco/gris claro
        scene.fog = new THREE.Fog(0xf8f9fa, 100, 500);

    // 2. Camera Setup
    camera = new THREE.PerspectiveCamera(70, container.clientWidth / container.clientHeight, 0.1, 2000);
    camera.position.set(0, 100, 250);

    // 3. Renderer Setup
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    // 4. Label Renderer (CSS2D)
    labelRenderer = new THREE.CSS2DRenderer();
    labelRenderer.setSize(container.clientWidth, container.clientHeight);
    labelRenderer.domElement.style.position = 'absolute';
    labelRenderer.domElement.style.top = '0px';
    labelRenderer.domElement.style.pointerEvents = 'none';
    container.appendChild(labelRenderer.domElement);

    // 5. Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;

    // 6. Lights - Mas brillantes para fondo blanco
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
    scene.add(ambientLight);

    const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1);
    hemiLight.position.set(0, 20, 0);
    scene.add(hemiLight);

    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
    dirLight.position.set(-10, 20, 10);
    scene.add(dirLight);

    // 6. Raycaster for Interaction
    const raycaster = new THREE.Raycaster();
    const mouse = new THREE.Vector2();

    container.addEventListener('mousemove', (event) => {
        const rect = container.getBoundingClientRect();
        mouse.x = ((event.clientX - rect.left) / container.clientWidth) * 2 - 1;
        mouse.y = -((event.clientY - rect.top) / container.clientHeight) * 2 + 1;

        // Cambiar cursor si hay intersección
        raycaster.setFromCamera(mouse, camera);
        const intersects = raycaster.intersectObjects(nodes);
        if (intersects.length > 0) {
            container.style.cursor = 'pointer';
        } else {
            container.style.cursor = 'move';
        }
    });

    container.addEventListener('contextmenu', (event) => {
        event.preventDefault(); // Evitar menú del navegador
        
        raycaster.setFromCamera(mouse, camera);
        const intersects = raycaster.intersectObjects(nodes);
        if (intersects.length > 0) {
            const targetPos = new THREE.Vector3();
            intersects[0].object.getWorldPosition(targetPos);
            
            // Suavizar el movimiento del target de OrbitControls
            controls.target.copy(targetPos);
            controls.update();

            Swal.fire({ 
                icon: 'info', 
                title: 'Enfoque de Cámara', 
                text: 'Centrado en: ' + (intersects[0].object.userData.name || 'Nodo'), 
                toast: true, 
                position: 'top-end', 
                showConfirmButton: false, 
                timer: 1500 
            });
        }
    });

        container.addEventListener('click', (event) => {
            // Asegurar que procesamos el click solo si no estamos arrastrando
            raycaster.setFromCamera(mouse, camera);
            const intersects = raycaster.intersectObjects(nodes);
            if (intersects.length > 0) {
                const nodeObj = intersects[0].object;
                const nodeData = nodeObj.userData;
                
                showNodeInfo(nodeData);

                if (typeof nodeObj.onClick === 'function') {
                    nodeObj.onClick();
                }
            } else {
                hideNodeInfo();
            }
        });

        animate();
    } catch (error) {
        console.error("WebGL Init Error:", error);
        container.innerHTML = `
            <div class="alert alert-danger m-4 text-center shadow-sm">
                <h4><i class="fas fa-exclamation-triangle mr-2"></i> WebGL no disponible</h4>
                <p>Tu navegador actual no soporta aceleración 3D por hardware (WebGL) o se encuentra deshabilitado.</p>
                <small class="text-muted">Por favor, habilita WebGL en la configuración del navegador para visualizar la topología.</small>
            </div>
        `;
    }
}

let isProcessing = false;

function animate() {
    requestAnimationFrame(animate);
    controls.update();

    renderer.render(scene, camera);
    if (labelRenderer) labelRenderer.render(scene, camera);
}

function clearScene() {
    // Parar cualquier procesamiento
    isProcessing = false;

    // Limpiar objetos de la escena de forma recursiva y sus DOMs
    while(scene.children.length > 0){ 
        const obj = scene.children[0];
        recursiveDomRemove(obj);
        scene.remove(obj); 
    }
    nodes = [];
    links = [];
    
    // Re-añadir luces
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
    scene.add(ambientLight);
    const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1);
    hemiLight.position.set(0, 20, 0);
    scene.add(hemiLight);
    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
    dirLight.position.set(-10, 20, 10);
    scene.add(dirLight);
}

function recursiveDomRemove(obj) {
    if (obj.isCSS2DObject && obj.element) {
        obj.element.remove();
    }
    if (obj.children) {
        obj.children.forEach(c => recursiveDomRemove(c));
    }
}

async function loadTopology3D(subgrupo) {
    if (!subgrupo) return;
    
    $('#loading-overlay').removeClass('d-none');
    clearScene();

    try {
        const response = await fetch(`api_topology.php?action=get_topology&subgrupo=${encodeURIComponent(subgrupo)}`);
        const data = await response.json();

        if (data.success) {
            render3DGraph(data.hosts);
        }
    } catch (error) {
        console.error('Error loading 3D topology:', error);
    } finally {
        $('#loading-overlay').addClass('d-none');
    }
}

function createLabel(text, color = '#666', type = 'Server') {
    const div = document.createElement('div');
    div.className = 'label-container';
    
    // Icono según tipo
    const iconUrl = getIconForType(type, text);
    
    div.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; pointer-events: none;">
            <img src="${iconUrl}" style="width: 24px; height: 24px; margin-bottom: 2px;">
            <div style="
                font-size: 10px; 
                padding: 2px 6px; 
                background: white; 
                border: 1.5px solid ${color}; 
                border-radius: 8px; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.15);
                white-space: nowrap;
                color: ${color};
                font-weight: bold;
            ">${text}</div>
        </div>
    `;
    
    const obj = new THREE.CSS2DObject(div);
    obj.position.set(0, 10, 0); // Un poco arriba del nodo
    return obj;
}

function getIconForType(type, name) {
    name = name.toLowerCase();
    if (name.includes('switch')) return 'https://img.icons8.com/color/48/switch.png';
    if (name.includes('router')) return 'https://img.icons8.com/color/48/router.png';
    if (name.includes('firewall')) return 'https://img.icons8.com/color/48/firewall.png';
    return 'https://img.icons8.com/color/48/server.png';
}

function render3DGraph(hosts) {
    // Hub Central
    const hub = new THREE.Mesh(
        new THREE.SphereGeometry(15, 32, 32),
        new THREE.MeshPhongMaterial({ color: 0x4B79A1, transparent: true, opacity: 0.9 })
    );
    hub.position.set(0, 0, 0);
    hub.add(createLabel('RED CORE', '#4B79A1', 'Hub'));
    
    hub.userData = { type: 'hub', hosts: hosts };
    hub.onClick = () => expandHub(hub);

    scene.add(hub);
    nodes.push(hub);

    controls.reset();
}

let distanceMultiplier = 1.0;
let currentLayout = 'sphere';

function expandHub(hubMesh) {
    if (isProcessing) return;
    isProcessing = true;

    const hosts = hubMesh.userData.hosts;
    if (!hosts || hosts.length === 0) { isProcessing = false; return; }

    const existing = hubMesh.children.filter(c => c.userData && c.userData.type !== 'label');
    if (existing.length > 0) {
        existing.forEach(c => {
            recursiveDomRemove(c);
            nodes = nodes.filter(n => n !== c);
            hubMesh.remove(c);
        });
        setTimeout(() => { isProcessing = false; }, 100);
        return;
    }

    // AUTO-REGULACIÓN: Calcular radio necesario según cantidad de equipos
    // Basado en el área superficial necesaria para que cada etiqueta (aprox 140x50px) no choque
    const minAreaPerNode = 12000; // Espacio vital por equipo
    const autoRadius = Math.sqrt((hosts.length * minAreaPerNode) / (4 * Math.PI));
    const radiusBase = Math.max(220, autoRadius) * distanceMultiplier; 
    
    hosts.forEach((h, i) => {
        let x, y, z;

        if (currentLayout === 'sphere') {
            const phi = Math.acos(-1 + (2 * i) / hosts.length);
            const theta = Math.sqrt(hosts.length * Math.PI) * phi;
            x = radiusBase * Math.cos(theta) * Math.sin(phi);
            y = radiusBase * Math.sin(theta) * Math.sin(phi);
            z = radiusBase * Math.cos(phi);
        } else if (currentLayout === 'spiral') {
            const theta = 0.4 * i;
            const r = radiusBase + (i * 5);
            x = r * Math.cos(theta);
            y = (i * 15) - (hosts.length * 7.5); // Propagación vertical
            z = r * Math.sin(theta);
        } else { // GRID
            const side = Math.ceil(Math.pow(hosts.length, 1/3));
            const gap = 120;
            const ix = i % side;
            const iy = Math.floor(i / side) % side;
            const iz = Math.floor(i / (side * side));
            x = (ix - side/2) * gap;
            y = (iy - side/2) * gap;
            z = (iz - side/2) * gap + radiusBase;
        }

        const nodeColor = h.status == 1 ? 0x2ecc71 : 0xe74c3c;
        const hostNode = new THREE.Mesh(
            new THREE.SphereGeometry(12, 32, 32),
            new THREE.MeshPhongMaterial({ color: nodeColor })
        );
        hostNode.position.set(x, y, z);
        // Guardar posición inicial sin el multiplicador aplicado para poder escalar en tiempo real
        const unscaledPos = new THREE.Vector3(x, y, z).divideScalar(distanceMultiplier);
        hostNode.userData = { ...h, type: 'host', initialPos: unscaledPos };
        
        hostNode.add(createLabel(h.name, h.status == 1 ? '#27ae60' : '#c0392b', h.inventory?.type || 'Server'));
        
        const lineGeo = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(-x, -y, -z), 
            new THREE.Vector3(0, 0, 0)     
        ]);
        const line = new THREE.Line(lineGeo, new THREE.LineBasicMaterial({ color: 0x95a5a6, transparent: true, opacity: 0.6 }));
        line.userData = { type: 'link' };
        hostNode.add(line);

        hostNode.onClick = () => expandHost(hostNode);
        
        hubMesh.add(hostNode);
        nodes.push(hostNode);
    });
    isProcessing = false;
}

let showDownPorts = false;

function expandHost(parentMesh) {
    if (isProcessing) return;
    isProcessing = true;

    const data = parentMesh.userData;
    if (!data.ports || data.ports.length === 0) { isProcessing = false; return; }

    const existingPorts = parentMesh.children.filter(c => c.userData && c.userData.type === 'port');
    if (existingPorts.length > 0) {
        existingPorts.forEach(p => {
            recursiveDomRemove(p);
            nodes = nodes.filter(n => n !== p);
            parentMesh.remove(p);
        });
        setTimeout(() => { isProcessing = false; }, 100);
        return;
    }

    // AUTO-REGULACIÓN DE PUERTOS
    const filteredPorts = data.ports.filter(p => showDownPorts || p.status == 1);
    const minAreaPerPort = 6000; // Los puertos son mas pequeños
    const autoPRadius = Math.sqrt((filteredPorts.length * minAreaPerPort) / (4 * Math.PI));
    const pRadius = Math.max(120, autoPRadius) * distanceMultiplier; 
    
    if (filteredPorts.length === 0 && !showDownPorts) {
        Swal.fire({ icon: 'info', title: 'Sin Puertos UP', text: 'Active "Ver Puertos Down".', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        isProcessing = false;
        return;
    }

    filteredPorts.forEach((p, i) => {
        // Distribución esférica más abierta
        const phi = Math.acos(-1 + (2 * i) / filteredPorts.length);
        const theta = Math.sqrt(filteredPorts.length * Math.PI) * phi;
        const px = pRadius * Math.cos(theta) * Math.sin(phi);
        const py = pRadius * Math.sin(theta) * Math.sin(phi);
        const pz = pRadius * Math.cos(phi);

        const pColor = p.status == 1 ? 0x2ecc71 : 0x9B59B6;
        
        // REQUERIMIENTO: Amarillo y Cuadrado si tiene equipo conectado
        let geometry;
        let pColorFinal = pColor; 
        
        if (p.connected_hostid) {
            geometry = new THREE.BoxGeometry(7, 7, 7);
            pColorFinal = 0xf1c40f; // Amarillo para equipos conectados
        } else {
            geometry = new THREE.SphereGeometry(4, 16, 16);
        }

        const portNode = new THREE.Mesh(
            geometry,
            new THREE.MeshBasicMaterial({ color: pColorFinal })
        );
        portNode.position.set(px, py, pz);
        // Guardar relación de posición
        const unscaledPPos = new THREE.Vector3(px, py, pz).divideScalar(distanceMultiplier);
        portNode.userData = { type: 'port', initialPos: unscaledPPos, ...p };
        
        portNode.add(createLabel(p.name, pColorFinal, 'Interface'));

        const lineGeo = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(-px, -py, -pz),
            new THREE.Vector3(0, 0, 0)
        ]);
        const line = new THREE.Line(lineGeo, new THREE.LineBasicMaterial({ color: pColorFinal, transparent: true, opacity: 0.5 }));
        line.userData = { type: 'link' };
        portNode.add(line);

        if (p.connected_hostid) {
            portNode.onClick = () => expandNeighbor(portNode);
        }

        parentMesh.add(portNode);
        nodes.push(portNode);
    });
    isProcessing = false;
}

function expandNeighbor(portMesh) {
    if (isProcessing) return;
    isProcessing = true;

    const connectedid = portMesh.userData.connected_hostid;
    const hub = nodes.find(n => n.userData && n.userData.type === 'hub');
    const targetHost = hub.userData.hosts.find(h => h.hostid == connectedid);

    if (!targetHost) { isProcessing = false; return; }

    // Toggle: Si ya se cargo, lo quitamos
    const existing = portMesh.children.find(c => c.userData && c.userData.type === 'host');
    if (existing) {
        recursiveDomRemove(existing);
        nodes = nodes.filter(n => n !== existing);
        portMesh.remove(existing);
        
        // VOLVER A CUADRADO (porque ya no esta expandido)
        portMesh.geometry = new THREE.BoxGeometry(6, 6, 6);
        
        setTimeout(() => { isProcessing = false; }, 100);
        return;
    }

    // CONVERTIR EN CIRCULO AMARILLO AL EXPANDIR (Mantener color de conexion)
    portMesh.geometry = new THREE.SphereGeometry(4, 16, 16);
    portMesh.material.color.setHex(0xf1c40f);
    
    // Actualizar etiqueta del puerto a amarillo
    const label = portMesh.children.find(c => c.isCSS2DObject);
    if(label) label.element.querySelector('div').style.borderColor = '#f1c40f';
    if(label) label.element.querySelector('div').style.color = '#f1c40f';

    const nRadius = 90 * distanceMultiplier; // Aplicar multiplicador global
    const nColor = targetHost.status == 1 ? 0x2ecc71 : 0xe74c3c;
    const nNode = new THREE.Mesh(
        new THREE.SphereGeometry(10, 32, 32),
        new THREE.MeshPhongMaterial({ color: nColor })
    );
    nNode.position.set(0, 0, nRadius); 
    const unscaledNPos = new THREE.Vector3(0, 0, nRadius).divideScalar(distanceMultiplier);
    nNode.userData = { ...targetHost, type: 'host', initialPos: unscaledNPos };
    nNode.add(createLabel(targetHost.name, nColor, targetHost.inventory?.type || 'Server'));

    const lineGeo = new THREE.BufferGeometry().setFromPoints([
        new THREE.Vector3(0, 0, -nRadius),
        new THREE.Vector3(0, 0, 0)
    ]);
    const line = new THREE.Line(lineGeo, new THREE.LineBasicMaterial({ color: 0x95a5a6, transparent: true, opacity: 0.5 }));
    line.userData = { type: 'link' };
    nNode.add(line);
    nNode.onClick = () => expandHost(nNode);

    portMesh.add(nNode);
    nodes.push(nNode);
    isProcessing = false;
}

function removeRecursively(parent, child) {
    // Parar si estamos procesando
    if (isProcessing) return;
    isProcessing = true;

    // Limpiar DOM de este objeto y sus hijos
    recursiveDomRemove(child);

    if (child.children) {
        const grandChildren = [...child.children];
        grandChildren.forEach(gc => {
            if (gc.userData && (gc.userData.type === 'port' || gc.userData.type === 'host')) {
                removeRecursively(child, gc);
            }
        });
    }
    
    nodes = nodes.filter(n => n !== child);
    parent.remove(child);

    setTimeout(() => { isProcessing = false; }, 100);
}

function showNodeInfo(data) {
    $('#node-name').text(data.name);
    const ip = data.interfaces && data.interfaces[0] ? data.interfaces[0].ip : 'N/A';
    $('#node-ip').text(ip);
    
    let details = `<span class="badge badge-${data.status == 1 ? 'success' : 'danger'}">${data.status == 1 ? 'Online' : 'Offline'}</span>`;
    if (data.ports && data.ports.length > 0) {
        details += `<br><small class="text-white mt-2">Puertos Detectados: ${data.ports.length}</small>`;
    }
    
    $('#node-details').html(details);
    $('#node-info-panel').removeClass('d-none').addClass('animate__animated animate__fadeInUp');
}

function hideNodeInfo() {
    $('#node-info-panel').addClass('d-none');
}

// Select2 y Eventos
$(document).ready(function() {
    init3D();

    $('.select2bs4').select2({ theme: 'bootstrap4' });

    // Cargar grupos
    fetch('api_topology.php?action=get_groups')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.result) {
                let options = '<option value="">Seleccione un Grupo</option>';
                data.result.forEach(g => {
                    options += `<option value="${g.name}">${g.name}</option>`;
                });
                $('#subgrupo-select').html(options);
            }
        });

    $('#subgrupo-select').on('change', function() {
        loadTopology3D($(this).val());
    });

    $('#refresh-3d-btn').on('click', function() {
        loadTopology3D($('#subgrupo-select').val());
    });

    $('#toggle-down-ports-3d').on('click', function() {
        showDownPorts = !showDownPorts;
        $(this).toggleClass('btn-outline-info btn-info');
        const icon = $(this).find('i');
        if (showDownPorts) {
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        } else {
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        }
        
        Swal.fire({ 
            icon: 'success', 
            title: 'Filtro Actualizado', 
            text: showDownPorts ? 'Mostrando todos los puertos' : 'Ocultando puertos DOWN', 
            toast: true, 
            position: 'top-end', 
            showConfirmButton: false, 
            timer: 2000 
        });
    });

    $('#layout-3d-select').on('change', function() {
        currentLayout = $(this).val();
        loadTopology3D($('#subgrupo-select').val());
        
        Swal.fire({ 
            icon: 'success', 
            title: 'Layout Cambiado', 
            text: 'Cambiando a vista: ' + currentLayout, 
            toast: true, 
            position: 'top-end', 
            showConfirmButton: false, 
            timer: 1500 
        });
    });

    $('#search-node-3d').on('input', function() {
        const term = $(this).val().toLowerCase();
        if(!term) return;

        const match = nodes.find(n => n.userData && n.userData.name && n.userData.name.toLowerCase().includes(term));
        if (match) {
            const targetPos = new THREE.Vector3();
            match.getWorldPosition(targetPos);
            controls.target.copy(targetPos);
            controls.update();

            // Resaltar momentaneamente
            showNodeInfo(match.userData);
        }
    });

    $('#distance-range-3d').on('input', function() {
        distanceMultiplier = parseFloat($(this).val());
        $('#distance-val').text(distanceMultiplier.toFixed(1));
        
        updateLayoutPositions();
    });

    window.addEventListener('resize', () => {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    });
});

function updateLayoutPositions() {
    nodes.forEach(node => {
        if (node.userData && node.userData.initialPos) {
            const newPos = node.userData.initialPos.clone().multiplyScalar(distanceMultiplier);
            node.position.copy(newPos);
            
            // Actualizar la línea de conexión (vínculo al padre)
            const link = node.children.find(c => c.userData && c.userData.type === 'link');
            if (link) {
                const points = [
                    new THREE.Vector3(-newPos.x, -newPos.y, -newPos.z),
                    new THREE.Vector3(0, 0, 0)
                ];
                link.geometry.setFromPoints(points);
            }
        }
    });
}
