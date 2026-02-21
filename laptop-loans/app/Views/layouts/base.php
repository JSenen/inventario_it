<?php
  $title = $title ?? 'Gestor de Portátiles';
  if (!isset($__view)) { $__view = 'handovers/index'; } // fallback seguro
  $route = $_GET['r'] ?? 'dashboard/index';
  $section = explode('/', $route)[0] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
  <div class="container-fluid">
    <img src="/assets/logo_departamento.png" alt="Logo" class="app-logo">
    <a class="navbar-brand" href="<?= url('handovers/index') ?>">Portátiles Formación</a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarLoans"
            aria-controls="navbarLoans"
            aria-expanded="false"
            aria-label="Alternar navegación">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarLoans">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= in_array($section, ['dashboard', 'handovers'], true) ? 'active' : '' ?>" href="<?= url('handovers/index') ?>">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $section === 'people' ? 'active' : '' ?>" href="<?= url('people/index') ?>">Personas</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $section === 'laptops' ? 'active' : '' ?>" href="<?= url('laptops/index') ?>">Portátiles</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $section === 'courses' ? 'active' : '' ?>" href="<?= url('courses/index') ?>">Cursos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $section === 'locations' ? 'active' : '' ?>" href="<?= url('locations/index') ?>">Ubicaciones</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $route === 'handovers/entrega' ? 'active' : '' ?>" href="<?= url('handovers/entrega') ?>">Entrega</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $route === 'handovers/devolucion' ? 'active' : '' ?>" href="<?= url('handovers/devolucion') ?>">Devolución</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $section === 'receipts' ? 'active' : '' ?>" href="<?= url('receipts/index') ?>">Recibos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/index.php">Volver a Inventario</a>
        </li>
      </ul>

      <?php if (!empty($_SESSION['user'])): ?>
        <span class="navbar-text me-3">
          <?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?>
        </span>
        <a href="<?= url('auth/logout') ?>" class="btn btn-outline-light btn-sm">Salir</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container mb-5">
  <?php include BASE_PATH . "/app/Views/" . $__view . ".php"; ?>
</main>
<script src="<?= asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body></html>
