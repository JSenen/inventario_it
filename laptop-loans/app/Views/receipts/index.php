<?php $title='Recibos'; $view='receipts/index'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2>Recibos</h2>
    <!-- Si se  quiere abrir la carpeta publicada (opción symlink), poner también un botón aquí -->
  </div>

  <table class="table">
    <thead><tr>
      <th>Archivo</th><th>Tamaño</th><th>Fecha</th><th style="width:140px">Acciones</th>
    </tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f['name']) ?></td>
        <td><?= number_format($f['size']/1024,1) ?> KB</td>
        <td><?= date('d-m-Y H:i', $f['mtime']) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary"
             href="<?= url('receipts/download') ?>&f=<?= urlencode($f['name']) ?>"
             target="_blank" rel="noopener">Ver</a>
          <a class="btn btn-sm btn-outline-primary"
             href="<?= url('receipts/download') ?>&f=<?= urlencode($f['name']) ?>&dl=1"
             download="<?= htmlspecialchars($f['name']) ?>">Descargar</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
