<?php
require_once 'config.php';
require_once __DIR__ . '/includes/secciones_helper.php';

ensureSeccionesCorreo($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de sección no válido.");

$stmt = $pdo->prepare("SELECT * FROM secciones WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) die("Sección no encontrada.");

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
        $stmtUp = $pdo->prepare(
            "UPDATE secciones SET nombre = ?, descripcion = ?, correo = ? WHERE id = ?"
        );
        $stmtUp->execute([$nombre, $descripcion, $correo !== '' ? $correo : null, $id]);

        header("Location: admin_catalogos.php?tab=secciones");
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4">
    <h2>Editar sección</h2>

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
            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($_POST['descripcion'] ?? ($t['descripcion'] ?? '')) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo" class="form-control"
                   value="<?= htmlspecialchars($_POST['correo'] ?? ($t['correo'] ?? '')) ?>"
                   placeholder="seccion@dominio.com">
        </div>

        <button class="btn btn-success">Guardar cambios</button>
        <a href="admin_catalogos.php?tab=secciones" class="btn btn-secondary">Volver</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
