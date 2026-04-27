var InterfaceStatusWidget = function() {
    this.initializeEventListeners();
    this.currentSort = {
        column: null,
        direction: 'asc'
    };
};

InterfaceStatusWidget.prototype = {
    initializeEventListeners: function() {
        console.log('Inicializando event listeners');


        jQuery(document).off('keydown.overlay');


        jQuery('#status').on('change', (e) => {
            const statusFilter = e.target.value;
            this.applyFilters(statusFilter);
        });

        jQuery('.sortable').on('click', (e) => {
            const column = jQuery(e.currentTarget).data('sort');
            this.handleSort(column);
        });


        this.initializeGraphButtons();


        jQuery(document).on('click', '#overlay_bg', () => {
            console.log('Overlay clicado');
            this.cleanupModal();
            return false;
        });
    },

    applyFilters: function(statusFilter) {
        jQuery('.interface-row').each((_, row) => {
            const $row = jQuery(row);
            const status = $row.find('.status-cell').first().text().trim().toLowerCase();
            
            if (statusFilter === 'all' || status === statusFilter) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    },

    handleSort: function(column) {
        const rows = Array.from(document.querySelectorAll('.interface-row'));
        const $header = jQuery(`[data-sort="${column}"]`);
        

        if (this.currentSort.column === column) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort.column = column;
            this.currentSort.direction = 'asc';
        }

        
        jQuery('.sortable').removeClass('sort-asc sort-desc');
        $header.addClass(`sort-${this.currentSort.direction}`);

        
        rows.sort((a, b) => {
            let aValue, bValue;
            
            if (column === 'status') {
                aValue = jQuery(a).find('.status-cell').first().text().trim().toLowerCase();
                bValue = jQuery(b).find('.status-cell').first().text().trim().toLowerCase();
                // Inverte a ordem para que UP venha primeiro
                return this.currentSort.direction === 'asc' ? 
                    bValue.localeCompare(aValue) : 
                    aValue.localeCompare(bValue);
            }
            else if (column === 'traffic_in' || column === 'traffic_out') {
                aValue = this.parseTrafficValue(jQuery(a).find(`.text-right:nth-child(${column === 'traffic_in' ? 4 : 5})`).text());
                bValue = this.parseTrafficValue(jQuery(b).find(`.text-right:nth-child(${column === 'traffic_in' ? 4 : 5})`).text());
                return this.currentSort.direction === 'asc' ? 
                    aValue - bValue : 
                    bValue - aValue;
            }
            
            return 0;
        });

        
        const tbody = document.querySelector('.interface-table tbody');
        rows.forEach(row => tbody.appendChild(row));
    },

    parseTrafficValue: function(trafficStr) {
        const match = trafficStr.match(/([\d.]+)\s*([KMGT]?B)\/s/);
        if (!match) return 0;
        
        const value = parseFloat(match[1]);
        const unit = match[2];
        
        const multipliers = {
            'B': 1,
            'KB': 1024,
            'MB': 1024 * 1024,
            'GB': 1024 * 1024 * 1024,
            'TB': 1024 * 1024 * 1024 * 1024
        };

        return value * (multipliers[unit] || 1);
    },

    initializeGraphButtons: function() {
        document.querySelectorAll('.btn-graph').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                const hostid = btn.getAttribute('data-hostid');
                const interfaceIndex = btn.getAttribute('data-interface-index');
                const interfaceName = btn.getAttribute('data-interface-name');
                const itemidIn = btn.getAttribute('data-itemid-in');
                const itemidOut = btn.getAttribute('data-itemid-out');
                
                if (!hostid || !interfaceIndex) {
                    console.warn('Dados da interface não encontrados');
                    return;
                }

                this.showGraphModal(hostid, interfaceIndex, interfaceName, itemidIn, itemidOut);
            });
        });
    },

    showGraphModal: function(hostid, interfaceIndex, interfaceName, itemidIn, itemidOut) {
        console.log('Criando modal para interface:', interfaceName);
        const modalContent = this.createModalContent(interfaceName);

        
        jQuery(document).off('keydown.interface_modal');
        jQuery(document).off('keydown.overlay');

        // Criar o modal
        overlayDialogue({
            'title': t('Interface Traffic Details for') + ' ' + interfaceName,
            'content': modalContent,
            'class': 'modal-popup-medium interface-modal',
            'buttons': [{
                'title': t('Close'),
                'class': 'btn-alt',
                'action': () => {
                    this.cleanupModal();
                    return true;
                }
            }],
            'dialogueid': 'interface-details-' + hostid + '-' + interfaceIndex
        });

        
        const checkModalReady = () => {
            const chartContainer = document.getElementById(`chart-${interfaceName.replace(/[^a-zA-Z0-9]/g, '-')}`);
            const modalVisible = jQuery('.interface-modal').is(':visible');
            
            if (chartContainer && modalVisible) {
                this.loadGraph(chartContainer, itemidIn, itemidOut);
            } else {
                setTimeout(checkModalReady, 50);
            }
        };

        setTimeout(checkModalReady, 100);
    },

    loadGraph: function(container, itemidIn, itemidOut) {
        if (!itemidIn || !itemidOut) {
            container.innerHTML = '<div class="msg-bad">Error: Items not found</div>';
            return;
        }

        const img = document.createElement('img');
        const url = new URL('chart.php', window.location.origin);
        url.searchParams.append('itemids[0]', itemidIn);
        url.searchParams.append('itemids[1]', itemidOut);
        url.searchParams.append('period', '86400');
        url.searchParams.append('width', container.offsetWidth.toString());
        url.searchParams.append('height', '300');
        url.searchParams.append('_', new Date().getTime());
        
        img.src = url.toString();
        img.style.width = '100%';
        img.style.height = 'auto';
        img.alt = 'Interface Traffic Chart';
        
        container.innerHTML = '';
        container.appendChild(img);
    },

    cleanupModal: function() {
        console.log('Iniciando limpeza do modal');
        
        jQuery(document).off('keydown.interface_modal');
        jQuery(document).off('keydown.overlay');
        
        jQuery('.overlay-dialogue, .overlay-dialogue-footer, #overlay_bg').remove();
        
        jQuery('body')
            .css('overflow', '')
            .removeClass('overlay-dialogue-open')
            .removeClass('no-scrolling')
            .removeClass('body-scroll-lock')
            .removeClass('overlay-dialogue-opened');
            
        jQuery('body').removeAttr('style');
        
        void document.body.offsetHeight;
    },

    createModalContent: function(interfaceName) {
        return `
            <div class="interface-details-container">
                <div class="interface-chart-container" id="chart-${interfaceName.replace(/[^a-zA-Z0-9]/g, '-')}">
                </div>
            </div>
        `;
    }
};

jQuery(document).ready(function($) {
    'use strict';
    window.interfaceStatusWidget = new InterfaceStatusWidget();
}); 