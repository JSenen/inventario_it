<?php $title='Portátiles'; $view='laptops/index'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h2>Portátiles</h2>
    <div>
      <a class="btn btn-sm <?= ($show??'available')==='available'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('laptops/index') ?>&show=available">Disponibles</a>
      <a class="btn btn-sm <?= ($show??'available')==='loaned'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('laptops/index') ?>&show=loaned">Prestados</a>
      <a class="btn btn-sm <?= ($show??'available')==='baja'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('laptops/index') ?>&show=baja">Baja</a>
      <a class="btn btn-sm <?= ($show??'available')==='all'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('laptops/index') ?>&show=all">Todos</a>
      <a class="btn btn-sm btn-success" href="<?= url('laptops/create') ?>">+ Nuevo</a>
    </div>
  </div>
<!-- ▶ Resumen compacto -->
  <div class="mb-2" style="display:flex;gap:8px;flex-wrap:wrap">
    <span class="badge bg-secondary">Total: <?= (int)($total ?? count($laptops)) ?></span>

    <?php if (!empty($countsEstado)): ?>
      <?php foreach ($countsEstado as $e): ?>
        <?php
          $label = $e['estado'] === 'prestado' ? 'warning' : 'success';
          $texto = $e['estado'] ?: '—';
        ?>
        <span class="badge bg-<?= $label ?>"><?= htmlspecialchars(ucfirst($texto)) ?>: <?= (int)$e['c'] ?></span>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($countsUso)): ?>
      <?php foreach ($countsUso as $u): ?>
        <span class="badge bg-info text-dark"><?= htmlspecialchars($u['uso']) ?>: <?= (int)$u['c'] ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>Nº Serie</th><th>Marca</th><th>Modelo</th><th>Estado</th><th>Ubicación</th><th style="width:230px">Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($laptops as $l): ?>
      <tr>
        <td><?= htmlspecialchars($l['num_serie']) ?></td>
        <td><?= htmlspecialchars($l['marca']) ?></td>
        <td><?= htmlspecialchars($l['modelo']) ?></td>
        <td><?= htmlspecialchars($l['estado']) ?></td>
        <td><?= htmlspecialchars($l['ubicacion'] ?? '') ?></td>
        <td><?= htmlspecialchars($l['uso_preferente'] ?? '') ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="<?= url('laptops/edit') ?>&id=<?= (int)$l['id'] ?>">Editar</a>

          <?php if ($l['estado'] === 'baja'): ?>
            <form method="post" action="<?= url('laptops/restore') ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="btn btn-sm btn-success">Restaurar</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= url('laptops/archive') ?>" style="display:inline"
                  onsubmit="return confirm('¿Dar de baja el portátil <?= htmlspecialchars($l['num_serie']) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="btn btn-sm btn-warning">Dar de baja</button>
            </form>
          <?php endif; ?>

          <form method="post" action="<?= url('laptops/delete') ?>" style="display:inline"
                onsubmit="return confirm('¿Borrar definitivamente? Esta acción no se puede deshacer.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <button class="btn btn-sm btn-danger" <?= ((int)($l['movimientos']??0)>0)?'disabled title="Tiene histórico"':''; ?>>Borrar</button>
          </form>
        </td>
      </tr>
      

    <?php endforeach; ?>
    </tbody>
  </table>
  <?= pagination_links($total ?? 0, $page ?? 1, $perPage ?? 25, 'laptops/index', ['show'=>$show ?? 'available']) ?>
</div>



