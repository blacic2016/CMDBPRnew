<!-- TAB 3: POOL OF SERVICES (CRUD) -->
<div class="tab-pane fade" id="tab-pool" role="tabpanel">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="text-primary font-weight-bold mb-0">Catálogo / Pool de Servicios y Actividades</h5>
    <div>
      <button class="btn btn-outline-success mr-2" onclick="showImportExcelModal()"><i class="fas fa-file-excel mr-1"></i> Importar desde Excel</button>
      <button class="btn btn-primary" onclick="showPoolModal()"><i class="fas fa-plus mr-1"></i> Agregar Actividad al Pool</button>
    </div>
  </div>
  
  <div class="row mb-4">
    <div class="col-md-4">
      <label class="small font-weight-bold text-muted">Marca / Categoría (Nivel 1)</label>
      <select id="pool-brand-filter" class="form-control" onchange="onPoolBrandFilterChange()">
        <option value="">-- Todas las Marcas / Categorías --</option>
        <!-- Populated dynamically -->
      </select>
    </div>
    <div class="col-md-4">
      <label class="small font-weight-bold text-muted">Actividad (Nivel 2)</label>
      <select id="pool-activity-filter" class="form-control" onchange="loadPoolList(true)">
        <option value="">-- Seleccionar Actividad --</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="small font-weight-bold text-muted">Buscador Palabra Clave (Global)</label>
      <input type="text" id="pool-search-filter" class="form-control" placeholder="Buscar en código, marca, actividad, detalle..." onkeyup="loadPoolList(true)">
    </div>
  </div>
  
  <div class="table-responsive">
    <table class="table table-bordered table-hover table-striped table-premium" id="pool-table">
      <thead>
        <tr>
          <th>Código ID</th>
          <th>Marca/Categoría</th>
          <th>Actividad (Grupo)</th>
          <th>Detalle de Tarea</th>
          <th class="text-center">N1</th>
          <th class="text-center">N2</th>
          <th class="text-center">N3</th>
          <th class="text-center">E1</th>
          <th class="text-center">E2</th>
          <th>Horas Lab</th>
          <th>H. 50%</th>
          <th>H. 100%</th>
          <th>Observaciones</th>
          <th class="text-right" style="width: 150px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="pool-table-body">
        <!-- Dynamic content -->
      </tbody>
    </table>
  </div>
  
  <div id="pool-pagination" class="d-flex justify-content-between align-items-center mt-3">
    <!-- Dynamic pagination rendered via JS -->
  </div>
</div>
