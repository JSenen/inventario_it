<?php
require_once 'config.php';



$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de usuario no válido.");
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado.");
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rol   = $_POST['rol'] ?? $usuario['rol'];
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($pass1 !== '' || $pass2 !== '') {
        // Quiere cambiar contraseña
        if ($pass1 !== $pass2) {
            $errores[] = "Las contraseñas no coinciden.";
        } elseif (strlen($pass1) < 6) {
            $errores[] = "La nueva contraseña debe tener al menos 6 caracteres.";
        } else {
            $hash = hash('sha3-256', $pass1);
            $stmtUp = $pdo->prepare(
                "UPDATE usuarios SET rol = ?, password_hash = ? WHERE id = ?"
            );
            $stmtUp->execute([$rol, $hash, $id]);

            header("Location: admin_catalogos.php?tab=usuarios");
            exit;
        }
    } else {
        // Solo cambia el rol
        $stmtUp = $pdo->prepare(
            "UPDATE usuarios SET rol = ? WHERE id = ?"
        );
        $stmtUp->execute([$rol, $id]);

        header("Location: admin_catalogos.php?tab=usuarios");
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4">
    <h2>Editar usuario</h2>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?>
                <p><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">

        <div class="mb-3">
            <label class="form-label">TIP</label>
            <input type="text" class="form-control"
                   value="<?= htmlspecialchars($usuario['tip']) ?>"
                   disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-control">
                <option value="usuario"
                    <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>
                    Usuario
                </option>
                <option value="admin"
                    <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>
                    Administrador
                </option>
            </select>
        </div>

        <hr>

        <p><b>Cambiar contraseña (opcional)</b></p>

        <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="password" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Repetir nueva contraseña</label>
            <input type="password" name="password2" class="form-control">
        </div>

        <button class="btn btn-success">Guardar cambios</button>
        <a href="admin_catalogos.php?tab=usuarios"
           class="btn btn-secondary">Volver</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
