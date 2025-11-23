<?php
session_start();
require_once 'config.php'; 

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tip      = trim($_POST['tip'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($tip === '' || $password === '') {
        $errores[] = "Debes introducir TIP y contraseña.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE tip = :tip LIMIT 1");
        $stmt->execute([':tip' => $tip]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $errores[] = "Usuario o contraseña incorrectos.";
        } else {
            // Comprobar SHA3-256
            $hashIntroducido = hash('sha3-256', $password);

            if (hash_equals($usuario['password_hash'], $hashIntroducido)) {
                // Login OK
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['tip']        = $usuario['tip'];
                $_SESSION['rol']        = $usuario['rol'];

                header('Location: index.php');
                exit;
            } else {
                $errores[] = "Usuario o contraseña incorrectos.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Login Inventario IT</title>
    <<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 420px;">
    <h1 class="h4 mb-4 text-center">Acceso Inventario IT</h1>

    <div class="text-center mb-4">
    <img src="assets/logo_departamento.png"
         alt="Logo departamento"
         style="max-width: 180px; height: auto;">
</div>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card card-body">
        <div class="mb-3">
            <label class="form-label">TIP</label>
            <input type="text" name="tip" class="form-control" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-primary w-100" type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
