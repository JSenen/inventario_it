<?php
require_once 'config.php';




$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tip     = trim($_POST['tip'] ?? '');
    $rol     = $_POST['rol'] ?? 'usuario';
    $pass1   = $_POST['password']  ?? '';
    $pass2   = $_POST['password2'] ?? '';

    // Validaciones básicas
    if ($tip === '') {
        $errores[] = "El TIP es obligatorio.";
    }

    if ($pass1 === '' || $pass2 === '') {
        $errores[] = "La contraseña y su confirmación son obligatorias.";
    } elseif ($pass1 !== $pass2) {
        $errores[] = "Las contraseñas no coinciden.";
    } elseif (strlen($pass1) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    }

    // Comprobar TIP duplicado
    if (!$errores) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE tip = ?");
        $stmt->execute([$tip]);
        if ($stmt->fetch()) {
            $errores[] = "Ya existe un usuario con ese TIP.";
        }
    }

    // Insertar si todo OK
    if (!$errores) {
        $hash = hash('sha3-256', $pass1);

        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (tip, password_hash, rol)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$tip, $hash, $rol]);

        header("Location: admin_catalogos.php?tab=usuarios");
        exit;
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Crear usuario</h2>

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
            <input type="text" name="tip" class="form-control"
                   maxlength="20" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-control">
                <option value="usuario">Usuario</option>
                <option value="admin">Administrador</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control"
                   required>
        </div>

        <div class="mb-3">
            <label class="form-label">Repetir contraseña</label>
            <input type="password" name="password2" class="form-control"
                   required>
        </div>

        <button class="btn btn-success">Guardar usuario</button>
        <a href="admin_catalogos.php?tab=usuarios"
           class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>