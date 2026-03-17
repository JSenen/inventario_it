<?php $title='Nuevo curso'; $view='courses/form'; ?>
<div class="card">
  <h2>Nuevo curso</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <label>Nombre</label><input name="nombre" required>
    <label>Descripción</label><textarea name="descripcion" rows="3"></textarea>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div><label>Inicio</label><input type="date" name="fecha_inicio"></div>
      <div><label>Fin</label><input type="date" name="fecha_fin"></div>
    </div>
    <div style="margin-top:12px"><button>Guardar</button></div>
  </form>
</div>