<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/movimientos_helper.php';

$token = $_GET['token'] ?? '';
if ($token === '') {
    die('Token no válido');
}

$stmt = $pdo->prepare("
    SELECT m.*, e.marca, e.modelo, e.numero_serie, e.hostname
    FROM equipos_movimientos m
    JOIN equipos e ON e.id = m.id_equipo
    WHERE m.firma_token = :t
");
$stmt->execute([':t' => $token]);
$mov = $stmt->fetch(PDO::FETCH_ASSOC);

$ren = null;
if (!$mov) {
    // Intentar con renovación
    $stmtRen = $pdo->prepare("
        SELECT r.*, eold.marca AS old_marca, eold.modelo AS old_modelo, eold.numero_serie AS old_sn,
               enew.marca AS new_marca, enew.modelo AS new_modelo, enew.numero_serie AS new_sn,
               enew.hostname AS new_host
        FROM renovaciones r
        JOIN equipos enew ON enew.id = r.equipo_new_id
        JOIN equipos eold ON eold.id = r.equipo_old_id
        WHERE r.firma_token = :t
    ");
    $stmtRen->execute([':t' => $token]);
    $ren = $stmtRen->fetch(PDO::FETCH_ASSOC);
    if (!$ren) {
        die('Movimiento o renovación no encontrado');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Firma recibo equipo</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="p-3">
    <?php if ($mov): ?>
        <h1 class="h4 mb-3">Firma del recibo de <?= htmlspecialchars($mov['tipo']) ?></h1>
        <p><strong>Equipo:</strong>
            <?= htmlspecialchars($mov['marca'] . ' ' . $mov['modelo']) ?>
            (S/N: <?= htmlspecialchars($mov['numero_serie']) ?>,
             HOST: <?= htmlspecialchars($mov['hostname']) ?>)
        </p>
        <p><strong>Usuario:</strong> <?= htmlspecialchars($mov['usuario_destino']) ?></p>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($mov['fecha']) ?></p>
    <?php else: ?>
        <h1 class="h4 mb-3">Firma del recibo de renovación</h1>
        <p><strong>Equipo renovado:</strong>
            <?= htmlspecialchars($ren['old_marca'].' '.$ren['old_modelo']) ?> (S/N: <?= htmlspecialchars($ren['old_sn']) ?>)
        </p>
        <p><strong>Equipo nuevo:</strong>
            <?= htmlspecialchars($ren['new_marca'].' '.$ren['new_modelo']) ?> (S/N: <?= htmlspecialchars($ren['new_sn']) ?>, HOST: <?= htmlspecialchars($ren['new_host']) ?>)
        </p>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($ren['fecha']) ?></p>
    <?php endif; ?>

    <p>Por favor, firme en el recuadro:</p>

    <canvas id="firma" width="500" height="200" style="border:1px solid #000; touch-action:none;"></canvas>
    <div class="mt-2">
        <button id="borrar" class="btn btn-secondary btn-sm">Borrar</button>
        <button id="guardar" class="btn btn-primary btn-sm">Guardar firma</button>
    </div>

    <div id="msg" class="mt-3"></div>

    <script>
    const canvas = document.getElementById('firma');
    const ctx = canvas.getContext('2d');
    let pintando = false;

    function getPos(e) {
        if (e.touches && e.touches.length > 0) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        } else {
            return { x: e.offsetX, y: e.offsetY };
        }
    }

    function empezar(e) {
        pintando = true;
    }
    function terminar(e) {
        pintando = false;
        ctx.beginPath();
    }
    function dibujar(e) {
        if (!pintando) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.fillStyle = "black";
        ctx.beginPath();
        ctx.arc(pos.x, pos.y, 2, 0, Math.PI*2);
        ctx.fill();
    }

    canvas.addEventListener('mousedown', empezar);
    canvas.addEventListener('mouseup', terminar);
    canvas.addEventListener('mouseleave', terminar);
    canvas.addEventListener('mousemove', dibujar);

    canvas.addEventListener('touchstart', empezar);
    canvas.addEventListener('touchend', terminar);
    canvas.addEventListener('touchcancel', terminar);
    canvas.addEventListener('touchmove', dibujar);

    document.getElementById('borrar').onclick = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    document.getElementById('guardar').onclick = async function() {
        const dataUrl = canvas.toDataURL('image/png');
        const res = await fetch('guardar_firma.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                token: '<?= htmlspecialchars($token, ENT_QUOTES) ?>',
                firma: dataUrl
            })
        });
        const txt = await res.text();
        document.getElementById('msg').innerHTML = txt;
    };
    </script>
</body>
</html>
