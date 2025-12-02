<?php
require_once 'config.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de ubicación no válido.");

$stmt = $pdo->prepare("SELECT * FROM ubicaciones WHERE id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) die("Ubicación no encontrada.");

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        $errores[] = "El nombre es obligatorio.";
    }

    if (!$errores) {
        $stmtUp = $pdo->prepare(
            "UPDATE ubicaciones SET nombre = ?, descripcion = ? WHERE id = ?"
        );
        $stmtUp->execute([$nombre, $descripcion ?: null, $id]);

        header("Location: admin_catalogos.php?tab=ubicaciones");
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4">
    <h2>Editar ubicación</h2>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control"
                   value="<?= htmlspecialchars($u['nombre']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($u['descripcion'] ?? '') ?></textarea>
        </div>

        <button class="btn btn-success">Guardar cambios</button>
        <a href="admin_catalogos.php?tab=ubicaciones" class="btn btn-secondary">Volver</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
