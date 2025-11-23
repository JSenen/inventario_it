<?php
require_once 'auth.php';
require_once 'config.php';

// Solo admin
if (($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit("Acceso denegado");
}

$stmt = $pdo->query("
    SELECT * FROM actividad_logs
    ORDER BY id DESC
    LIMIT 200
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container mt-4">
    <h2 class="h4 mb-3">Registro de actividad</h2>

    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>TIP</th>
                <th>Acción</th>
                <th>Detalles</th>
                <th>IP</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= $log['id'] ?></td>
                    <td><?= htmlspecialchars($log['usuario_tip']) ?></td>
                    <td><strong><?= htmlspecialchars($log['accion']) ?></strong></td>
                    <td><?= nl2br(htmlspecialchars($log['detalles'])) ?></td>
                    <td><?= $log['ip'] ?></td>
                    <td><?= $log['creado_en'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
