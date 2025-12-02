<?php
require_once 'config.php';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        $errores[] = "El nombre es obligatorio.";
    }

    if (!$errores) {
        $stmt = $pdo->prepare(
            "INSERT INTO tipos_equipo (nombre, descripcion)
             VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $descripcion ?: null]);

        header("Location: admin_catalogos.php?tab=tipos");
        exit;
    }
}

   // SOLO si NO hubo redirección, cargamos el HTML
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2>Nuevo tipo de equipo</h2>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción (opcional)</label>
            <textarea name="descripcion" class="form-control" rows="3"></textarea>
        </div>

        <button class="btn btn-success">Guardar</button>
        <a href="admin_catalogos.php?tab=tipos" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
