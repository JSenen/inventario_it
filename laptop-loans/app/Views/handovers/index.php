<?php $title='Histórico de movimientos'; $view='handovers/index'; ?>
<div class="card">
  <h2>Histórico de entregas / devoluciones</h2>
  <table class="table">
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Serie</th><th>Persona</th><th>Curso</th><th>Observaciones</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr>
        <td><?= htmlspecialchars($h['fecha']) ?></td>
        <td><?= htmlspecialchars(strtoupper($h['tipo'])) ?></td>
        <td><?= htmlspecialchars($h['num_serie']) ?></td>
        <td><?= htmlspecialchars($h['nombre'].' '.$h['apellidos']) ?></td>
        <td><?= htmlspecialchars($h['curso']) ?></td>
        <td><?= htmlspecialchars($h['observaciones']) ?></td>
        <td>
  <?php if (!empty($h['recibo_pdf_path'])): ?>
    <a href="<?= url('receipts/ver') ?>&id=<?= (int)$h['id'] ?>" target="_blank">Ver recibo</a>
  <?php else: ?>
    <?php if ($h['tipo']==='entrega'): ?>
      <a href="<?= url('receipts/entrega') ?>&id=<?= (int)$h['id'] ?>" target="_blank">PDF Entrega</a>
    <?php else: ?>
      <a href="<?= url('receipts/devolucion') ?>&id=<?= (int)$h['id'] ?>" target="_blank">PDF Devolución</a>
    <?php endif; ?>
  <?php endif; ?>
</td>

      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= pagination_links($total ?? 0, $page ?? 1, $perPage ?? 25, 'handovers/index') ?>

</div>