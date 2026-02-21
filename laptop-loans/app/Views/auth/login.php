<?php $title='Acceso'; $view='auth/login'; ?>
<form method="post" class="card" style="max-width:420px;margin:0 auto">
  <?= csrf_field() ?>
  <h2>Entrar</h2>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="mb-3"><label class="form-label">Usuario o email</label><input name="user" class="form-control" required autofocus></div>
  <div class="mb-3"><label class="form-label">Contraseña</label><input type="password" name="pass" class="form-control" required></div>
  <button class="btn btn-primary w-100">Entrar</button>
</form>
