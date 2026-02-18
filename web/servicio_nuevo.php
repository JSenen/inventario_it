<?php
require_once 'config.php';


$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        $errores[] = "El nombre es obligatorio.";
    }

    if (!$errores) {
        $stmt = $pdo->prepare(
            "INSERT INTO tipos_servicio (nombre)
             VALUES (?)"
        );
        $stmt->execute([$nombre]);

        header("Location: admin_catalogos.php?tab=servicios");
        exit;
    }
}

?>
<?php require_once 'includes/header.php'; ?>

<div class="container mt-4">
    <h2>Nuevo Tipo de Servicio</h2>

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

        <button class="btn btn-success">Guardar</button>
        <a href="admin_catalogos.php?tab=servicios" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
