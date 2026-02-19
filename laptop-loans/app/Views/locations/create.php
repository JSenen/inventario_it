<?php $title='Nueva ubicación'; $view='locations/create'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Nueva ubicación</h2>

  <div class="mb-3">
    <label class="form-label">Nombre</label>
    <input name="nombre" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Tipo</label>
    <select name="tipo" class="form-select">
      <option>Zona</option>
      <option>Comandancia</option>
      <option selected>Otro</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Descripción</label>
    <input name="descripcion" class="form-control">
  </div>

  <button class="btn btn-primary">Guardar</button>
</form>
