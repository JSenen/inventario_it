<?php $title='Editar ubicación'; $view='locations/edit'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Editar ubicación</h2>

  <div class="mb-3">
    <label class="form-label">Nombre</label>
    <input name="nombre" class="form-control" required
           value="<?= htmlspecialchars($location['nombre']) ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Tipo</label>
    <select name="tipo" class="form-select">
      <?php $t = $location['tipo'] ?? 'Otro'; ?>
      <option value="Zona"        <?= $t==='Zona'?'selected':'' ?>>Zona</option>
      <option value="Comandancia" <?= $t==='Comandancia'?'selected':'' ?>>Comandancia</option>
      <option value="Otro"        <?= $t==='Otro'?'selected':'' ?>>Otro</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Descripción</label>
    <input name="descripcion" class="form-control"
           value="<?= htmlspecialchars($location['descripcion'] ?? '') ?>">
  </div>

  <button class="btn btn-primary">Guardar cambios</button>
  <a class="btn btn-link" href="<?= url('locations/index') ?>">Cancelar</a>
</form>
