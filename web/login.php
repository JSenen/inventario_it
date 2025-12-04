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
                $_SESSION['play_saloon_sound'] = true;


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
    <title>Login Inventario IT - Saloon</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                /* vetas de madera */
                repeating-linear-gradient(
                    90deg,
                    #3b2a20 0px,
                    #3b2a20 8px,
                    #35241c 8px,
                    #35241c 16px
                );
            color: #2a1b10;
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
            padding: 24px;
        }

        .saloon-sign {
            text-align: center;
            margin-bottom: 12px;
        }

        .saloon-sign span {
            display: inline-block;
            padding: 6px 22px;
            border-radius: 40px;
            border: 2px solid #fbbf77;
            background: radial-gradient(circle at 30% 10%, #ffe8b3, #e0a35b);
            color: #4a2508;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-size: 0.8rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.45);
        }

        .login-card {
            position: relative;
            background: #f7e0b3;
            background-image:
                radial-gradient(circle at 10% 10%, rgba(255,255,255,0.18), transparent 60%),
                radial-gradient(circle at 80% 0%, rgba(255,255,255,0.12), transparent 55%);
            border-radius: 14px;
            padding: 22px 20px 18px;
            border: 1px solid #c08a4a;
            box-shadow:
                0 22px 40px rgba(0,0,0,0.6),
                0 0 0 2px rgba(71, 37, 14, 0.85);
            overflow: hidden;
        }

        /* Bordes como papel viejo “rasgado” */
        .login-card::before,
        .login-card::after {
            content: "";
            position: absolute;
            left: -15%;
            right: -15%;
            height: 26px;
            background:
                radial-gradient(circle at 10% 150%, transparent 12px, #3b2a20 13px),
                radial-gradient(circle at 90% -50%, transparent 12px, #3b2a20 13px);
            opacity: 0.5;
        }

        .login-card::before {
            top: -16px;
        }

        .login-card::after {
            bottom: -16px;
            transform: rotate(180deg);
        }

        .badge-sheriff {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            margin: 0 auto 12px auto;
            position: relative;
            background: radial-gradient(circle at 30% 25%, #ffe9a6, #d49a32);
            border: 4px solid #f7d49c;
            box-shadow: 0 0 10px rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5a360f;
            font-size: 34px;
            font-weight: 900;
        }

        .badge-sheriff::before,
        .badge-sheriff::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 102px;
            height: 102px;
            border-radius: 50%;
            border: 4px solid rgba(247, 212, 156, 0.7);
            transform: translate(-50%, -50%) rotate(30deg);
        }

        .badge-sheriff::after {
            transform: translate(-50%, -50%) rotate(-30deg);
        }

        .login-title {
            text-align: center;
            margin-bottom: 2px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-size: 1rem;
            color: #3b2310;
        }

        .login-subtitle {
            text-align: center;
            font-size: 0.8rem;
            color: #704321;
            margin-bottom: 10px;
        }

        .divider-rope {
            height: 1px;
            margin: 10px 0 14px;
            background-image: linear-gradient(to right, transparent, #8b5a2b, transparent);
            position: relative;
        }

        .divider-rope::before,
        .divider-rope::after {
            content: "";
            position: absolute;
            top: -3px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #8b5a2b;
        }

        .divider-rope::before { left: 14px; }
        .divider-rope::after  { right: 14px; }

        .form-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #5a2d12;
            margin-bottom: 4px;
        }

        .form-control {
            background-color: #fdf3d7;
            border-color: #c08a4a;
            color: #3b2310;
            font-size: 0.9rem;
        }

        .form-control:focus {
            background-color: #fff7df;
            border-color: #8b5a2b;
            box-shadow: 0 0 0 2px rgba(139, 90, 43, 0.35);
            color: #3b2310;
        }

        .form-control::placeholder {
            color: #b08a5a;
        }

        .input-hint {
            font-size: 0.75rem;
            color: #8b5a2b;
        }

        .btn-saloon {
            margin-top: 12px;
            width: 100%;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 9px 0;
            font-size: 0.8rem;
            background: linear-gradient(to bottom, #b45309, #7c2d12);
            border: 1px solid #4a1c0a;
            color: #fff8e7;
            box-shadow: 0 12px 22px rgba(0,0,0,0.6);
        }

        .btn-saloon:hover {
            background: linear-gradient(to bottom, #c46a17, #8b3a18);
            color: #fffbe9;
        }

        .login-footer {
            text-align: center;
            margin-top: 12px;
            font-size: 0.75rem;
            color: #7a4a22;
        }

        .login-footer span {
            font-weight: 600;
        }

        .alert {
            font-size: 0.8rem;
            padding: 8px 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .alert-danger {
            background-color: #fcd7bf;
            border-color: #d94824;
            color: #7c2d12;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="saloon-sign">
        <span>INVENTARIO IT</span>
    </div>

    <div class="login-card">
        <div class="text-center">
            <div class="badge-sheriff">
                ★
            </div>
            <div class="login-title">Inventario IT</div>
            <div class="login-subtitle">Acceso restringido · Solo personal autorizado</div>
        </div>

        <div class="divider-rope"></div>

        <?php if ($errores): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="mb-3">
                <label class="form-label">TIP</label>
                <input
                    type="text"
                    name="tip"
                    class="form-control"
                    placeholder="Ej: X12345X"
                    autocomplete="username"
                    required
                    autofocus
                >
                <div class="input-hint mt-1">
                    Identificador del agente para entrar en el sistema.
                </div>
            </div>

            <div class="mb-1">
                <label class="form-label">Contraseña</label>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Clave secreta"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button class="btn btn-saloon" type="submit">
                Entrar al saloon
            </button>
        </form>

        <div class="login-footer">
            <span>Inventario IT</span> · Control de equipos y recursos informáticos
        </div>
    </div>
</div>
</body>
</html>
