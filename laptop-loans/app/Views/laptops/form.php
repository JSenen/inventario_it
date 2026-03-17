<?php $title='Nuevo portátil'; $view='laptops/form'; ?>
<div class="card">
  <h2>Nuevo portátil</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div><label>Nº de serie</label><input name="num_serie" required></div>
      <div><label>Marca</label><input name="marca"></div>
      <div><label>Modelo</label><input name="modelo"></div>
      <div><label>Estado</label>
        <select name="estado">
          <option value="disponible">disponible</option>
          <option value="prestado">prestado</option>
          <option value="mantenimiento">mantenimiento</option>
          <option value="baja">baja</option>
        </select>
      </div>
    </div>
    <label>Observaciones</label>
    <textarea name="observaciones" rows="3"></textarea>
    <div style="margin-top:12px"><button>Guardar</button></div>
  </form>
</div>