<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';

if (!isset($_GET['id'])) {
    die("ID de equipo no especificado.");
}

$id = intval($_GET['id']);

// Obtener datos del equipo
$sql = "SELECT * FROM equipos WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    die("Equipo no encontrado.");
}

// Obtener IP principal
$sql_ip_principal = "SELECT ip, mac FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1 LIMIT 1";
$stmt_ip = $pdo->prepare($sql_ip_principal);
$stmt_ip->execute(['id' => $id]);
$ip_principal = $stmt_ip->fetch(PDO::FETCH_ASSOC);

// Obtener todas las IPs
$sql_ips = "SELECT ie.ip, ie.mac, r.nombre AS red_nombre, r.direccion_red
            FROM ips_equipos ie
            JOIN redes r ON r.id = ie.red_id
            WHERE ie.equipo_id = :id
            ORDER BY ie.es_principal DESC, ie.id ASC";

$stmt_ips = $pdo->prepare($sql_ips);
$stmt_ips->execute(['id' => $id]);
$ips = $stmt_ips->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Detalle del Equipo</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>

<div class="container mt-4">
    <h2>Detalle del Equipo</h2>
    <hr>

    <div class="mb-3">
        <a href="equipos_list.php" class="btn btn-secondary">Volver al listado</a>
        <a href="averias_list.php?equipo_id=<?= $id ?>" class="btn btn-warning">Ver averías de este equipo</a>
    </div>

    <h4>Información del equipo</h4>
    <table class="table table-bordered">
        <tr><th>ID</th> <td><?= htmlspecialchars($equipo['id']) ?></td></tr>
        <tr><th>Tipo</th> <td><?= htmlspecialchars($equipo['tipo']) ?></td></tr>
        <tr><th>Marca</th> <td><?= htmlspecialchars($equipo['marca']) ?></td></tr>
        <tr><th>Modelo</th> <td><?= htmlspecialchars($equipo['modelo']) ?></td></tr>
        <tr><th>Número de serie</th> <td><?= htmlspecialchars($equipo['numero_serie']) ?></td></tr>
        <tr><th>Hostname</th> <td><?= htmlspecialchars($equipo['hostname']) ?></td></tr>
        <tr><th>Usuario asignado</th> <td><?= htmlspecialchars($equipo['usuario_asignado']) ?></td></tr>
        <tr><th>Departamento</th> <td><?= htmlspecialchars($equipo['departamento']) ?></td></tr>
        <tr><th>Ubicación</th> <td><?= htmlspecialchars($equipo['ubicacion']) ?></td></tr>
        <tr>
    <th>Fecha compra</th>
    <td><?= htmlspecialchars($equipo['fecha_compra'] ?? '') ?></td>
</tr>
<tr>
    <th>Proveedor</th>
    <td><?= htmlspecialchars($equipo['proveedor'] ?? '') ?></td>
</tr>
<tr>
    <th>Coste</th>
    <td>
        <?php if ($equipo['coste'] !== null && $equipo['coste'] !== ''): ?>
            <?= htmlspecialchars($equipo['coste']) ?> €
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr>
        <tr><th>Estado</th> <td><?= htmlspecialchars($equipo['estado']) ?></td></tr>
        <tr><th>Creado en</th> <td><?= htmlspecialchars($equipo['creado_en']) ?></td></tr>
    </table>

    <h4>Notas del equipo</h4>
    <div class="border p-2 mb-4" style="white-space: pre-wrap;">
        <?= !empty($equipo['notas']) ? nl2br(htmlspecialchars($equipo['notas'])) : '<span class="text-muted">Sin notas.</span>' ?>
    </div>

    <h4>IP principal</h4>
    <?php if ($ip_principal): ?>
        <table class="table table-bordered">
            <tr><th>IP</th><td><?= htmlspecialchars($ip_principal['ip']) ?></td></tr>
            <tr><th>MAC</th><td><?= htmlspecialchars($ip_principal['mac']) ?></td></tr>
        </table>
    <?php else: ?>
        <p class="text-muted">Este equipo no tiene IP principal asignada.</p>
    <?php endif; ?>

    <h4>Todas las IPs del equipo</h4>
    <?php if ($ips): ?>
        <div style="overflow-x:auto;">
            <table class="table table-striped table-sm" style="min-width: 900px;">
                <thead>
                <tr>
                    <th>IP</th>
                    <th>MAC</th>
                    <th>Red</th>
                    <th>Dirección red</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ips as $ip): ?>
                    <tr>
                        <td><?= htmlspecialchars($ip['ip']) ?></td>
                        <td><?= htmlspecialchars($ip['mac']) ?></td>
                        <td><?= htmlspecialchars($ip['red_nombre']) ?></td>
                        <td><?= htmlspecialchars($ip['direccion_red']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No hay IPs registradas para este equipo.</p>
    <?php endif; ?>

</div>
</body>
</html>
