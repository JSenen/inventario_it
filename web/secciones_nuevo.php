<?php
require_once 'config.php';
require_once __DIR__ . '/includes/secciones_helper.php';

ensureSeccionesCorreo($pdo);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $correo = trim($_POST['correo'] ?? '');

    if ($nombre === '') {
        $errores[] = "El nombre es obligatorio.";
    }
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no tiene un formato válido.";
    }

    if (!$errores) {
        $stmt = $pdo->prepare(
            "INSERT INTO secciones (nombre, descripcion, correo)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$nombre, $descripcion ?: null, $correo !== '' ? $correo : null]);

        header("Location: admin_catalogos.php?tab=secciones");
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4">
    <h2>Nueva sección</h2>

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
            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo electrónico (opcional)</label>
            <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>" placeholder="seccion@dominio.com">
        </div>

        <button class="btn btn-success">Guardar</button>
        <a href="admin_catalogos.php?tab=secciones" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
