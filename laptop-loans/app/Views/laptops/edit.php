<?php $title='Editar portátil'; $view='laptops/edit'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Editar portátil</h2>

  <div class="mb-3">
    <label class="form-label">Nº de serie</label>
    <input name="num_serie" class="form-control" required
           value="<?= htmlspecialchars($laptop['num_serie']) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Marca</label>
    <input name="marca" class="form-control" value="<?= htmlspecialchars($laptop['marca'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Modelo</label>
    <input name="modelo" class="form-control" value="<?= htmlspecialchars($laptop['modelo'] ?? '') ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Uso preferente</label>
    <select name="uso_preferente" class="form-select">
      <option value="">— Seleccionar —</option>
      <?php $up = $laptop['uso_preferente'] ?? ''; ?>
      <?php foreach (($usos ?? []) as $u): ?>
        <option value="<?= htmlspecialchars($u) ?>" <?= $up===$u?'selected':'' ?>><?= htmlspecialchars($u) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Estado</label>
    <?php $est = $laptop['estado'] ?? 'disponible'; ?>
    <select name="estado" class="form-select">
      <option value="disponible" <?= $est==='disponible'?'selected':'' ?>>Disponible</option>
      <option value="prestado"   <?= $est==='prestado'?'selected':'' ?>>Prestado</option>
      <option value="baja"       <?= $est==='baja'?'selected':'' ?>>Baja</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Ubicación (almacén)</label>
    <select name="ubicacion_id" class="form-select">
      <option value="">— Seleccionar —</option>
      <?php foreach ($locations as $loc): ?>
        <option value="<?= (int)$loc['id'] ?>" <?= ((int)($laptop['ubicacion_id']??0)===(int)$loc['id'])?'selected':'' ?>>
          <?= htmlspecialchars($loc['nombre']) ?> (<?= htmlspecialchars($loc['tipo']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="btn btn-primary">Guardar cambios</button>
  <a class="btn btn-link" href="<?= url('laptops/index') ?>">Cancelar</a>
</form>
