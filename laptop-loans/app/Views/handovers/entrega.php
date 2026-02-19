<?php $title='Entrega'; $__view='handovers/entrega'; ?>
<div class="card">
  <h2>Registrar entrega</h2>
  <form method="post">
    <?= csrf_field() ?>

    <div class="card nested-card">
      <h3>Persona</h3>
      <label class="form-label">Selecciona una persona</label>
      <select name="person_id" class="form-select" required>
        <option value="">--</option>
        <?php foreach(($people ?? []) as $p): ?>
          <option value="<?= (int)$p['id'] ?>">
            <?= htmlspecialchars($p['nombre'].' '.$p['apellidos']) ?>
            (<?= htmlspecialchars($p['dni'] ?: $p['tip']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <div class="mt-2">
        <a href="<?= url('people/create') ?>" class="btn btn-outline-secondary btn-sm">+ Nueva persona</a>
      </div>
    </div>

    <div class="card nested-card">
      <h3>Equipo</h3>
      <label class="form-label">Portátil disponible</label>
      <select name="laptop_id" class="form-select" required>
        <option value="">--</option>
        <?php foreach(($laptops ?? []) as $l): ?>
          <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['num_serie']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="mt-2">
        <a href="<?= url('laptops/create') ?>" class="btn btn-outline-secondary btn-sm">+ Nuevo portátil</a>
      </div>

      <label class="form-label mt-3">Curso</label>
      <select name="course_id" class="form-select">
        <option value="">--</option>
        <?php foreach(($courses ?? []) as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="form-label mt-3">Observaciones</label>
      <textarea name="observaciones" rows="3" class="form-control"></textarea>
      <div class="mb-3 mt-3">
        <label class="form-label">Almacén (lugar de entrega)</label>
        <select name="location_id" class="form-select">
          <option value="">— Seleccionar —</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['nombre']) ?> (<?= htmlspecialchars($loc['tipo']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label">Fecha</label>
          <input type="datetime-local" name="fecha" class="form-control">
        </div>
        <div></div>
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Registrar entrega</button>
    </div>
  </form>
</div>
