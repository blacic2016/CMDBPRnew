<!-- TAB 5: SPECIALIST LEVELS + EQUIPMENT CATEGORIES CONFIGURATION -->
<div class="tab-pane fade" id="tab-levels" role="tabpanel">

  <!-- ─── SECCIÓN 1: RANGOS SALARIALES ─────────────────────────────── -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-primary font-weight-bold mb-0">
      <i class="fas fa-sliders-h mr-2"></i> Configuración de Especialistas — Rangos Salariales
    </h5>
    <button class="btn btn-primary font-weight-bold" onclick="showLevelModal()">
      <i class="fas fa-plus mr-1"></i> Agregar Nivel / Rango
    </button>
  </div>
  <p class="text-muted mb-3">
    Defina los sub-niveles (ej. N1 Junior, N2 Intermedio) con sus topes salariales mínimo/máximo y la fórmula de costo correspondiente.
  </p>
  <div class="table-responsive mb-5">
    <table class="table table-bordered table-hover table-striped table-premium">
      <thead>
        <tr>
          <th>Nivel / Sub-Nivel</th>
          <th>Tipo Base (Fórmula)</th>
          <th class="text-right">Salario Mín.</th>
          <th class="text-right">Salario Máx.</th>
          <th>Fórmula Aplicada</th>
          <th class="text-center" style="width:130px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="levels-table-body">
        <!-- Dynamic content loaded via AJAX -->
      </tbody>
    </table>
  </div>

  <hr class="my-4">

  <!-- ─── SECCIÓN 2: CATEGORÍAS DE INVENTARIO DE EQUIPOS ───────────── -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-info font-weight-bold mb-0">
      <i class="fas fa-network-wired mr-2"></i> Categorías de Inventario de Equipos
    </h5>
    <button class="btn btn-info font-weight-bold" onclick="showEqCategoryModal()">
      <i class="fas fa-plus mr-1"></i> Agregar Categoría
    </button>
  </div>
  <p class="text-muted mb-3">
    Administre las categorías disponibles para el inventario de equipos del Diseñador de Cotización (ej. Core, Acceso, WLC, AP…).
    Estas categorías también aparecen como multiplicadores en las actividades del workbook.
  </p>
  <div class="table-responsive">
    <table class="table table-bordered table-hover table-striped table-premium">
      <thead>
        <tr>
          <th>Nombre de Categoría</th>
          <th>Vista Previa (Color)</th>
          <th class="text-center" style="width:130px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="eq-categories-tbody">
        <!-- Dynamic content loaded via AJAX -->
      </tbody>
    </table>
  </div>
  <hr class="my-4">

  <!-- ─── SECCIÓN 3: MARCAS / GRUPOS DE ACTIVIDADES (POOL) ─────────── -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-warning font-weight-bold mb-0">
      <i class="fas fa-tags mr-2"></i> Marcas / Grupos de Actividades (Pool de Servicios)
    </h5>
    <button class="btn btn-warning font-weight-bold text-white" onclick="showPoolBrandModal()">
      <i class="fas fa-plus mr-1"></i> Agregar Marca / Grupo
    </button>
  </div>
  <p class="text-muted mb-3">
    Administre los grupos de nivel 1 del Pool de Servicios (ej. CISCO, ARUBA, HP, VMWARE…).
    Estos grupos aparecen como filtros en el pool, en el modal de selección de actividades y como 
    <strong>separadores de color</strong> en las tablas de implementación/mantenimiento del workbook.
    Al renombrar un grupo, se actualizará automáticamente en todas las actividades del pool.
  </p>
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-hover table-striped table-premium">
      <thead>
        <tr>
          <th>Nombre del Grupo / Marca</th>
          <th>Vista Previa & Uso en Pool</th>
          <th class="text-center" style="width:130px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="pool-brands-tbody">
        <!-- Dynamic content loaded via AJAX -->
      </tbody>
    </table>
  </div>

</div>
