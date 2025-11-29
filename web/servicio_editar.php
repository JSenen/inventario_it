<?php
require_once 'config.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de servicio no válido.");

$stmt = $pdo->prepare("SELECT * FROM tipos_servicio WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) die("Tipo de servicio no encontrado.");

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
   

    if ($nombre === '') {
        $errores[] = "El nombre es obligatorio.";
    }

    if (!$errores) {
        $stmtUp = $pdo->prepare(
            "UPDATE tipos_servicio SET nombre = ? WHERE id = ?"
        );
        $stmtUp->execute([$nombre, $id]);

        header("Location: admin_catalogos.php?tab=servicios");
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4">
    <h2>Editar tipo de Servicio</h2>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control"
                   value="<?= htmlspecialchars($t['nombre']) ?>" required>
        </div>

        <button class="btn btn-success">Guardar cambios</button>
        <a href="admin_catalogos.php?tab=servicio" class="btn btn-secondary">Volver</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
