<?php $title='Nuevo curso'; $view='courses/create'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h2>Nuevo curso</h2>
    <a class="btn btn-link" href="<?= url('courses/index') ?>">← Volver</a>
  </div>

  <div class="mb-3">
    <label class="form-label">Nombre del curso</label>
    <input name="nombre" class="form-control" required maxlength="150" autofocus>
  </div>

  <div class="row g-3">
    <div class="col-sm-6">
      <label class="form-label">Fecha de inicio</label>
      <input type="date" name="fecha_inicio" class="form-control">
    </div>
    <div class="col-sm-6">
      <label class="form-label">Fecha de fin</label>
      <input type="date" name="fecha_fin" class="form-control">
    </div>
  </div>

  <div class="form-check mt-3">
    <input class="form-check-input" type="checkbox" value="1" id="activo" name="activo" checked>
    <label class="form-check-label" for="activo">
      Curso activo
    </label>
  </div>

  <div class="mt-4">
    <button class="btn btn-primary">Guardar</button>
    <a class="btn btn-outline-secondary" href="<?= url('courses/index') ?>">Cancelar</a>
  </div>
</form>
