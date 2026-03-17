<?php $title='Devolución'; $__view='handovers/devolucion'; ?>
<div class="card">
  <h2>Registrar devolución</h2>
  <form method="post">
    <?= csrf_field() ?>

    <div class="card nested-card">
      <label class="form-label">Portátil prestado</label>
      <select name="laptop_id" class="form-select" required>
        <option value="">--</option>
        <?php foreach(($prestados ?? []) as $x): ?>
          <option value="<?= (int)$x['id'] ?>">
            <?= htmlspecialchars($x['num_serie']) ?> — <?= htmlspecialchars($x['nombre'].' '.$x['apellidos']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="form-label mt-3">Fecha</label>
      <input type="datetime-local" name="fecha" class="form-control">
      <div class="mb-3 mt-3">
        <label class="form-label">Almacén (lugar de devolución)</label>
        <select name="location_id" class="form-select" required>
          <option value="">— Seleccionar —</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['nombre']) ?> (<?= htmlspecialchars($loc['tipo']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <label class="form-label mt-3">Observaciones</label>
      <textarea name="observaciones" rows="3" class="form-control"></textarea>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Registrar devolución</button>
    </div>
  </form>
</div>
