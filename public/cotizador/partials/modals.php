<!-- Modal: Specialist CRUD -->
<div class="modal fade" id="specialistModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title font-weight-bold" id="specialistModalTitle">Especialista</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="specialistForm" onsubmit="saveSpecialist(event)">
        <div class="modal-body">
          <input type="hidden" name="id" id="sp-id" value="0">
          
          <div class="form-group">
            <label>Nombre Completo *</label>
            <input type="text" name="nombre" id="sp-nombre" class="form-control" required placeholder="Ej. Carlos Pérez...">
          </div>
          
          <div class="form-group">
            <label>Tipo / Nivel *</label>
            <select name="tipo" id="sp-tipo" class="form-control" required onchange="toggleSpecialistFields()">
              <option value="N1">N1 Junior</option>
              <option value="N2">N2 Intermedio</option>
              <option value="N3">N3 Senior</option>
              <option value="E1">E1 Externo 1 (Tarifa fija)</option>
              <option value="E2">E2 Externo 2 (Tarifa fija)</option>
              <option value="BOC">GESTION BOC</option>
              <option value="GP1">Gestión de Proyecto 1 (PM Jr)</option>
              <option value="GP2">Gestión de Proyecto 2 (PM Sr)</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Rango Salarial (Ej. 1200 - 1400)</label>
            <input type="text" name="rango_salarial" id="sp-rango-salarial" class="form-control" placeholder="Ej. 1200 - 1400">
          </div>
          
          <div class="form-group" id="div-sp-salario">
            <label>Salario Mensual * <span id="sp-salary-range-info" class="badge badge-info ml-2" style="display: none;"></span></label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">$</span></div>
              <input type="number" step="0.01" name="salario" id="sp-salario" class="form-control" value="0.00">
              <div class="invalid-feedback">El salario debe estar dentro del rango configurado para este nivel.</div>
            </div>
          </div>
          
          <div class="form-group" id="div-sp-utilizable">
            <label>Factor de Utilización (0.01 - 1.00) *</label>
            <input type="number" step="0.01" min="0.01" max="1.00" name="utilizable" id="sp-utilizable" class="form-control" value="0.80">
          </div>
          
          <div class="form-group d-none" id="div-sp-manual">
            <label>Costo de Hora Manual *</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">$</span></div>
              <input type="number" step="0.01" name="costo_hora_manual" id="sp-costo-manual" class="form-control" value="0.00">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Especialista</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Pool Item CRUD -->
<div class="modal fade" id="poolModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title font-weight-bold" id="poolModalTitle">Servicio del Pool</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="poolForm" onsubmit="savePoolItem(event)">
        <div class="modal-body">
          <input type="hidden" name="id" id="pool-id" value="0">
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Marca / Categoría *
                <a href="#" class="ml-2 small text-warning" onclick="event.preventDefault();$('#poolModal').modal('hide');setTimeout(()=>showPoolBrandModal(),400);" title="Agregar nueva marca">
                  <i class="fas fa-plus-circle"></i> Nueva
                </a>
              </label>
              <select name="marca_categoria" id="pool-marca" class="form-control" required>
                <option value="">-- Seleccionar Marca / Categoría --</option>
                <!-- Populated dynamically from DB by loadPoolBrands() -->
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Actividad (Grupo / Nombre de Equipo) *</label>
              <input type="text" name="actividad" id="pool-actividad" class="form-control" required placeholder="Ej. Switch Catalyst, Firewall ASA...">
            </div>
          </div>
          
          <div class="form-group">
            <label>Detalle de la Tarea / Trabajo *</label>
            <textarea name="detalle" id="pool-detalle" class="form-control" rows="2" required placeholder="Escriba la descripción de la tarea..."></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-12 form-group">
              <label>Niveles recomendados (Seleccione los aplicables):</label>
              <div class="d-flex justify-content-between">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="pool-n1" name="n1">
                  <label class="custom-control-label" for="pool-n1">N1 Junior</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="pool-n2" name="n2">
                  <label class="custom-control-label" for="pool-n2">N2 Intermedio</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="pool-n3" name="n3">
                  <label class="custom-control-label" for="pool-n3">N3 Senior</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="pool-e1" name="e1">
                  <label class="custom-control-label" for="pool-e1">E1 Externo 1</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="pool-e2" name="e2">
                  <label class="custom-control-label" for="pool-e2">E2 Externo 2</label>
                </div>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-4 form-group">
              <label>Horas Laborables por Defecto</label>
              <input type="number" step="0.1" name="horas_laborables" id="pool-horas-lab" class="form-control" value="0.0">
            </div>
            <div class="col-md-4 form-group">
              <label>Horas no Lab 50% por Defecto</label>
              <input type="number" step="0.1" name="horas_no_laborables_50" id="pool-horas-50" class="form-control" value="0.0">
            </div>
            <div class="col-md-4 form-group">
              <label>Horas no Lab 100% por Defecto</label>
              <input type="number" step="0.1" name="horas_no_laborables_100" id="pool-horas-100" class="form-control" value="0.0">
            </div>
          </div>
          
          <div class="form-group">
            <label>Tipo (Mapeo opcional)</label>
            <input type="text" name="tipo" id="pool-tipo" class="form-control" placeholder="Ej. preventivo, correctivo...">
          </div>
          
          <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" id="pool-observaciones" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar en Pool</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Import Excel -->
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-file-excel mr-2"></i> Importar Catálogo desde Excel</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="importExcelForm" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="alert alert-warning border-0 shadow-sm mb-4">
            <h6 class="font-weight-bold"><i class="icon fas fa-exclamation-triangle mr-1"></i> ¡Atención!</h6>
            Esta acción <strong>eliminará por completo el catálogo actual</strong> en la base de datos y lo reemplazará con el contenido del archivo Excel subido.
          </div>
          <div class="form-group">
            <label class="font-weight-bold text-muted small">Seleccionar archivo de Excel (.xlsx, .xls, .xlsm)</label>
            <div class="custom-file">
              <input type="file" name="excel_file" id="excel_file" class="custom-file-input" accept=".xlsx, .xls, .xlsm" required>
              <label class="custom-file-label text-truncate" for="excel_file">Elegir archivo...</label>
            </div>
            <small class="form-text text-muted mt-2">
              El archivo debe contener las pestañas correspondientes a cada marca (ARUBA, CISCO, etc.) con las columnas: Actividad, Detalle, Especialidades, Horas.
            </small>
          </div>
        </div>
        <div class="modal-footer bg-light border-top-0">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success" id="btn-submit-import"><i class="fas fa-cloud-upload-alt mr-1"></i> Importar Catálogo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Select Services from Pool -->
<div class="modal fade" id="poolSelectionModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-layer-group mr-2"></i> Seleccionar Servicios del Pool</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pool-target-section">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="small font-weight-bold text-muted">Marca / Categoría (Nivel 1)</label>
            <select id="modal-pool-brand" class="form-control" onchange="onModalBrandChange()">
              <option value="">-- Todas las Marcas / Categorías --</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="small font-weight-bold text-muted">Actividad (Nivel 2)</label>
            <select id="modal-pool-activity" class="form-control" onchange="filterModalPool()">
              <option value="">-- Seleccionar Actividad --</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="small font-weight-bold text-muted">Buscador Palabra Clave (Global)</label>
            <input type="text" id="modal-pool-search" class="form-control" placeholder="Buscar en código, actividad, detalle..." onkeyup="filterModalPool()">
          </div>
        </div>
        
        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
          <table class="table table-sm table-bordered table-striped table-hover table-premium">
            <thead class="thead-light">
              <tr>
                <th style="width: 40px;" class="text-center"><input type="checkbox" id="modal-pool-checkall" onchange="toggleAllModalPool(this)"></th>
                <th>Código ID</th>
                <th>Marca</th>
                <th>Actividad (Grupo)</th>
                <th>Detalle de Tarea (Nivel 3)</th>
                <th>Recomendado</th>
                <th>Horas Lab</th>
                <th>H. 50%</th>
                <th>H. 100%</th>
              </tr>
            </thead>
            <tbody id="modal-pool-body">
              <!-- Loaded dynamically -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-button btn-success" onclick="addSelectedServicesToSection()"><i class="fas fa-plus-circle mr-1"></i> Agregar Seleccionados</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Comparison Result -->
<div class="modal fade" id="comparisonModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-columns mr-2"></i> Comparativa de Cotizaciones / Versiones</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="comparison-modal-body">
        <!-- Rendered dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Specialist Level CRUD -->
<div class="modal fade" id="levelModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title font-weight-bold" id="levelModalTitle">Configuración de Nivel / Rango</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="levelForm" onsubmit="saveLevel(event)">
        <div class="modal-body">
          <input type="hidden" name="id" id="lvl-id" value="0">
          
          <div class="form-group">
            <label>Código / Nombre del Nivel *</label>
            <input type="text" name="code" id="lvl-code" class="form-control" required placeholder="Ej. N1 Junior, N2 Intermedio...">
          </div>
          
          <div class="form-group">
            <label>Tipo Base de Cálculo (Fórmula) *</label>
            <select name="base_type" id="lvl-base-type" class="form-control" required>
              <option value="N1">N1 Formula (21d * 8h * %Utilizable / 0.95)</option>
              <option value="N2">N2 Formula (21d * 8h * %Utilizable / 0.95)</option>
              <option value="N3">N3 Formula (21d * 8h * %Utilizable / 0.95)</option>
              <option value="BOC">BOC Formula (21d * 8h * %Utilizable / 0.95)</option>
              <option value="GP1">GP1 Formula PM Jr (20d * 8h * %Utilizable)</option>
              <option value="GP2">GP2 Formula PM Sr (20d * 8h * %Utilizable)</option>
              <option value="E1">E1 (Tarifa Fija)</option>
              <option value="E2">E2 (Tarifa Fija)</option>
            </select>
          </div>
          
          <div class="row">
            <div class="col-6 form-group">
              <label>Salario Mínimo *</label>
              <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                <input type="number" step="0.01" name="min_salary" id="lvl-min-salary" class="form-control" required value="0.00">
              </div>
            </div>
            <div class="col-6 form-group">
              <label>Salario Máximo *</label>
              <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                <input type="number" step="0.01" name="max_salary" id="lvl-max-salary" class="form-control" required value="0.00">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Rango</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Equipment Category CRUD -->
<div class="modal fade" id="eqCategoryModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title font-weight-bold" id="eqCategoryModalTitle">Categoría de Equipo</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="eqCategoryForm" onsubmit="saveEqCategory(event)">
        <div class="modal-body">
          <input type="hidden" name="id" id="eq-cat-id" value="0">
          <div class="form-group">
            <label>Nombre de la Categoría *</label>
            <input type="text" name="name" id="eq-cat-name" class="form-control" required
                   placeholder="Ej. Core, Acceso, WLC, AP...">
            <small class="form-text text-muted">Este nombre aparecerá en el inventario y como multiplicador en actividades.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info font-weight-bold">Guardar Categoría</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal: Pool Brand / Activity Group CRUD -->
<div class="modal fade" id="poolBrandModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title font-weight-bold" id="poolBrandModalTitle">Marca / Grupo de Actividades</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="poolBrandForm" onsubmit="savePoolBrand(event)">
        <div class="modal-body">
          <input type="hidden" name="id" id="pb-id" value="0">
          <div class="form-group">
            <label>Nombre del Grupo / Marca *</label>
            <input type="text" name="name" id="pb-name" class="form-control" required
                   placeholder="Ej. CISCO, ARUBA, VMWARE...">
            <small class="form-text text-muted">
              Aparece como filtro en el pool y como separador de color en las tablas del workbook.<br>
              <strong>Renombrar</strong> actualizará automáticamente todas las actividades del pool que usen este grupo.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning font-weight-bold text-white">Guardar Grupo</button>
        </div>
      </form>
    </div>
  </div>
</div>
