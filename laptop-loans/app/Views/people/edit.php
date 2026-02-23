<?php $title='Editar persona'; $view='people/edit'; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Editar persona</h2>

  <div class="mb-3"><label class="form-label">Nombre</label>
    <input name="nombre" class="form-control" required value="<?= htmlspecialchars($person['nombre']) ?>">
  </div>
  <div class="mb-3"><label class="form-label">Apellidos</label>
    <input name="apellidos" class="form-control" required value="<?= htmlspecialchars($person['apellidos']) ?>">
  </div>
  <div class="mb-3"><label class="form-label">DNI</label>
    <input name="dni" class="form-control" required value="<?= htmlspecialchars($person['dni']) ?>">
  </div>
  <div class="mb-3"><label class="form-label">TIP</label>
    <input name="tip" class="form-control" value="<?= htmlspecialchars($person['tip'] ?? '') ?>">
  </div>
  <div class="mb-3"><label class="form-label">Teléfono</label>
    <input name="telefono" class="form-control" value="<?= htmlspecialchars($person['telefono'] ?? '') ?>">
  </div>
  <div class="mb-3"><label class="form-label">Email</label>
    <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($person['email'] ?? '') ?>">
  </div>
  <div class="mb-3">
  <label class="form-label">Unidad de destino</label>
  <input name="unidad_destino" class="form-control"
         value="<?= htmlspecialchars($person['unidad_destino'] ?? '') ?>">
</div>


  <button class="btn btn-primary">Guardar cambios</button>
  <a class="btn btn-link" href="<?= url('people/index') ?>">Cancelar</a>
</form>
