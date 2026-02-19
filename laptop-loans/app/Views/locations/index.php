<?php $title='Ubicaciones'; $view='locations/index'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2>Ubicaciones</h2>
    <a href="<?= url('locations/create') ?>"><button>+ Nueva</button></a>
  </div>

  <table class="table">
    <thead>
      <tr><th>Nombre</th><th>Tipo</th><th>Descripción</th><th style="width:160px">Acciones</th></tr>
    </thead>
    <tbody>
    <?php foreach ($locations as $x): ?>
      <tr>
        <td><?= htmlspecialchars($x['nombre']) ?></td>
        <td><?= htmlspecialchars($x['tipo']) ?></td>
        <td><?= htmlspecialchars($x['descripcion']) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="<?= url('locations/edit') ?>&id=<?= (int)$x['id'] ?>">Editar</a>
          <form method="post" action="<?= url('locations/delete') ?>" style="display:inline"
                onsubmit="return confirm('¿Eliminar la ubicación «<?= htmlspecialchars($x['nombre']) ?>»?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
            <button class="btn btn-sm btn-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
