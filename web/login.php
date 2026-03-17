<?php
session_start();
require_once 'config.php'; 
require_once __DIR__ . '/includes/logger.php';

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
            logActividad(
                $pdo,
                'LOGIN_FALLIDO',
                'Intento con TIP no existente: ' . strtoupper($tip),
                ['modulo' => 'AUTH', 'nivel' => 'SECURITY']
            );
        } else {
            // Comprobar SHA3-256
            $hashIntroducido = hash('sha3-256', $password);

            if (hash_equals($usuario['password_hash'], $hashIntroducido)) {
                // Login OK
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['tip']        = $usuario['tip'];
                $_SESSION['rol']        = $usuario['rol'];
                $_SESSION['play_saloon_sound'] = true;

                logActividad(
                    $pdo,
                    'LOGIN_OK',
                    'Acceso correcto',
                    ['modulo' => 'AUTH', 'nivel' => 'INFO']
                );

                header('Location: index.php');
                exit;
            } else {
                $errores[] = "Usuario o contraseña incorrectos.";
                logActividad(
                    $pdo,
                    'LOGIN_FALLIDO',
                    'Contraseña incorrecta para TIP: ' . strtoupper($tip),
                    ['modulo' => 'AUTH', 'nivel' => 'SECURITY']
                );
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
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: "Inter", "SF Pro Display", "Segoe UI", system-ui, sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(81, 207, 250, 0.12), transparent 26%),
                        radial-gradient(circle at 80% 0%, rgba(255, 99, 146, 0.14), transparent 24%),
                        radial-gradient(circle at 50% 90%, rgba(96, 165, 250, 0.16), transparent 25%),
                        #0b1021;
            color: #e8ecf5;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            overflow: hidden;
        }

        /* floating shapes */
        .bg-shape {
            position: fixed;
            border-radius: 32px;
            filter: blur(48px);
            opacity: 0.5;
            z-index: 0;
        }
        .bg-shape.one { width: 280px; height: 280px; background: #3b82f6; top: 12%; left: 6%; }
        .bg-shape.two { width: 340px; height: 340px; background: #22d3ee; bottom: 8%; right: 4%; }

        .login-shell {
            position: relative;
            width: 100%;
            max-width: 980px;
            border-radius: 18px;
            padding: 26px;
            background: linear-gradient(135deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow:
                0 24px 80px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(255,255,255,0.04),
                0 20px 40px rgba(45, 95, 255, 0.18);
            backdrop-filter: blur(14px);
            z-index: 1;
        }

        .login-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
            align-items: stretch;
        }

        @media (max-width: 900px) {
            .login-grid {
                grid-template-columns: 1fr;
            }
        }

        .hero-pane {
            background: radial-gradient(circle at 30% 20%, rgba(59,130,246,0.28), transparent 55%),
                        radial-gradient(circle at 80% 30%, rgba(16,185,129,0.22), transparent 48%),
                        rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 14px;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-pane::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08), transparent 40%);
            pointer-events: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.75rem;
            background: rgba(59, 130, 246, 0.16);
            color: #cde2ff;
            border: 1px solid rgba(59,130,246,0.35);
        }

        .hero-title {
            margin: 16px 0 6px;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .hero-subtitle {
            color: #a9b7d3;
            max-width: 560px;
            line-height: 1.6;
            font-size: 0.98rem;
        }

        .hero-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .hero-pill {
            padding: 7px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            color: #dfe7ff;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.07);
        }

        .login-card {
            background: rgba(8, 12, 30, 0.75);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 20px 60px rgba(0,0,0,0.45);
            padding: 24px;
        }

        .login-title {
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #e9edf5;
            font-size: 1.05rem;
        }

        .login-subtitle {
            color: #9db0d3;
            margin-top: 2px;
            font-size: 0.9rem;
        }

        .form-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #8da5cc;
            margin-bottom: 6px;
        }

        .form-control {
            background-color: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.12);
            color: #e7ecf5;
            font-size: 0.95rem;
            padding: 11px 12px;
        }

        .form-control:focus {
            background-color: rgba(255,255,255,0.06);
            border-color: #4f9cff;
            box-shadow: 0 0 0 3px rgba(79,156,255,0.25);
            color: #fff;
        }

        .form-control::placeholder {
            color: #7f8bad;
        }

        .btn-primary-modern {
            width: 100%;
            margin-top: 14px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 12px 0;
            font-size: 0.9rem;
            background: linear-gradient(120deg, #2563eb, #22d3ee);
            border: none;
            color: #0b1021;
            box-shadow: 0 18px 40px rgba(34, 211, 238, 0.25);
        }

        .btn-primary-modern:hover {
            filter: brightness(1.08);
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.35);
        }

        .input-hint {
            font-size: 0.78rem;
            color: #8da5cc;
        }

        .login-footer {
            margin-top: 14px;
            font-size: 0.8rem;
            color: #7e8cb1;
            text-align: center;
        }

        .alert {
            font-size: 0.86rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.12);
            color: #fecdd3;
            border-color: rgba(239, 68, 68, 0.35);
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #22d3ee);
            display: grid;
            place-items: center;
            color: #0b1021;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.35);
        }
    </style>
</head>
<body>
<div class="bg-shape one"></div>
<div class="bg-shape two"></div>

<div class="login-shell">
    <div class="login-grid">
        <div class="hero-pane">
            <div class="badge">
                <div class="brand-mark">IT</div>
                Inventario seguro
            </div>
            <div class="hero-title">Accede al panel de Inventario IT</div>
            <div class="hero-subtitle">
                Gestiona equipos, activos, telefonia,SIMs, averias... con visibilidad en tiempo real, auditoría de movimientos y control de accesos reforzado.
            </div>
            <div class="hero-pills">
                <div class="hero-pill">Audit ready</div>
                <div class="hero-pill">Encriptado SHA3</div>
                <div class="hero-pill">Trazabilidad completa</div>
                <div class="hero-pill">Soporte multi‑equipo</div>
            </div>
        </div>

        <div class="login-card">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="brand-mark">IT</div>
                <div>
                    <div class="login-title">Inicio de sesión</div>
                    <div class="login-subtitle">Solo personal autorizado</div>
                </div>
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
                        Identificador corporativo de acceso.
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Contraseña</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="btn btn-primary-modern" type="submit">
                    Entrar
                </button>
            </form>

            <div class="login-footer">
                Inventario IT · Seguridad, trazabilidad y control.
            </div>
        </div>
    </div>
</div>
</body>
</html>
