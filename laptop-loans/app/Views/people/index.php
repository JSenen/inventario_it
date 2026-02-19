<?php $title='Personas'; $view='people/index'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h2>Personas</h2>
    <div>
      <a class="btn btn-sm <?= ($show??'active')==='active'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('people/index') ?>&show=active">Activas</a>
      <a class="btn btn-sm <?= ($show??'active')==='archived'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('people/index') ?>&show=archived">Archivadas</a>
      <a class="btn btn-sm <?= ($show??'active')==='all'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('people/index') ?>&show=all">Todas</a>
      <a class="btn btn-sm btn-success" href="<?= url('people/create') ?>">+ Nueva</a>
      <a href="<?= url('people/import') ?>">Importar personas</a>
    </div>
  </div>

  <table class="table">
    <thead><tr>
      <th>Nombre</th><th>Apellidos</th><th>DNI</th><th>TIP</th><th>Teléfono</th><th>Email</th><th >Destino</th><th class="col-actions">Acciones</th>
    </tr></thead>
    <tbody>
    <?php foreach ($people as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['nombre']) ?></td>
        <td><?= htmlspecialchars($p['apellidos']) ?></td>
        <td><?= htmlspecialchars($p['dni']) ?></td>
        <td><?= htmlspecialchars($p['tip']) ?></td>
        <td><?= htmlspecialchars($p['telefono']) ?></td>
        <td><?= htmlspecialchars($p['email']) ?></td>
        <td><?= htmlspecialchars($p['unidad_destino'] ) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="<?= url('people/edit') ?>&id=<?= (int)$p['id'] ?>">Editar</a>
          <?php if ((int)$p['activo']===1): ?>
            <form method="post" action="<?= url('people/archive') ?>" style="display:inline" onsubmit="return confirm('¿Archivar a <?= htmlspecialchars($p['nombre'].' '.$p['apellidos']) ?>?');">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-warning">Archivar</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= url('people/restore') ?>" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-success">Restaurar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      

    <?php endforeach; ?>
    </tbody>
  </table>
  <?= pagination_links($total ?? 0, $page ?? 1, $perPage ?? 25, 'people/index', ['show'=>$show ?? 'active']) ?>
</div>
