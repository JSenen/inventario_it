<?php $title='Cursos'; $view='courses/index'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h2>Cursos</h2>
    <div>
      <a class="btn btn-sm <?= ($show??'active')==='active'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('courses/index') ?>&show=active">Activos</a>
      <a class="btn btn-sm <?= ($show??'active')==='archived'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('courses/index') ?>&show=archived">Archivados</a>
      <a class="btn btn-sm <?= ($show??'active')==='all'?'btn-primary':'btn-outline-primary' ?>" href="<?= url('courses/index') ?>&show=all">Todos</a>

      <!-- ⬇️ Excel resumen (opcional) -->
      <a class="btn btn-sm btn-success" href="<?= url('exports/summary') ?>" target="_blank" rel="noopener">⬇️ Excel (Resumen)</a>

      <a class="btn btn-sm btn-success" href="<?= url('courses/create') ?>">+ Nuevo</a>
    </div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Nombre</th><th>Inicio</th><th>Fin</th>
        <th style="width:240px">Acciones</th> <!-- ancho un poco mayor para que quepan los botones -->
      </tr>
    </thead>
    <tbody>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['nombre']) ?></td>
        <td><?= htmlspecialchars(df($c['fecha_inicio'])) ?></td>
        <td><?= htmlspecialchars(df($c['fecha_fin'])) ?></td>
        <td class="d-flex gap-1" style="flex-wrap:wrap">
          <a class="btn btn-sm btn-secondary" href="<?= url('courses/edit') ?>&id=<?= (int)$c['id'] ?>">Editar</a>

          <!-- ⬇️ Excel por curso -->
          <a class="btn btn-sm btn-success"
             href="<?= url('exports/course') ?>&course_id=<?= (int)$c['id'] ?>"
             target="_blank" rel="noopener">
            ⬇️ Excel
          </a>

          <?php if ((int)$c['activo']===1): ?>
            <form method="post" action="<?= url('courses/archive') ?>" style="display:inline" onsubmit="return confirm('¿Archivar el curso «<?= htmlspecialchars($c['nombre']) ?>»?');">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-warning">Archivar</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= url('courses/restore') ?>" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-success">Restaurar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?= pagination_links($total ?? 0, $page ?? 1, $perPage ?? 25, 'courses/index', ['show'=>$show ?? 'active']) ?>
</div>
