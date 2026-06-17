<?php
/**
 * Datacenter Floor Plan 3D Viewer
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$room_id = (int)($_GET['room_id'] ?? $_GET['id'] ?? 0);
$pdo = getPDO();

// Get room details
$stmt = $pdo->prepare("SELECT * FROM dc_rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    die("Cuarto no encontrado");
}

$tile_size = 0.6; // each tile is 0.6m x 0.6m
$width_m = (float)($room['width_meters'] ?? 6.0);
$length_m = (float)($room['length_meters'] ?? 6.0);

$tiles_x = floor($width_m / $tile_size);
$tiles_y = floor($length_m / $tile_size);

// Get racks in this room
$stmt = $pdo->prepare("SELECT id, name, grid_x, grid_y, width_tiles, depth_tiles, total_u, rotation, z_index FROM dc_racks WHERE room_id = ?");
$stmt->execute([$room_id]);
$racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get layers
$stmt = $pdo->query("SELECT * FROM dc_floor_layers ORDER BY z_index ASC");
$layers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get items in this room
$stmt = $pdo->prepare("SELECT * FROM dc_floor_items WHERE room_id = ?");
$stmt->execute([$room_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista 3D: <?php echo htmlspecialchars($room['name']); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #0f172a;
            font-family: 'Outfit', sans-serif;
            color: #f1f5f9;
        }

        #canvas-container {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        /* UI Overlays */
        .ui-overlay {
            position: absolute;
            z-index: 10;
            pointer-events: none;
        }

        .interactive {
            pointer-events: auto;
        }

        .header-panel {
            top: 20px;
            left: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .header-title h4 {
            margin: 0;
            font-weight: 800;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #38bdf8, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-title p {
            margin: 5px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .control-panel {
            bottom: 20px;
            left: 20px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            width: 280px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .control-panel h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #38bdf8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .control-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
            color: #cbd5e1;
        }

        .control-item i {
            width: 20px;
            margin-right: 10px;
            color: #38bdf8;
            text-align: center;
        }

        .legend-panel {
            bottom: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            width: 240px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .legend-panel h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #38bdf8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            font-size: 12px;
            color: #cbd5e1;
        }

        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            margin-right: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Tooltip style */
        #tooltip {
            position: absolute;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid #38bdf8;
            color: #f1f5f9;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            pointer-events: none;
            display: none;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>

    <div id="tooltip"></div>

    <!-- 3D Canvas Container -->
    <div id="canvas-container"></div>

    <!-- UI Overlay Panels -->
    <div class="ui-overlay header-panel w-95 mx-auto">
        <div class="header-title">
            <h4><i class="fas fa-cube mr-2"></i> Vista Datacenter 3D: <?php echo htmlspecialchars($room['name']); ?></h4>
            <p>
                Dimensiones: <?php echo $width_m; ?>m x <?php echo $length_m; ?>m | 
                Baldosas: <?php echo $tiles_x; ?> x <?php echo $tiles_y; ?> | 
                Racks: <?php echo count($racks); ?> | 
                Equipos: <?php echo count($items); ?>
            </p>
        </div>
        <div class="interactive">
            <button onclick="resetCamera()" class="btn btn-outline-info btn-sm mr-2" title="Restablecer Vista">
                <i class="fas fa-sync"></i> Vista Inicial
            </button>
            <a href="floor_plan.php?id=<?php echo $room_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-th mr-1"></i> Volver a Plano 2D
            </a>
        </div>
    </div>

    <div class="ui-overlay control-panel interactive">
        <h5>Controles de Cámara</h5>
        <div class="control-item">
            <i class="fas fa-mouse-pointer"></i> Click Izquierdo + Arrastrar: Rotar cámara
        </div>
        <div class="control-item">
            <i class="fas fa-arrows-alt"></i> Click Derecho + Arrastrar: Desplazar (Pan)
        </div>
        <div class="control-item">
            <i class="fas fa-search-plus"></i> Rueda del Mouse: Acercar / Alejar (Zoom)
        </div>
        <div class="control-item">
            <i class="fas fa-info-circle"></i> Pasa el mouse sobre un objeto para ver detalles
        </div>

        <h5 class="mt-3">Interruptor de Luces</h5>
        <div class="control-item">
            <label class="d-flex align-items-center m-0" style="cursor: pointer; user-select: none;">
                <input type="checkbox" id="toggle-ambient" checked class="mr-2"> Luz Ambiental
            </label>
        </div>
        <div class="control-item">
            <label class="d-flex align-items-center m-0" style="cursor: pointer; user-select: none;">
                <input type="checkbox" id="toggle-dir" checked class="mr-2"> Focos de Techo (Techo)
            </label>
        </div>
        <div class="control-item">
            <label class="d-flex align-items-center m-0" style="cursor: pointer; user-select: none;">
                <input type="checkbox" id="toggle-neon" checked class="mr-2"> Luces Neón (Pasillos)
            </label>
        </div>
    </div>

    <div class="ui-overlay legend-panel">
        <h5>Leyenda de Equipos</h5>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #10b981;"></div>
            <span>Racks</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #0ea5e9;"></div>
            <span>Aire Acondicionado (AACC)</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #f59e0b;"></div>
            <span>UPS</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #8b5cf6;"></div>
            <span>Rampa</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #475569;"></div>
            <span>Cámaras</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #ea580c;"></div>
            <span>Puerta</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #eab308; border: 1px dashed #ca8a04;"></div>
            <span>Escalerillas / Cable Trays</span>
        </div>
    </div>

    <!-- Three.js + OrbitControls -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

    <script>
        // Room configuration parameters
        const roomWidth = <?php echo $width_m; ?>;
        const roomDepth = <?php echo $length_m; ?>;
        const tilesX = <?php echo $tiles_x; ?>;
        const tilesY = <?php echo $tiles_y; ?>;
        const tileSize = <?php echo $tile_size; ?>; // 0.6m
        const floorHeight = <?php echo (float)($room['floor_height_meters'] ?? 0.0); ?>;

        // Scene variables
        let scene, camera, renderer, controls;
        let raycaster, mouse;
        let interactiveObjects = [];
        let ambientLight, dirLight, blueLight, greenLight;

        // Data arrays from PHP
        const racksData = <?php echo json_encode($racks); ?>;
        const itemsData = <?php echo json_encode($items); ?>;

        init();
        animate();

        function init() {
            // 1. Create Scene
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x0f172a);
            scene.fog = new THREE.FogExp2(0x0f172a, 0.015);

            // 2. Camera Setup
            camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
            resetCameraPosition();

            // 3. Renderer Setup
            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            document.getElementById('canvas-container').appendChild(renderer.domElement);

            // 4. OrbitControls
            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.maxPolarAngle = Math.PI / 2 - 0.05; // Prevent camera going below floor
            controls.minDistance = 2;
            controls.maxDistance = 100;
            controls.target.set(roomWidth / 2, floorHeight, roomDepth / 2);

            // 5. Lights
            ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
            scene.add(ambientLight);

            dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
            dirLight.position.set(roomWidth / 2, 20, roomDepth / 2);
            dirLight.castShadow = true;
            dirLight.shadow.mapSize.width = 2048;
            dirLight.shadow.mapSize.height = 2048;
            dirLight.shadow.camera.near = 0.5;
            dirLight.shadow.camera.far = 40;
            const d = 25;
            dirLight.shadow.camera.left = -d;
            dirLight.shadow.camera.right = d;
            dirLight.shadow.camera.top = d;
            dirLight.shadow.camera.bottom = -d;
            scene.add(dirLight);

            // Subtle colored point lights for realistic datacenter aesthetic
            blueLight = new THREE.PointLight(0x0ea5e9, 2.0, 25);
            blueLight.position.set(2, floorHeight + 4, 2);
            scene.add(blueLight);

            greenLight = new THREE.PointLight(0x10b981, 2.0, 25);
            greenLight.position.set(roomWidth - 2, floorHeight + 4, roomDepth - 2);
            scene.add(greenLight);

            // 6. Floor Grid (Fake floor tile system)
            createFloor();

            // 7. Render Datacenter Equipment
            renderRacks();
            renderItems();

            // 8. Raycasting for hover tooltip
            raycaster = new THREE.Raycaster();
            mouse = new THREE.Vector2();

            window.addEventListener('resize', onWindowResize, false);
            window.addEventListener('mousemove', onMouseMove, false);

            // 9. Lighting Controls Event Listeners
            document.getElementById('toggle-ambient').addEventListener('change', function(e) {
                ambientLight.visible = e.target.checked;
            });
            document.getElementById('toggle-dir').addEventListener('change', function(e) {
                dirLight.visible = e.target.checked;
            });
            document.getElementById('toggle-neon').addEventListener('change', function(e) {
                blueLight.visible = e.target.checked;
                greenLight.visible = e.target.checked;
            });
        }

        function resetCameraPosition() {
            // Position camera looking down from an angle
            camera.position.set(roomWidth / 2, Math.max(roomWidth, roomDepth) * 1.2, roomDepth * 1.5);
        }

        function resetCamera() {
            resetCameraPosition();
            if (controls) {
                controls.target.set(roomWidth / 2, floorHeight, roomDepth / 2);
                controls.update();
            }
        }

        function createFloor() {
            // Tile grid lines (matching tiles_x and tiles_y)
            const gridHelper = new THREE.GridHelper(
                Math.max(roomWidth, roomDepth), 
                Math.max(tilesX, tilesY), 
                0x475569, 
                0x334155
            );

            if (floorHeight > 0) {
                // Concrete base at y = -0.005 (slightly below y=0 to prevent z-fighting with the grid)
                const baseGeo = new THREE.PlaneGeometry(roomWidth, roomDepth);
                const baseMat = new THREE.MeshStandardMaterial({
                    color: 0x0f172a, // Darker concrete color
                    roughness: 0.9,
                    metalness: 0.1
                });
                const concreteBase = new THREE.Mesh(baseGeo, baseMat);
                concreteBase.rotation.x = -Math.PI / 2;
                concreteBase.position.set(roomWidth / 2, -0.005, roomDepth / 2);
                concreteBase.receiveShadow = true;
                scene.add(concreteBase);

                // Raised floor slab at y = floorHeight
                const raisedGeo = new THREE.BoxGeometry(roomWidth, floorHeight, roomDepth);
                const raisedMat = new THREE.MeshStandardMaterial({
                    color: 0xf8fafc, // Off-white color requested by the user
                    roughness: 0.5,
                    metalness: 0.2,
                    transparent: true,
                    opacity: 0.9 // Higher opacity for a clear white surface
                });
                const raisedFloor = new THREE.Mesh(raisedGeo, raisedMat);
                raisedFloor.position.set(roomWidth / 2, floorHeight / 2, roomDepth / 2);
                raisedFloor.receiveShadow = true;
                scene.add(raisedFloor);

                // Tile divisions grid on top of the raised floor
                const topGrid = new THREE.GridHelper(
                    Math.max(roomWidth, roomDepth), 
                    Math.max(tilesX, tilesY), 
                    0x94a3b8, // Slate gray divisions
                    0xcbd5e1  // Light slate gray divisions
                );
                topGrid.position.set(roomWidth / 2, floorHeight + 0.002, roomDepth / 2);
                scene.add(topGrid);
            } else {
                // Base floor plane at y = -0.005 (slightly below y=0 to prevent z-fighting with the grid)
                const floorGeo = new THREE.PlaneGeometry(roomWidth, roomDepth);
                const floorMat = new THREE.MeshStandardMaterial({
                    color: 0xf8fafc, // Render a white base floor if floorHeight is 0 so divisions are always visible
                    roughness: 0.5,
                    metalness: 0.2
                });
                const floor = new THREE.Mesh(floorGeo, floorMat);
                floor.rotation.x = -Math.PI / 2;
                floor.position.set(roomWidth / 2, -0.005, roomDepth / 2);
                floor.receiveShadow = true;
                scene.add(floor);
            }

            // Always place the grid helper on the lowest point (exactly at y = 0)
            gridHelper.position.set(roomWidth / 2, 0, roomDepth / 2);
            scene.add(gridHelper);

            // Add room boundary walls (transparent/wireframe style to look high-tech)
            const wallMat = new THREE.MeshBasicMaterial({
                color: 0x38bdf8,
                wireframe: true,
                transparent: true,
                opacity: 0.15
            });

            // Back wall
            const wallBackGeo = new THREE.PlaneGeometry(roomWidth, 3);
            const wallBack = new THREE.Mesh(wallBackGeo, wallMat);
            wallBack.position.set(roomWidth / 2, floorHeight + 1.5, 0);
            scene.add(wallBack);

            // Left wall
            const wallLeftGeo = new THREE.PlaneGeometry(roomDepth, 3);
            const wallLeft = new THREE.Mesh(wallLeftGeo, wallMat);
            wallLeft.rotation.y = Math.PI / 2;
            wallLeft.position.set(0, floorHeight + 1.5, roomDepth / 2);
            scene.add(wallLeft);
        }

        function renderRacks() {
            // Rack geometry (standard rack is 0.6m wide, 1.2m deep, 2.0m high)
            // In grid coordinate units: width = 1 tile, depth = 2 tiles, height = 2 meters
            racksData.forEach(r => {
                const rx = parseFloat(r.grid_x);
                const ry = parseFloat(r.grid_y);
                const rot = parseInt(r.rotation) || 0;
                
                const w = parseFloat(r.width_tiles || 1) * tileSize;
                const d = parseFloat(r.depth_tiles || 2) * tileSize;
                const h = 2.0; // standard height: 2 meters

                // Create group for rack + indicators
                const rackGroup = new THREE.Group();

                // Main rack body box
                const rackGeo = new THREE.BoxGeometry(w - 0.04, h, d - 0.04);
                
                // Beautiful multi-material setup: green sides, black front
                const greenMat = new THREE.MeshStandardMaterial({ color: 0x10b981, roughness: 0.5, metalness: 0.5 });
                const blackMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.9, metalness: 0.1 });
                const frontIndicatorMat = new THREE.MeshStandardMaterial({ color: 0x000000, roughness: 0.2 });

                const materials = [
                    greenMat, // Right
                    greenMat, // Left
                    greenMat, // Top
                    greenMat, // Bottom
                    blackMat, // Back
                    frontIndicatorMat // Front (indicates direction)
                ];

                const rackMesh = new THREE.Mesh(rackGeo, materials);
                rackMesh.position.y = h / 2;
                rackMesh.castShadow = true;
                rackMesh.receiveShadow = true;
                rackGroup.add(rackMesh);

                // Add name text badge label on top of rack
                const canvas = document.createElement('canvas');
                canvas.width = 256;
                canvas.height = 64;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#0f172a';
                ctx.fillRect(0, 0, 256, 64);
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 24px Outfit';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(r.name, 128, 32);

                const texture = new THREE.CanvasTexture(canvas);
                const textMat = new THREE.MeshBasicMaterial({ map: texture, transparent: true });
                const textGeo = new THREE.PlaneGeometry(w * 0.9, 0.2);
                const textMesh = new THREE.Mesh(textGeo, textMat);
                textMesh.position.set(0, h + 0.12, 0);
                textMesh.rotation.y = 0;
                rackGroup.add(textMesh);

                // Position the group
                // Note: database coordinates are top-left of the tile grid, Three.js coordinates are center-based
                // We add half width and half depth to center
                const posX = rx * tileSize + w / 2;
                const posZ = ry * tileSize + d / 2;
                rackGroup.position.set(posX, floorHeight, posZ);

                // Apply rotation
                rackGroup.rotation.y = -THREE.MathUtils.degToRad(rot);

                // Save reference metadata on group for raycasting tooltip
                rackMesh.userData = {
                    name: r.name,
                    type: 'Rack',
                    details: `Altura: ${r.total_u} U | Dim: ${w.toFixed(1)}m x ${d.toFixed(1)}m | Rot: ${rot}°`
                };
                interactiveObjects.push(rackMesh);

                scene.add(rackGroup);
            });
        }

        function renderItems() {
            itemsData.forEach(i => {
                const rx = parseFloat(i.grid_x);
                const ry = parseFloat(i.grid_y);
                const wt = parseFloat(i.width_tiles || 1);
                const dt = parseFloat(i.depth_tiles || 1);
                const rot = parseInt(i.rotation) || 0;
                const type = i.type;

                const w = wt * tileSize;
                const d = dt * tileSize;
                let h = parseFloat(i.height_meters) || 1.8; // Fallback default height

                const posX = rx * tileSize + w / 2;
                const posZ = ry * tileSize + d / 2;

                let mesh;

                if (type === 'aacc') {
                    // Air Conditioner (Blue taller cabinet)
                    h = 2.0;
                    const geo = new THREE.BoxGeometry(w - 0.02, h, d - 0.02);
                    const blueMat = new THREE.MeshStandardMaterial({ color: 0x0ea5e9, roughness: 0.3, metalness: 0.7 });
                    const darkMat = new THREE.MeshStandardMaterial({ color: 0x0f172a });
                    const materials = [blueMat, blueMat, blueMat, blueMat, darkMat, blueMat]; // Black back/front indicator
                    mesh = new THREE.Mesh(geo, materials);
                    mesh.position.set(posX, floorHeight + h / 2, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);
                    
                } else if (type === 'ups') {
                    // UPS (Orange block cabinet)
                    h = 1.8;
                    const geo = new THREE.BoxGeometry(w - 0.02, h, d - 0.02);
                    const orangeMat = new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.4, metalness: 0.6 });
                    const darkMat = new THREE.MeshStandardMaterial({ color: 0x0f172a });
                    const materials = [orangeMat, orangeMat, orangeMat, orangeMat, darkMat, orangeMat];
                    mesh = new THREE.Mesh(geo, materials);
                    mesh.position.set(posX, floorHeight + h / 2, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);

                } else if (type === 'rampa') {
                    // Ramp (Slanted wedge geometry)
                    h = floorHeight > 0 ? floorHeight : 0.3; // Connect ground to raised floor
                    
                    // Create a wedge shape
                    const shape = new THREE.Shape();
                    shape.moveTo(-w/2, 0);
                    shape.lineTo(w/2, 0);
                    shape.lineTo(w/2, h);
                    shape.lineTo(-w/2, 0); // Triangle

                    const extrudeSettings = {
                        depth: d,
                        bevelEnabled: false
                    };

                    const geo = new THREE.ExtrudeGeometry(shape, extrudeSettings);
                    geo.center(); // Center geometry
                    
                    const purpleMat = new THREE.MeshStandardMaterial({ color: 0x8b5cf6, roughness: 0.6 });
                    mesh = new THREE.Mesh(geo, purpleMat);
                    // Adjust position: centers the ramp between ground and floor height
                    mesh.position.set(posX, h / 2, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);

                } else if (type === 'camaras') {
                    // Camera (Sphere mounted high up on a wall/stand)
                    h = 2.4; // Ceiling height
                    const group = new THREE.Group();
                    
                    // Small stand/pole
                    const poleGeo = new THREE.CylinderGeometry(0.02, 0.02, 0.4);
                    const darkMat = new THREE.MeshStandardMaterial({ color: 0x475569 });
                    const pole = new THREE.Mesh(poleGeo, darkMat);
                    pole.position.y = h - 0.2;
                    group.add(pole);

                    // Camera body (sphere/box)
                    const camGeo = new THREE.SphereGeometry(0.1, 16, 16);
                    const camMat = new THREE.MeshStandardMaterial({ color: 0x0ea5e9, roughness: 0.2 });
                    const cameraSphere = new THREE.Mesh(camGeo, camMat);
                    cameraSphere.position.y = h - 0.4;
                    group.add(cameraSphere);

                    // Dummy main mesh for raycasting bounding box
                    const dummyGeo = new THREE.BoxGeometry(w, h, d);
                    const dummyMat = new THREE.MeshBasicMaterial({ transparent: true, opacity: 0.0 });
                    mesh = new THREE.Mesh(dummyGeo, dummyMat);
                    mesh.position.set(posX, floorHeight + h / 2, posZ);
                    group.position.set(0, 0, 0);
                    mesh.add(group);
                    
                } else if (type === 'puerta') {
                    // Door (Thin slate standing panel)
                    h = 2.1;
                    const doorThickness = 0.08;
                    const geo = new THREE.BoxGeometry(w - 0.02, h, doorThickness);
                    const woodMat = new THREE.MeshStandardMaterial({ color: 0xea580c, roughness: 0.7 });
                    mesh = new THREE.Mesh(geo, woodMat);
                    mesh.position.set(posX, h / 2, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);

                } else if (type.includes('escalerilla')) {
                    // Cable Tray heights relative to the raised floor:
                    // Fibra (Yellow) = 2.3m, Datos (Blue/Cobre) = 2.45m, Energía (Red) = 2.60m
                    let altitude = 2.3; // Default (fibra)
                    let trayColor = 0xeab308; // Fibra (Yellow)

                    if (type === 'escalerilla_cobre') {
                        altitude = 2.45;
                        trayColor = 0x0284c7; // Cobre (Blue)
                    } else if (type === 'escalerilla_energia') {
                        altitude = 2.60;
                        trayColor = 0xdc2626; // Energía (Red)
                    }

                    const trayHeight = 0.06;
                    const geo = new THREE.BoxGeometry(w, trayHeight, d);

                    const trayMat = new THREE.MeshStandardMaterial({ 
                        color: trayColor, 
                        wireframe: true, 
                        transparent: true, 
                        opacity: 0.9 
                    });
                    mesh = new THREE.Mesh(geo, trayMat);
                    mesh.position.set(posX, floorHeight + altitude, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);

                } else if (type.includes('piso')) {
                    // Perforated Floor Grid (Very flat on grid)
                    h = 0.02;
                    const geo = new THREE.BoxGeometry(w - 0.01, h, d - 0.01);
                    const tileMat = new THREE.MeshStandardMaterial({ 
                        color: 0x64748b, 
                        roughness: 0.9, 
                        wireframe: true 
                    });
                    mesh = new THREE.Mesh(geo, tileMat);
                    mesh.position.set(posX, floorHeight + h / 2, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);
                }

                if (mesh) {
                    mesh.castShadow = true;
                    mesh.receiveShadow = true;
                    
                    // Set metadata
                    mesh.userData = {
                        name: i.name,
                        type: type.toUpperCase(),
                        details: `Dim: ${w.toFixed(1)}m x ${d.toFixed(1)}m | Capa: ${i.layer_name || 'Sin Capa'}`
                    };

                    interactiveObjects.push(mesh);
                    scene.add(mesh);
                }
            });
        }

        function onWindowResize() {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        }

        function onMouseMove(event) {
            event.preventDefault();
            // Calculate normalized mouse coordinates
            mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
            mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;

            // Handle raycast hovering tooltip
            raycaster.setFromCamera(mouse, camera);
            const intersects = raycaster.intersectObjects(interactiveObjects);

            const tooltip = document.getElementById('tooltip');
            if (intersects.length > 0) {
                const obj = intersects[0].object;
                const data = obj.userData || obj.parent.userData;
                
                if (data && data.name) {
                    tooltip.innerHTML = `
                        <strong style="color: #38bdf8;">${data.name}</strong><br>
                        <small style="color: #94a3b8;">Tipo: ${data.type}</small><br>
                        <small style="color: #cbd5e1;">${data.details}</small>
                    `;
                    tooltip.style.left = (event.clientX + 15) + 'px';
                    tooltip.style.top = (event.clientY + 15) + 'px';
                    tooltip.style.display = 'block';
                    document.body.style.cursor = 'pointer';
                }
            } else {
                tooltip.style.display = 'none';
                document.body.style.cursor = 'default';
            }
        }

        function animate() {
            requestAnimationFrame(animate);
            if (controls) controls.update();
            renderer.render(scene, camera);
        }
    </script>
</body>
</html>
