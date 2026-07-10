<!-- TAB 2: QUOTE WORKBOOK DESIGNER -->
<div class="tab-pane fade <?php echo (isset($active_tab) && $active_tab === 'editor') ? 'show active' : ''; ?>" id="tab-editor" role="tabpanel">
  <form id="quote-editor-form" onsubmit="event.preventDefault();">
    <input type="hidden" id="editor-quote-id" value="0">
    <input type="hidden" id="editor-parent-id" value="">
    <input type="hidden" id="editor-version" value="1">
    
    <!-- Workbook Sections Container -->
    <div class="card card-outline card-secondary shadow-none border">
      <div class="card-header p-0 border-bottom">
        <ul class="nav nav-tabs px-3 pt-2" id="designer-sections" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="sec-config-link" data-toggle="tab" href="#sec-config" role="tab"><i class="fas fa-cog mr-1"></i> 01. Configuración de Propuesta</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="sec-inventario-link" data-toggle="tab" href="#sec-inventario" role="tab"><i class="fas fa-network-wired mr-1"></i> 02. Inventario de Equipos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="sec-implementacion-link" data-toggle="tab" href="#sec-implementacion" role="tab"><i class="fas fa-tools mr-1"></i> 03. Implementación</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="sec-preventivo-link" data-toggle="tab" href="#sec-preventivo" role="tab"><i class="fas fa-shield-alt mr-1"></i> 04. Mantenimiento Preventivo</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="sec-correctivo-link" data-toggle="tab" href="#sec-correctivo" role="tab"><i class="fas fa-wrench mr-1"></i> 05. Mantenimiento Correctivo</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="sec-bolsa-link" data-toggle="tab" href="#sec-bolsa" role="tab"><i class="fas fa-business-time mr-1"></i> 06. Bolsa de Horas</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-success" id="sec-resumen-link" data-toggle="tab" href="#sec-resumen" role="tab"><i class="fas fa-file-invoice-dollar mr-1"></i> 07. Resumen General</a>
          </li>
        </ul>
      </div>
      
      <div class="card-body p-3">
        <div class="tab-content">
          
          <!-- 01. CONFIGURACIÓN DE PROPUESTA SECTION -->
          <div class="tab-pane fade show active" id="sec-config" role="tabpanel">
            <div class="card p-4 shadow-none border">
              <h5 class="text-primary font-weight-bold mb-4"><i class="fas fa-cog mr-2"></i> 01. Configuración de Propuesta</h5>
              <div class="row">
                <div class="col-md-6 form-group">
                  <label class="font-weight-bold">Cliente (Buscar o Ingresar) *</label>
                  <input type="text" id="editor-cliente" class="form-control form-control-lg" required placeholder="Escriba el nombre del cliente..." list="clients-datalist">
                  <datalist id="clients-datalist">
                    <?php foreach ($clients as $c): ?>
                      <option value="<?php echo htmlspecialchars($c['hostname']); ?>"><?php echo htmlspecialchars($c['ci_unique']); ?></option>
                    <?php endforeach; ?>
                  </datalist>
                  <small class="text-muted">Seleccione un cliente existente o ingrese uno nuevo.</small>
                </div>
                <div class="col-md-6 form-group">
                  <label class="font-weight-bold">Contrato / Nombre del Proyecto *</label>
                  <input type="text" id="editor-contrato" class="form-control form-control-lg" required placeholder="Ej. Renovación Core 2026...">
                  <small class="text-muted">Nombre identificativo del proyecto o contrato.</small>
                </div>
              </div>
              <div class="row mt-3">
                <div class="col-md-6 form-group">
                  <label class="font-weight-bold">Fecha de Creación *</label>
                  <input type="date" id="editor-fecha" class="form-control form-control-lg" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label class="font-weight-bold">Margen Global de Ganancia (PVP) *</label>
                  <div class="input-group input-group-lg">
                    <input type="number" step="1" min="0" max="100" id="editor-margen-global" class="form-control" required value="20" onchange="recalculateAll()">
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                  </div>
                  <small class="text-muted">Margen utilizado para calcular el precio de venta (PVP) basado en el costo del especialista.</small>
                </div>
              </div>
              
              <div class="mt-4 d-flex justify-content-end">
                <button type="button" class="btn btn-primary px-4 btn-lg font-weight-bold" onclick="$('#sec-inventario-link').tab('show')">
                  Siguiente: Inventario de Equipos <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>
          </div>
          
          <!-- 02. INVENTARIO DE EQUIPOS SECTION -->
          <div class="tab-pane fade" id="sec-inventario" role="tabpanel">
            <div class="card p-4 shadow-none border">
              <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="text-info font-weight-bold mb-0"><i class="fas fa-network-wired mr-2"></i> 02. Inventario de Equipos (Físicos o Virtuales)</h5>
                <button type="button" class="btn btn-info btn-sm font-weight-bold" onclick="addEquipmentInventoryRow('Acceso', 0)">
                  <i class="fas fa-plus-circle mr-1"></i> Agregar Fila de Equipo
                </button>
              </div>
              
              <p class="text-muted mb-4">
                Ingrese la lista de equipos contemplados en el proyecto. Estas cantidades actuarán como multiplicadores en las tareas que utilicen el factor de escala correspondiente.
              </p>
              
              <div class="table-responsive">
                <table class="table table-bordered table-striped table-premium" id="eq-inventory-table">
                  <thead class="thead-light">
                    <tr>
                      <th>Categoría / Tipo de Equipo</th>
                      <th style="width: 250px;">Cantidad de Equipos</th>
                      <th style="width: 120px;" class="text-center">Acción</th>
                    </tr>
                  </thead>
                  <tbody id="eq-inventory-tbody">
                    <!-- Dynamic rows are added here -->
                  </tbody>
                </table>
              </div>
              
              <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4 btn-lg" onclick="$('#sec-config-link').tab('show')"><i class="fas fa-arrow-left mr-2"></i> Atrás</button>
                <button type="button" class="btn btn-info px-4 btn-lg font-weight-bold" onclick="$('#sec-implementacion-link').tab('show')">Siguiente: Implementación <i class="fas fa-arrow-right ml-2"></i></button>
              </div>
            </div>
          </div>
          
          <!-- 03. IMPLEMENTACIÓN SECTION -->
          <div class="tab-pane fade" id="sec-implementacion" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="text-primary font-weight-bold mb-0">Servicios de Implementación, Migración & Configuración</h5>
              <button type="button" class="btn btn-info btn-sm" onclick="openPoolSelectionModal('Implementacion')"><i class="fas fa-search-plus mr-1"></i> Agregar Servicios del Pool</button>
            </div>
            
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-premium" id="table-impl-items">
                <thead>
                  <tr>
                    <th style="width: 100px;">Código ID</th>
                    <th>Marca / Categoria</th>
                    <th>Actividad (Grupo)</th>
                    <th>Detalle de Tarea</th>
                    <th style="width: 130px;">Mult. Equipo</th>
                    <th style="width: 140px;">Especialista</th>
                    <th style="width: 80px;">H. Lab</th>
                    <th style="width: 80px;">H. 50%</th>
                    <th style="width: 80px;">H. 100%</th>
                    <th class="text-right">Costo unit</th>
                    <th class="text-right">PVP unit</th>
                    <th class="text-right">Costo Total</th>
                    <th class="text-right">PVP Total</th>
                    <th style="width: 50px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Dynamic rows -->
                </tbody>
                <tfoot>
                  <tr class="table-info font-weight-bold">
                    <td colspan="6">Subtotal Horas de Servicios</td>
                    <td id="impl-tot-h-lab">0</td>
                    <td id="impl-tot-h-50">0</td>
                    <td id="impl-tot-h-100">0</td>
                    <td colspan="2"></td>
                    <td class="text-right" id="impl-tot-cost-serv">$0.00</td>
                    <td class="text-right" id="impl-tot-pvp-serv">$0.00</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            
            <!-- Implementation Overheads & Extras -->
            <div class="sub-total-section">Adicionales de Implementación</div>
            <div class="row mb-3">
              <!-- Project Risk -->
              <div class="col-md-3 form-group">
                <label>Riesgo Proyecto (% sobre Horas)</label>
                <div class="input-group">
                  <input type="number" id="impl-risk-pct" class="form-control" value="10" onchange="recalculateAll()">
                  <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
              </div>
              <!-- Toggle for Knowledge Transfer -->
              <div class="col-md-3 form-group">
                <label>¿Incluye Transferencia de Conocimiento?</label>
                <select id="impl-kt-incluye" class="form-control" onchange="toggleKtSection(); recalculateAll();">
                  <option value="No">No</option>
                  <option value="Si">Sí</option>
                </select>
              </div>
            </div>

            <!-- Knowledge Transfer Panel -->
            <div id="impl-kt-panel" class="card card-outline card-warning shadow-none border mb-3" style="display: none;">
              <div class="card-header py-2">
                <h6 class="card-title text-warning font-weight-bold mb-0"><i class="fas fa-graduation-cap mr-1"></i> Detalle de Transferencia de Conocimiento y Talleres</h6>
              </div>
              <div class="card-body p-3">
                <div class="row">
                  <div class="col-md-2 form-group">
                    <label class="small font-weight-bold">Cant. Personas</label>
                    <input type="number" min="1" id="impl-kt-personas" class="form-control form-control-sm text-center" value="2" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-2 form-group">
                    <label class="small font-weight-bold">Duración (Días)</label>
                    <input type="number" min="1" id="impl-kt-dias" class="form-control form-control-sm text-center" value="2" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-2 form-group">
                    <label class="small font-weight-bold">Horas por Día</label>
                    <input type="number" min="1" id="impl-kt-horas-dia" class="form-control form-control-sm text-center" value="8" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-3 form-group">
                    <label class="small font-weight-bold">Nivel Especialista KT <span class="badge badge-success ml-1" id="impl-kt-level-rate-badge"></span></label>
                    <select id="impl-kt-level" class="form-control form-control-sm" onchange="recalculateAll()">
                      <option value="N1">N1 Junior</option>
                      <option value="N2">N2 Intermedio</option>
                      <option value="N3" selected>N3 Senior</option>
                      <option value="E1">E1 Externo 1</option>
                      <option value="E2">E2 Externo 2</option>
                    </select>
                  </div>
                  <div class="col-md-3 form-group">
                    <label class="small font-weight-bold">Breaks/Taller ($/persona/día)</label>
                    <input type="number" min="0" id="impl-kt-breaks-unit" class="form-control form-control-sm text-center" value="5" onchange="recalculateAll()">
                  </div>
                </div>
                <!-- Calculations display inside panel -->
                <div class="row mt-2 bg-light p-2 rounded">
                  <div class="col-md-4">
                    <span class="small text-muted">Total Horas de Capacitación:</span>
                    <strong id="lbl-kt-total-hours">0 Horas</strong>
                    <input type="hidden" id="impl-kt-hours" value="0">
                  </div>
                  <div class="col-md-4">
                    <span class="small text-muted">Costo Breaks - Catering:</span>
                    <strong id="lbl-kt-breaks-cost">$0.00</strong>
                    <input type="hidden" id="impl-breaks-cost" value="0">
                  </div>
                  <div class="col-md-4">
                    <span class="small text-muted">PVP Servicio de Capacitación:</span>
                    <strong class="text-success" id="lbl-kt-service-pvp">$0.00</strong>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row mt-3">
              <!-- Travel expenses -->
              <div class="col-md-6">
                <div class="card p-3 shadow-none border h-100">
                  <h6 class="font-weight-bold text-secondary"><i class="fas fa-plane-departure mr-1"></i> Movilización y Viáticos (Provincias)</h6>
                  <div class="row">
                    <div class="col-6 form-group">
                      <label>Noches fuera Gye/Uio</label>
                      <input type="number" id="impl-travel-nights" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                    <div class="col-6 form-group">
                      <label>Costo de Viático / Noche</label>
                      <input type="number" id="impl-travel-cost-night" class="form-control" value="25" onchange="recalculateAll()">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-6 form-group">
                      <label>Vuelos Nacionales (Cant)</label>
                      <input type="number" id="impl-flights-qty" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                    <div class="col-6 form-group">
                      <label>Costo / Vuelo</label>
                      <input type="number" id="impl-flight-cost" class="form-control" value="150" onchange="recalculateAll()">
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- PSS Support & Ext Support -->
              <div class="col-md-6">
                <div class="card p-3 shadow-none border h-100">
                  <h6 class="font-weight-bold text-secondary"><i class="fas fa-ticket-alt mr-1"></i> Soporte Externo / PSS (Fabricantes)</h6>
                  <div class="form-group">
                    <label>Valor de PSS (Fabricante - USD)</label>
                    <input type="number" id="impl-pss-val" class="form-control" value="0" onchange="recalculateAll()">
                  </div>
                  <div class="row">
                    <div class="col-6 form-group">
                      <label>Apoyo Prov Ext. (Costo USD)</label>
                      <input type="number" id="impl-ext-prov-cost" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                    <div class="col-6 form-group">
                      <label>Apoyo Prov Ext. (PVP USD)</label>
                      <input type="number" id="impl-ext-prov-pvp" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Service Management overheads -->
            <div class="sub-total-section">Servicios de Gestión & Post-Implementación</div>
            <div class="row">
              <!-- BOC Management -->
              <div class="col-md-6">
                <div class="card p-3 shadow-none border">
                  <h6 class="font-weight-bold text-secondary"><i class="fas fa-headset mr-1"></i> Gestión BOC post-implementación</h6>
                  <div class="row">
                    <div class="col-4 form-group">
                      <label>Meses</label>
                      <input type="number" id="impl-boc-months" class="form-control" value="12" onchange="recalculateAll()">
                    </div>
                    <div class="col-4 form-group">
                      <label>Horas / Mes</label>
                      <input type="number" id="impl-boc-hours" class="form-control" value="2" onchange="recalculateAll()">
                    </div>
                    <div class="col-4 form-group">
                      <label>Nivel Especialista <span class="badge badge-success ml-1" id="impl-boc-level-rate-badge"></span></label>
                      <select id="impl-boc-level" class="form-control" onchange="recalculateAll()">
                        <option value="BOC" selected>BOC Agent</option>
                        <option value="N1">N1 Junior</option>
                        <option value="N2">N2 Intermedio</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Technical PM Reports -->
              <div class="col-md-6">
                <div class="card p-3 shadow-none border">
                  <h6 class="font-weight-bold text-secondary"><i class="fas fa-file-alt mr-1"></i> Elaboración mensual de informes técnicos</h6>
                  <div class="row">
                    <div class="col-4 form-group">
                      <label>Meses</label>
                      <input type="number" id="impl-pm-months" class="form-control" value="12" onchange="recalculateAll()">
                    </div>
                    <div class="col-4 form-group">
                      <label>Horas / Mes</label>
                      <input type="number" id="impl-pm-hours" class="form-control" value="6" onchange="recalculateAll()">
                    </div>
                    <div class="col-4 form-group">
                      <label>Nivel Especialista <span class="badge badge-success ml-1" id="impl-pm-level-rate-badge"></span></label>
                      <select id="impl-pm-level" class="form-control" onchange="recalculateAll()">
                        <option value="GP1" selected>PM Junior</option>
                        <option value="GP2">PM Senior</option>
                        <option value="N2">N2 Intermedio</option>
                        <option value="N3">N3 Senior</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row">
              <!-- Other Consumables -->
              <div class="col-md-12">
                <div class="card p-3 shadow-none border">
                  <h6 class="font-weight-bold text-secondary"><i class="fas fa-box-open mr-1"></i> Consumibles y Otros Gastos</h6>
                  <div class="row">
                    <div class="col-md-3 form-group">
                      <label>Tornillos & Binchas (USD)</label>
                      <input type="number" id="impl-consumables-screws" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                    <div class="col-md-3 form-group">
                      <label>Etiquetas (USD)</label>
                      <input type="number" id="impl-consumables-labels" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                    <div class="col-md-3 form-group">
                      <label>Vacunas (USD)</label>
                      <input type="number" id="impl-consumables-vaccines" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                    <div class="col-md-3 form-group">
                      <label>EPPs (USD)</label>
                      <input type="number" id="impl-consumables-epp" class="form-control" value="0" onchange="recalculateAll()">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary px-4 btn-lg" onclick="$('#sec-inventario-link').tab('show')"><i class="fas fa-arrow-left mr-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary px-4 btn-lg font-weight-bold" onclick="$('#sec-preventivo-link').tab('show')">Siguiente: Mantenimiento Preventivo <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
          </div>
          
          <!-- 04. MANTENIMIENTO PREVENTIVO SECTION -->
          <div class="tab-pane fade" id="sec-preventivo" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="text-primary font-weight-bold mb-0">Mantenimiento Preventivo Periódico</h5>
              <button type="button" class="btn btn-info btn-sm" onclick="openPoolSelectionModal('MantPrev')"><i class="fas fa-search-plus mr-1"></i> Agregar Preventivos del Pool</button>
            </div>
            
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-premium" id="table-prev-items">
                <thead>
                  <tr>
                    <th style="width: 100px;">Código ID</th>
                    <th>Marca / Categoria</th>
                    <th>Actividad (Grupo)</th>
                    <th>Detalle de Tarea</th>
                    <th style="width: 130px;">Mult. Equipo</th>
                    <th style="width: 140px;">Especialista</th>
                    <th style="width: 80px;">H. Lab</th>
                    <th style="width: 80px;">H. 50%</th>
                    <th style="width: 80px;">H. 100%</th>
                    <th class="text-right">Costo unit</th>
                    <th class="text-right">PVP unit</th>
                    <th class="text-right">Costo Total</th>
                    <th class="text-right">PVP Total</th>
                    <th style="width: 50px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Dynamic rows -->
                </tbody>
                <tfoot>
                  <tr class="table-info font-weight-bold">
                    <td colspan="6">Subtotal Horas de Preventivos</td>
                    <td id="prev-tot-h-lab">0</td>
                    <td id="prev-tot-h-50">0</td>
                    <td id="prev-tot-h-100">0</td>
                    <td colspan="2"></td>
                    <td class="text-right" id="prev-tot-cost-serv">$0.00</td>
                    <td class="text-right" id="prev-tot-pvp-serv">$0.00</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            
            <!-- Additional Preventives details -->
            <div class="sub-total-section">Adicionales de Mantenimiento Preventivo</div>
            <div class="row">
              <div class="col-md-3 form-group">
                <label>Noches Viajes Prov. (Cant)</label>
                <input type="number" id="prev-travel-nights" class="form-control" value="0" onchange="recalculateAll()">
              </div>
              <div class="col-md-3 form-group">
                <label>Vuelos Nacionales (Cant)</label>
                <input type="number" id="prev-flights-qty" class="form-control" value="0" onchange="recalculateAll()">
              </div>
              <div class="col-md-3 form-group">
                <label>Materiales Mantenimiento (USD)</label>
                <input type="number" id="prev-materials-cost" class="form-control" value="0" onchange="recalculateAll()">
              </div>
              <div class="col-md-3 form-group">
                <label>Soporte PSS Preventivo (USD)</label>
                <input type="number" id="prev-pss-cost" class="form-control" value="0" onchange="recalculateAll()">
              </div>
            </div>
            <div class="mt-4 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary px-4 btn-lg" onclick="$('#sec-implementacion-link').tab('show')"><i class="fas fa-arrow-left mr-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary px-4 btn-lg font-weight-bold" onclick="$('#sec-correctivo-link').tab('show')">Siguiente: Mantenimiento Correctivo <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
          </div>
          
          <!-- 05. MANTENIMIENTO CORRECTIVO SECTION -->
          <div class="tab-pane fade" id="sec-correctivo" role="tabpanel">
            <h5 class="text-primary font-weight-bold mb-3">Mantenimiento Correctivo (Atención a Fallas / Incidencias)</h5>
            
            <div class="row">
              <!-- Option Selector -->
              <div class="col-md-12 form-group">
                <label class="font-weight-bold">Método de Cálculo de Correctivos:</label>
                <div class="d-flex">
                  <div class="custom-control custom-radio mr-4">
                    <input type="radio" id="corr-opt-1" name="corr-option" class="custom-control-input" value="hours" checked onchange="recalculateAll()">
                    <label class="custom-control-label" for="corr-opt-1">Opción 1: Cálculo Basado en Horas / Pool de Servicios</label>
                  </div>
                  <div class="custom-control custom-radio">
                    <input type="radio" id="corr-opt-2" name="corr-option" class="custom-control-input" value="cases" onchange="recalculateAll()">
                    <label class="custom-control-label" for="corr-opt-2">Opción 2: Cálculo por Casos Estimados de Soporte (Nivel de Equipos)</label>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- DIV FOR OPTION 1: HOURLY SYSTEM -->
            <div id="corr-div-hours" class="mt-3">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="font-weight-bold text-secondary">Correctivos - Cálculo por Horas de Tareas</h6>
                <button type="button" class="btn btn-info btn-sm" onclick="openPoolSelectionModal('MantCorr')"><i class="fas fa-search-plus mr-1"></i> Agregar del Pool</button>
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-premium" id="table-corr-items">
                  <thead>
                    <tr>
                      <th style="width: 100px;">Código ID</th>
                      <th>Marca / Categoria</th>
                      <th>Actividad (Grupo)</th>
                      <th>Detalle de Tarea</th>
                      <th style="width: 130px;">Mult. Equipo</th>
                      <th style="width: 140px;">Especialista</th>
                      <th style="width: 80px;">H. Lab</th>
                      <th style="width: 80px;">H. 50%</th>
                      <th style="width: 80px;">H. 100%</th>
                      <th class="text-right">Costo unit</th>
                      <th class="text-right">PVP unit</th>
                      <th class="text-right">Costo Total</th>
                      <th class="text-right">PVP Total</th>
                      <th style="width: 50px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Dynamic rows -->
                  </tbody>
                  <tfoot>
                    <tr class="table-info font-weight-bold">
                      <td colspan="6">Subtotal Horas de Correctivos</td>
                      <td id="corr-tot-h-lab">0</td>
                      <td id="corr-tot-h-50">0</td>
                      <td id="corr-tot-h-100">0</td>
                      <td colspan="2"></td>
                      <td class="text-right" id="corr-tot-cost-serv">$0.00</td>
                      <td class="text-right" id="corr-tot-pvp-serv">$0.00</td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              
              <div class="sub-total-section">Adicionales de Correctivo (Opción 1)</div>
              <div class="row">
                <div class="col-md-3 form-group">
                  <label>Noches Viajes (Cant)</label>
                  <input type="number" id="corr-travel-nights" class="form-control" value="0" onchange="recalculateAll()">
                </div>
                <div class="col-md-3 form-group">
                  <label>Vuelos Nacionales (Cant)</label>
                  <input type="number" id="corr-flights-qty" class="form-control" value="0" onchange="recalculateAll()">
                </div>
                <div class="col-md-3 form-group">
                  <label>Materiales Mantenimiento (USD)</label>
                  <input type="number" id="corr-materials-cost" class="form-control" value="0" onchange="recalculateAll()">
                </div>
                <div class="col-md-3 form-group">
                  <label>Soporte PSS Correctivo (USD)</label>
                  <input type="number" id="corr-pss-cost" class="form-control" value="0" onchange="recalculateAll()">
                </div>
              </div>
            </div>
            
            <!-- DIV FOR OPTION 2: CASE SYSTEM -->
            <div id="corr-div-cases" class="mt-3 d-none">
              <div class="card p-3 shadow-none border">
                <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-calculator mr-1"></i> Estimación Financiera por Casos de Atención</h6>
                <div class="row">
                  <div class="col-md-3 form-group">
                    <label>Número de Equipos *</label>
                    <input type="number" id="corr-case-equipos" class="form-control" value="53" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Porcentaje de Daño Estimado</label>
                    <div class="input-group">
                      <input type="number" step="0.1" id="corr-case-dmg-pct" class="form-control" value="10" onchange="recalculateAll()">
                      <div class="input-group-append"><span class="input-group-text">%</span></div>
                    </div>
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Casos Calculados (Anuales)</label>
                    <input type="number" id="corr-case-cases-calc" class="form-control" value="6" readonly>
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Años de Contrato</label>
                    <input type="number" id="corr-case-years" class="form-control" value="1" onchange="recalculateAll()">
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-3 form-group">
                    <label>Horas por Caso *</label>
                    <input type="number" id="corr-case-hours-per-case" class="form-control" value="4" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Nivel Especialista de Sop <span class="badge badge-success ml-1" id="corr-case-level-rate-badge"></span></label>
                    <select id="corr-case-level" class="form-control" onchange="recalculateAll()">
                      <option value="N1">N1 Junior</option>
                      <option value="N2" selected>N2 Intermedio</option>
                      <option value="N3">N3 Senior</option>
                      <option value="E1">E1 Externo 1</option>
                      <option value="E2">E2 Externo 2</option>
                    </select>
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Costo Total Estimado</label>
                    <div class="input-group">
                      <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                      <input type="text" id="corr-case-total-cost" class="form-control" value="0.00" readonly>
                    </div>
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Precio Final Estimado (PVP)</label>
                    <div class="input-group">
                      <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                      <input type="text" id="corr-case-total-pvp" class="form-control" value="0.00" readonly>
                    </div>
                  </div>
                </div>
                
                <div class="sub-total-section">Movilización Correctiva (Por Caso)</div>
                <div class="row">
                  <div class="col-md-3 form-group">
                    <label>Costo de Movil. / Viaje ($)</label>
                    <input type="number" id="corr-case-mov-cost" class="form-control" value="15" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-3 form-group">
                    <label>PVP de Movil. / Viaje ($)</label>
                    <input type="number" id="corr-case-mov-pvp" class="form-control" value="15" onchange="recalculateAll()">
                  </div>
                  <div class="col-md-3 form-group">
                    <label>Costo Total Movil. ($)</label>
                    <input type="text" id="corr-case-mov-tot-cost" class="form-control" readonly value="0">
                  </div>
                  <div class="col-md-3 form-group">
                    <label>PVP Total Movil. ($)</label>
                    <input type="text" id="corr-case-mov-tot-pvp" class="form-control" readonly value="0">
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary px-4 btn-lg" onclick="$('#sec-preventivo-link').tab('show')"><i class="fas fa-arrow-left mr-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary px-4 btn-lg font-weight-bold" onclick="$('#sec-bolsa-link').tab('show')">Siguiente: Bolsa de Horas <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
          </div>
          
          <!-- 06. BOLSA DE HORAS SECTION -->
          <div class="tab-pane fade" id="sec-bolsa" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="text-primary font-weight-bold mb-0">Bolsa de Horas de Soporte</h5>
              <button type="button" class="btn btn-info btn-sm" onclick="openPoolSelectionModal('BolsaHoras')"><i class="fas fa-search-plus mr-1"></i> Agregar del Pool</button>
            </div>
            
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-premium" id="table-bolsa-items">
                <thead>
                  <tr>
                    <th style="width: 100px;">Código ID</th>
                    <th>Marca / Categoria</th>
                    <th>Actividad (Grupo)</th>
                    <th>Detalle de Tarea</th>
                    <th style="width: 130px;">Mult. Equipo</th>
                    <th style="width: 140px;">Especialista</th>
                    <th style="width: 80px;">H. Lab</th>
                    <th style="width: 80px;">H. 50%</th>
                    <th style="width: 80px;">H. 100%</th>
                    <th class="text-right">Costo unit</th>
                    <th class="text-right">PVP unit</th>
                    <th class="text-right">Costo Total</th>
                    <th class="text-right">PVP Total</th>
                    <th style="width: 50px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Dynamic rows -->
                </tbody>
                <tfoot>
                  <tr class="table-info font-weight-bold">
                    <td colspan="6">Subtotal Horas de Bolsa</td>
                    <td id="bolsa-tot-h-lab">0</td>
                    <td id="bolsa-tot-h-50">0</td>
                    <td id="bolsa-tot-h-100">0</td>
                    <td colspan="2"></td>
                    <td class="text-right" id="bolsa-tot-cost-serv">$0.00</td>
                    <td class="text-right" id="bolsa-tot-pvp-serv">$0.00</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            
            <div class="sub-total-section">Adicionales Bolsa de Horas</div>
            <div class="row">
              <div class="col-md-3 form-group">
                <label>Viajes Movilización Extra (Cant)</label>
                <input type="number" id="bolsa-travel-extra" class="form-control" value="0" onchange="recalculateAll()">
              </div>
              <div class="col-md-3 form-group">
                <label>Vuelos Nacionales (Cant)</label>
                <input type="number" id="bolsa-flights-qty" class="form-control" value="0" onchange="recalculateAll()">
              </div>
            </div>
            <div class="mt-4 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary px-4 btn-lg" onclick="$('#sec-correctivo-link').tab('show')"><i class="fas fa-arrow-left mr-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary px-4 btn-lg font-weight-bold" onclick="$('#sec-resumen-link').tab('show')">Siguiente: Resumen General <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
          </div>
          
          <!-- 07. SUMMARY & PERSISTENCE SECTION -->
          <div class="tab-pane fade" id="sec-resumen" role="tabpanel">
            <h5 class="text-primary font-weight-bold mb-4">Resumen General de la Cotización</h5>
            
            <div class="row">
              <div class="col-md-8">
                <div class="table-responsive">
                  <table class="table table-bordered table-premium">
                    <thead>
                      <tr>
                        <th>Sección / Componente</th>
                        <th class="text-right">Costo Total</th>
                        <th class="text-right">Precio de Venta (PVP)</th>
                        <th class="text-right">Rentabilidad ($)</th>
                        <th class="text-right">Margen Real</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>1. Implementación & Adicionales</td>
                        <td class="text-right" id="sum-impl-cost">$0.00</td>
                        <td class="text-right" id="sum-impl-pvp">$0.00</td>
                        <td class="text-right" id="sum-impl-rent">$0.00</td>
                        <td class="text-right" id="sum-impl-marg">0%</td>
                      </tr>
                      <tr>
                        <td>2. Mantenimiento Preventivo</td>
                        <td class="text-right" id="sum-prev-cost">$0.00</td>
                        <td class="text-right" id="sum-prev-pvp">$0.00</td>
                        <td class="text-right" id="sum-prev-rent">$0.00</td>
                        <td class="text-right" id="sum-prev-marg">0%</td>
                      </tr>
                      <tr>
                        <td>3. Mantenimiento Correctivo</td>
                        <td class="text-right" id="sum-corr-cost">$0.00</td>
                        <td class="text-right" id="sum-corr-pvp">$0.00</td>
                        <td class="text-right" id="sum-corr-rent">$0.00</td>
                        <td class="text-right" id="sum-corr-marg">0%</td>
                      </tr>
                      <tr>
                        <td>4. Bolsa de Horas</td>
                        <td class="text-right" id="sum-bolsa-cost">$0.00</td>
                        <td class="text-right" id="sum-bolsa-pvp">$0.00</td>
                        <td class="text-right" id="sum-bolsa-rent">$0.00</td>
                        <td class="text-right" id="sum-bolsa-marg">0%</td>
                      </tr>
                      <tr class="total-row">
                        <td>TOTALES GENERALES</td>
                        <td class="text-right" id="sum-tot-cost">$0.00</td>
                        <td class="text-right" id="sum-tot-pvp">$0.00</td>
                        <td class="text-right" id="sum-tot-rent">$0.00</td>
                        <td class="text-right" id="sum-tot-marg">0%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="card p-3 shadow-none border h-100 d-flex flex-column justify-content-between">
                  <div>
                    <h6 class="font-weight-bold text-secondary mb-3"><i class="fas fa-save mr-1"></i> Guardar y Registrar Cotización</h6>
                    <div class="form-group">
                      <label>Comentarios / Observaciones Internas</label>
                      <textarea id="editor-observaciones" class="form-control" rows="4" placeholder="Escriba notas sobre esta cotización..."></textarea>
                    </div>
                  </div>
                  
                  <div class="mt-4">
                    <div id="editor-version-badge" class="mb-2 text-muted font-weight-bold"></div>
                    <button type="button" class="btn btn-success btn-block btn-lg mb-2" onclick="submitQuoteForm('update')">
                      <i class="fas fa-save mr-1"></i> Guardar Cotización
                    </button>
                    <button type="button" class="btn btn-outline-info btn-block d-none" id="btn-save-as-new-version" onclick="submitQuoteForm('new_version')">
                      <i class="fas fa-history mr-1"></i> Guardar como Nueva Versión
                    </button>
                    <button type="button" class="btn btn-default btn-block mt-3" onclick="cancelEditor()">Cancelar</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="row mt-4">
              <div class="col-12">
                <button type="button" class="btn btn-outline-secondary px-4 btn-lg" onclick="$('#sec-bolsa-link').tab('show')"><i class="fas fa-arrow-left mr-2"></i> Atrás al Paso Anterior</button>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </form>
</div>
