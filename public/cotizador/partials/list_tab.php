<!-- TAB 1: LIST & HISTORY OF QUOTES -->
<div class="tab-pane fade <?php echo (isset($active_tab) && $active_tab === 'list') ? 'show active' : ''; ?>" id="tab-list" role="tabpanel">
  <div class="row mb-4">
    <!-- Filters -->
    <div class="col-md-4">
      <input type="text" id="filter-client" class="form-control" placeholder="Buscar por Cliente...">
    </div>
    <div class="col-md-3">
      <select id="filter-status" class="form-control">
        <option value="">-- Todos los Estados --</option>
        <option value="Borrador">Borrador</option>
        <option value="Enviada">Enviada (Aprobada)</option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="month" id="filter-month" class="form-control" title="Filtrar por Mes">
    </div>
    <div class="col-md-2 text-right">
      <button class="btn btn-primary btn-block" onclick="startNewQuote()"><i class="fas fa-plus mr-1"></i> Nueva Cotización</button>
    </div>
  </div>
  
  <div class="alert alert-light border py-2 d-flex justify-content-between align-items-center mb-3">
    <span><i class="fas fa-info-circle mr-1 text-info"></i> Seleccione dos cotizaciones o versiones del listado para compararlas de forma instantánea.</span>
    <button class="btn btn-outline-primary btn-sm d-none" id="btn-compare-selected" onclick="compareSelectedQuotes()"><i class="fas fa-columns mr-1"></i> Comparar Seleccionados</button>
  </div>
  
  <!-- Quotes Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover table-striped table-premium">
      <thead>
        <tr>
          <th style="width: 40px;">Comp</th>
          <th>Cliente</th>
          <th>Contrato / Proyecto</th>
          <th>Fecha</th>
          <th>Margen</th>
          <th>Costo Total</th>
          <th>Precio de Venta (PVP)</th>
          <th>Estado</th>
          <th>Versión</th>
          <th class="text-right" style="width: 250px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="quotes-table-body">
        <tr>
          <td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando cotizaciones...</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
