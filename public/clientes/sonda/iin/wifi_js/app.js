document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const globalSearch = document.getElementById('global-search');

    // Tab switching logic
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.getAttribute('data-tab');

            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === targetTab) {
                    content.classList.add('active');
                }
            });

            // Trigger specific tab initialization if needed
            if (targetTab === 'topology') initTopology();
            if (targetTab === 'topo-2d') initTopology2D();
            if (targetTab === 'trajectory') initTrajectory();
            if (targetTab === 'ap-analysis') initApAnalysis();
            if (targetTab === 'heatmap') initHeatmap();
            if (targetTab === 'floor-config') initFloorConfig();
        });
    });

    // Sidebar Sub-tab switching logic
    document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-sidebar-tab');
            
            // Toggle active button
            btn.parentElement.querySelectorAll('.sidebar-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Toggle active content
            btn.parentElement.parentElement.querySelectorAll('.sidebar-tab-content').forEach(content => {
                content.classList.remove('active');
                if (content.id === `sidebar-tab-${target}`) {
                    content.classList.add('active');
                }
            });
        });
    });

    // Global search logic (autocomplete)
    globalSearch.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        if (query.length < 2) return;

        // Search in local data (stored in window object after loading)
        // Implementation for search results visualization here
    });

    // Theme toggle logic
    const themeToggle = document.getElementById('theme-toggle');
    
    // Load theme from localStorage
    const savedThemeIndex = localStorage.getItem('selectedThemeIndex');
    if (savedThemeIndex === '1') {
        document.body.classList.add('dark-theme');
    } else {
        document.body.classList.remove('dark-theme');
    }

    themeToggle.textContent = document.body.classList.contains('dark-theme') ? '🌙' : '☀️';
    themeToggle.addEventListener('click', () => {
        const isDark = document.body.classList.toggle('dark-theme');
        themeToggle.textContent = isDark ? '🌙' : '☀️';
        localStorage.setItem('selectedThemeIndex', isDark ? '1' : '0');
        // Re-draw topology if active to update line colors
        if (document.getElementById('topology').classList.contains('active')) {
            initTopology();
        }
    });

    // Initialize Custom Multiselect
    setupCustomMultiselect();

    // Initialize sidebar resizing
    initSidebarResize();

    // Initial load
    loadData();
});

window.appData = {
    aps: [],
    clients: []
};

async function loadData() {
    try {
        const response = await fetch('wifi_php/api.php?action=topology');
        const result = await response.json();

        if (result.success) {
            window.appData.aps = result.aps;
            window.appData.clients = result.clients;

            // Update badges
            document.getElementById('ap-count-badge').textContent = `APs: ${result.aps.length}`;
            document.getElementById('client-count-badge').textContent = `Clientes: ${result.clients.length}`;

            // Populate AP Selectors
            const apSelector = document.getElementById('ap-selector');
            const trajApSelector = document.getElementById('traj-ap-selector');

            [apSelector, trajApSelector].forEach(sel => {
                if (!sel) return;
                
                // Extract currently selected values
                let selectedValues = [];
                if (sel.id === 'ap-selector') {
                    selectedValues = Array.from(sel.selectedOptions).map(opt => opt.value);
                } else {
                    selectedValues = [sel.value];
                }

                sel.innerHTML = sel.id === 'ap-selector' ? '<option value="ALL">TODOS</option>' : '<option value="">Cualquiera</option>';
                result.aps.forEach(ap => {
                    const opt = document.createElement('option');
                    opt.value = ap.ip;
                    opt.textContent = ap.name;
                    sel.appendChild(opt);
                });

                if (sel.id === 'ap-selector') {
                    if (selectedValues.length === 0 || selectedValues.includes('ALL')) {
                        const allOpt = Array.from(sel.options).find(o => o.value === 'ALL');
                        if (allOpt) allOpt.selected = true;
                    } else {
                        Array.from(sel.options).forEach(opt => {
                            if (selectedValues.includes(opt.value)) {
                                opt.selected = true;
                            }
                        });
                    }
                } else {
                    sel.value = selectedValues[0] || '';
                }
            });

            // Sync the custom multiselect UI
            if (typeof window.syncApMultiselect === 'function') {
                window.syncApMultiselect();
            }

            // Function to populate clients (extract for reuse)
            window.populateClientSelector = (apIp = '') => {
                const clientSelector = document.getElementById('client-selector');
                if (!clientSelector) return;
                clientSelector.innerHTML = '<option value="">Seleccione un cliente...</option>';

                let filteredClients = result.clients;
                if (apIp) {
                    filteredClients = result.clients.filter(c => c.apIP === apIp);
                }

                filteredClients.sort((a, b) => (a.name || '').localeCompare(b.name || '')).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.mac || c.key;
                    opt.textContent = `${c.name || 'Desconocido'} (${c.ip || 'N/A'})`;
                    clientSelector.appendChild(opt);
                });
            };

            populateClientSelector();

            // Initialize the active tab
            initTopology();
            initTrajectory();
            if (typeof initApAnalysis === 'function') initApAnalysis();
        }
    } catch (error) {
        console.error("Error loading data:", error);
    }
}

// Add event listener for topology filter
document.getElementById('ap-selector')?.addEventListener('change', () => {
    initTopology();
});

// Add event listener for trajectory AP filter
document.getElementById('traj-ap-selector')?.addEventListener('change', (e) => {
    if (typeof populateClientSelector === 'function') {
        populateClientSelector(e.target.value);
    }
});


// Modal management
function showModal(title, contentHtml) {
    const overlay = document.getElementById('modal-overlay');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');

    modalTitle.textContent = title;
    modalBody.innerHTML = contentHtml;
    overlay.classList.add('active');
}

document.getElementById('close-modal').addEventListener('click', () => {
    document.getElementById('modal-overlay').classList.remove('active');
});

// Helper functions for all tabs
function getSnrColor(snr) {
    if (typeof ZONES !== 'undefined') {
        const zone = ZONES.find(z => snr >= z.min);
        if (zone) return zone.color.replace(/0\.\d+\)/, '0.8)');
    }
    if (snr > 30) return '#3fb950'; // Optimal
    if (snr > 20) return '#d29922'; // Fair
    return '#f85149'; // Low
}

function setupCustomMultiselect() {
    const container = document.getElementById('ap-multiselect-container');
    const trigger = document.getElementById('ap-multiselect-trigger');
    if (!container || !trigger) return;
    
    const triggerText = container.querySelector('.trigger-text');
    const dropdown = document.getElementById('ap-multiselect-dropdown');
    const optionsContainer = document.getElementById('ap-multiselect-options');
    const apSelector = document.getElementById('ap-selector');
    const selectAllBtn = document.getElementById('ap-select-all');
    const clearAllBtn = document.getElementById('ap-clear-all');

    if (!optionsContainer || !apSelector) return;

    // Toggle dropdown
    trigger.onclick = (e) => {
        e.stopPropagation();
        container.classList.toggle('open');
    };

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            container.classList.remove('open');
        }
    });

    // Populate custom options from the hidden select
    function syncOptions() {
        optionsContainer.innerHTML = '';
        
        // Check if 'ALL' is selected
        const isAllSelected = Array.from(apSelector.options).some(opt => opt.value === 'ALL' && opt.selected);

        Array.from(apSelector.options).forEach(opt => {
            if (opt.value === 'ALL') return; // We handle ALL separately

            const item = document.createElement('div');
            item.className = 'multiselect-option';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = opt.selected && !isAllSelected;
            checkbox.id = `ap-opt-${opt.value}`;
            
            const label = document.createElement('label');
            label.htmlFor = checkbox.id;
            label.textContent = opt.textContent;

            item.appendChild(checkbox);
            item.appendChild(label);

            item.onclick = (e) => {
                e.stopPropagation();
                // Toggle checkbox if click wasn't on the checkbox itself
                if (e.target !== checkbox) {
                    checkbox.checked = !checkbox.checked;
                }
                opt.selected = checkbox.checked;
                
                // If any specific AP is checked, unselect 'ALL'
                const allOpt = Array.from(apSelector.options).find(o => o.value === 'ALL');
                if (allOpt) {
                    allOpt.selected = false;
                }
                
                // If no options are selected, select 'ALL'
                const anySelected = Array.from(apSelector.options).some(o => o.value !== 'ALL' && o.selected);
                if (!anySelected && allOpt) {
                    allOpt.selected = true;
                }
                
                updateTriggerText();
                apSelector.dispatchEvent(new Event('change'));
                syncOptions(); // refresh checks representation
            };

            optionsContainer.appendChild(item);
        });

        updateTriggerText();
    }

    function updateTriggerText() {
        const selected = Array.from(apSelector.selectedOptions).filter(opt => opt.value !== 'ALL');
        const isAllSelected = Array.from(apSelector.options).some(o => o.value === 'ALL' && o.selected);
        
        if (selected.length === 0 || isAllSelected) {
            triggerText.textContent = 'TODOS';
        } else if (selected.length === 1) {
            triggerText.textContent = selected[0].textContent;
        } else {
            triggerText.textContent = `${selected.length} seleccionados`;
        }
    }

    selectAllBtn.onclick = (e) => {
        e.stopPropagation();
        Array.from(apSelector.options).forEach(opt => {
            opt.selected = opt.value !== 'ALL';
        });
        syncOptions();
        apSelector.dispatchEvent(new Event('change'));
    };

    clearAllBtn.onclick = (e) => {
        e.stopPropagation();
        Array.from(apSelector.options).forEach(opt => {
            opt.selected = opt.value === 'ALL';
        });
        syncOptions();
        apSelector.dispatchEvent(new Event('change'));
    };

    // Expose sync function to window so we can call it after loading data
    window.syncApMultiselect = syncOptions;
    
    // Initial sync
    syncOptions();
}

function initSidebarResize() {
    const sidebar = document.getElementById('topology-sidebar');
    const handle = document.getElementById('sidebar-resize-handle');
    if (!sidebar || !handle) return;

    let isResizing = false;
    let startX, startWidth;

    handle.addEventListener('mousedown', (e) => {
        isResizing = true;
        startX = e.clientX;
        startWidth = sidebar.getBoundingClientRect().width;
        handle.classList.add('active');
        document.body.style.cursor = 'ew-resize';
        document.body.style.userSelect = 'none'; // prevent text selection
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;
        // Since the sidebar is on the right, dragging left increases width, dragging right decreases width.
        const dx = startX - e.clientX;
        const newWidth = Math.max(250, Math.min(600, startWidth + dx));
        sidebar.style.width = `${newWidth}px`;
        
        // Trigger GoJS diagram update if layout changed
        if (window.myDiagram) {
            window.myDiagram.requestUpdate();
        }
    });

    document.addEventListener('mouseup', () => {
        if (isResizing) {
            isResizing = false;
            handle.classList.remove('active');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        }
    });
}

