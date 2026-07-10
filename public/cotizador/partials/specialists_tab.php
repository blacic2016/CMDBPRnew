<!-- TAB 4: SPECIALISTS & RATES -->
<div class="tab-pane fade show active" id="tab-specialists" role="tabpanel">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="text-primary font-weight-bold mb-0">Listado de Especialistas y Estructura de Tarifas</h5>
    <button class="btn btn-primary" onclick="showSpecialistModal()"><i class="fas fa-plus mr-1"></i> Agregar Especialista</button>
  </div>
  
  <div class="alert alert-info py-2 mb-4">
    <i class="fas fa-info-circle mr-1"></i> 
    <strong>Fórmula Costos Internos:</strong> Costo Empresa = Salario * 1.48 (beneficios de ley). 
    Horas Lab = 21 días * 8h * %Utilizable. Costo Hora = (Costo Empresa / Horas Lab) / 0.95 (overhead 5%). 
    PVP Hora = Costo Hora / (1 - Margen Global).
  </div>
  
  <div class="table-responsive">
    <table class="table table-bordered table-hover table-striped table-premium">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Nivel / Tipo</th>
          <th>Rango Salarial</th>
          <th class="text-right">Salario Mensual</th>
          <th>% Utilizable</th>
          <th class="text-right">Costo Empresa</th>
          <th>Horas Lab / Mes</th>
          <th class="text-right">Costo Hora Lab</th>
          <th class="text-right">PVP Hora Lab</th>
          <th class="text-right" style="width: 150px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="specialists-table-body">
        <!-- Dynamic content -->
      </tbody>
    </table>
  </div>
</div>
