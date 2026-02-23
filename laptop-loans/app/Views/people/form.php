<?php $title='Nueva persona'; $view='people/form'; ?>
<div class="card">
  <h2>Nueva persona</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div><label>Nombre</label><input name="nombre" required></div>
      <div><label>Apellidos</label><input name="apellidos" required></div>
      <div><label>DNI</label><input name="dni" required></div>
      <div><label>TIP</label><input name="tip"></div>
      <div><label>Teléfono</label><input name="telefono"></div>
      <div><label>Email</label><input name="email" type="email"></div>
      <div><label>Unidad de Desstino</label><input name="unidad_destino" type="unidad_destino"></div>
      <div>
    </div>
    <div style="margin-top:12px"><button>Guardar</button></div>
  </form>
</div>