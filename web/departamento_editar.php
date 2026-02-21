<?php
require_once 'config.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de departamento no válido.");

$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) die("Departamento no encontrado.");

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        $errores[] = "El nombre es obligatorio.";
    }

    if (!$errores) {
        $stmtUp = $pdo->prepare(
            "UPDATE departamentos SET nombre = ?, descripcion = ? WHERE id = ?"
        );
        $stmtUp->execute([$nombre, $descripcion ?: null, $id]);

        header("Location: admin_catalogos.php?tab=departamentos");
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4">
    <h2>Editar tipo de equipo</h2>

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

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($t['descripcion'] ?? '') ?></textarea>
        </div>

        <button class="btn btn-success">Guardar cambios</button>
        <a href="admin_catalogos.php?tab=departamentos" class="btn btn-secondary">Volver</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
