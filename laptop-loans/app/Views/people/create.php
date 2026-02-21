<?php $title='Nueva persona'; $view='people/create'; ?>
<form method="post" class="card" style="max-width:720px;margin:0 auto" autocomplete="off">
  <?= csrf_field() ?>
  <h2>Nueva persona</h2>

  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">Nombre</label>
      <input name="nombre" class="form-control" required>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">Apellidos</label>
      <input name="apellidos" class="form-control" required>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 mb-3">
      <label class="form-label">DNI</label>
      <input name="dni" class="form-control" required>
    </div>
    <div class="col-md-4 mb-3">
      <label class="form-label">TIP</label>
      <input name="tip" class="form-control">
    </div>
    <div class="col-md-4 mb-3">
      <label class="form-label">Teléfono</label>
      <input name="telefono" class="form-control">
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control">
  </div>

  <div class="mb-3">
  <label class="form-label">Unidad de destino</label>
  <input name="unidad_destino" class="form-control" placeholder="Comandancia / Unidad">
</div>

  <div class="d-flex gap-2">
    <button class="btn btn-primary">Guardar</button>
    <a class="btn btn-outline-secondary" href="<?= url('people/index') ?>" target="_self">Cancelar</a>
  </div>
</form>
