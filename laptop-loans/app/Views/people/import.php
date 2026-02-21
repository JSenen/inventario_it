<?php $title='Importar personas'; $view='people/import'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h2>Importar personas</h2>
    <div>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('people/index') ?>">← Volver</a>
      <a class="btn btn-sm btn-success" href="<?= url('people/template') ?>" target="_blank" rel="noopener">Descargar plantilla</a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (empty($report)): ?>
    <form method="post" enctype="multipart/form-data" class="mt-3">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Archivo Excel/CSV</label>
        <input type="file" name="file" class="form-control" accept=".xlsx,.csv" required>
        <div class="form-text">Primera fila = cabeceras. Soporta XLSX y CSV (delimitador “;”).</div>
      </div>

      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label">Si la persona ya existe…</label>
          <select class="form-select" name="mode">
            <option value="skip" selected>Omitir (no modificar)</option>
            <option value="update">Actualizar campos no vacíos</option>
          </select>
        </div>
        <div class="col-sm-6">
          <label class="form-label">Criterio de duplicado</label>
          <select class="form-select" name="dedup">
            <option value="dni_tip_nombre" selected>DNI → TIP → (Nombre+Apellidos)</option>
            <option value="dni_only">Solo DNI</option>
            <option value="name_lastname">Nombre + Apellidos</option>
          </select>
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary">Importar</button>
      </div>
    </form>
  <?php else: ?>
    <div class="alert alert-info mt-3">
      <strong>Resultado:</strong>
      Total: <?= (int)$report['total'] ?> ·
      Insertados: <?= (int)$report['insertados'] ?> ·
      Actualizados: <?= (int)$report['actualizados'] ?> ·
      Omitidos: <?= (int)$report['omitidos'] ?>
    </div>

    <?php if (!empty($report['errores'])): ?>
      <div class="alert alert-warning">
        <strong>Incidencias:</strong>
        <ul class="mb-0">
          <?php foreach ($report['errores'] as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <a class="btn btn-outline-primary" href="<?= url('people/import') ?>">← Nueva importación</a>
  <?php endif; ?>
</div>
