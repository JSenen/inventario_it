<?php $title='Editar curso'; $view='courses/edit'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Editar curso</h2>

  <div class="mb-3">
    <label class="form-label">Nombre</label>
    <input name="nombre" class="form-control" required value="<?= htmlspecialchars($course['nombre']) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Fecha inicio</label>
    <input name="fecha_inicio" type="date" class="form-control" value="<?= htmlspecialchars($course['fecha_inicio'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Fecha fin</label>
    <input name="fecha_fin" type="date" class="form-control" value="<?= htmlspecialchars($course['fecha_fin'] ?? '') ?>">
  </div>

  <button class="btn btn-primary">Guardar cambios</button>
  <a class="btn btn-link" href="<?= url('courses/index') ?>">Cancelar</a>
</form>
