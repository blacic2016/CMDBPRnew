function initHeatmap() {
    // Generate grids for floors 5, 6, 7
    [5, 6, 7].forEach(floor => {
        const grid = document.getElementById(`grid-${floor}`);
        grid.innerHTML = '';

        // 10x10 Grid
        const cells = [];
        for (let i = 0; i < 100; i++) {
            const cell = document.createElement('div');
            cell.className = 'cell';
            grid.appendChild(cell);
            cells.push(cell);
        }

        // Calculate density for this floor
        const floorAps = appData.aps.filter(ap => extractFloor(ap.name) === floor);
        const floorClients = appData.clients.filter(c => {
            const ap = appData.aps.find(a => a.ip === c.apIP);
            return ap && extractFloor(ap.name) === floor;
        });

        // Map APs to random positions on the grid (simulating layout)
        floorAps.forEach((ap, idx) => {
            const apPos = (idx * 13) % 100; // Pseudo-random fixed position
            const clientCount = floorClients.filter(c => c.apIP === ap.ip).length;

            // Spread "heat" to neighbors
            const radius = 2;
            const x = apPos % 10;
            const y = Math.floor(apPos / 10);

            for (let dy = -radius; dy <= radius; dy++) {
                for (let dx = -radius; dx <= radius; dx++) {
                    const nx = x + dx;
                    const ny = y + dy;
                    if (nx >= 0 && nx < 10 && ny >= 0 && ny < 10) {
                        const nIdx = ny * 10 + nx;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        const intensity = Math.max(0, (1 - dist / radius) * clientCount * 0.2);

                        // Apply color based on intensity
                        const currentOpacity = parseFloat(cells[nIdx].style.backgroundColor.split(',').pop()) || 0;
                        const newIntensity = Math.min(0.8, currentOpacity + intensity);
                        cells[nIdx].style.backgroundColor = `rgba(248, 81, 73, ${newIntensity})`; // Red heat
                    }
                }
            }
        });
    });
}

function extractFloor(name) {
    if (name.includes('P05')) return 5;
    if (name.includes('P06')) return 6;
    if (name.includes('P07')) return 7;
    return 0;
}
