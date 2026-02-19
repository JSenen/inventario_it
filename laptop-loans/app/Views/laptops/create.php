<?php $title='Nuevo portátil'; $view='laptops/create'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Nuevo portátil</h2>

  <div class="mb-3">
    <label class="form-label">Nº de serie</label>
    <input name="num_serie" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Marca</label>
    <input name="marca" class="form-control">
  </div>
  <div class="mb-3">
    <label class="form-label">Modelo</label>
    <input name="modelo" class="form-control">
  </div>

  <div class="mb-3">
    <label class="form-label">Uso preferente</label>
    <select name="uso_preferente" class="form-select">
      <option value="">— Seleccionar —</option>
      <?php foreach (($usos ?? []) as $u): ?>
        <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-select">
      <option value="disponible" selected>Disponible</option>
      <option value="prestado">Prestado</option>
      <option value="baja">Baja</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Ubicación (almacén)</label>
    <select name="ubicacion_id" class="form-select">
      <option value="">— Seleccionar —</option>
      <?php foreach ($locations as $loc): ?>
        <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['nombre']) ?> (<?= htmlspecialchars($loc['tipo']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="btn btn-primary">Guardar</button>
</form>
