<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . "/includes/logger.php";

// 👇 AÑADE ESTO
$id_equipo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_equipo <= 0) {
    die('ID de equipo no válido');
}

// Si quieres compatibilidad rápida con código antiguo que usa $id:
$id = $id_equipo;

// Obtener datos del equipo
$stmt = $pdo->prepare("SELECT * FROM equipos WHERE id = :id");
$stmt->execute([':id' => $id_equipo]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    die('Equipo no encontrado');
}

// Inicializar variables para monitores o PC asociado
$monitores_pc = [];
$pc_asociado  = null;

// Si es PC o PORTÁTIL → listar monitores
if (in_array($equipo['tipo'], ['PC','PORTÁTIL'])) {
    $sqlMon = "SELECT e.*
               FROM pc_monitores pm
               JOIN equipos e ON e.id = pm.id_monitor
               WHERE pm.id_pc = :id_pc
               ORDER BY e.marca, e.modelo, e.numero_serie";
    $stmtMon = $pdo->prepare($sqlMon);
    $stmtMon->execute([':id_pc' => $equipo['id']]);
    $monitores_pc = $stmtMon->fetchAll(PDO::FETCH_ASSOC);
}

// Si es MONITOR → ver a qué PC está asignado
if ($equipo['tipo'] === 'MONITOR') {
    $sqlPc = "SELECT e.*
              FROM pc_monitores pm
              JOIN equipos e ON e.id = pm.id_pc
              WHERE pm.id_monitor = :id_monitor";
    $stmtPc = $pdo->prepare($sqlPc);
    $stmtPc->execute([':id_monitor' => $equipo['id']]);
    $pc_asociado = $stmtPc->fetch(PDO::FETCH_ASSOC);
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


// Materiales instalados / consumidos en este equipo (SALIDAS)
$sqlMat = "
    SELECT 
        mm.*,
        m.referencia,
        m.descripcion,
        m.unidad
    FROM materiales_movimientos mm
    JOIN materiales m ON m.id = mm.material_id
    WHERE mm.equipo_id = :equipo_id
      AND mm.tipo = 'SALIDA'
    ORDER BY mm.fecha DESC, mm.id DESC
";
$stmtMat = $pdo->prepare($sqlMat);
$stmtMat->execute([':equipo_id' => $equipo['id']]);
$materiales_instalados = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

$ultimoMov = null;
if (isset($_GET['mov']) && $_GET['mov'] === 'last') {
    $stmtMov = $pdo->prepare("
        SELECT * FROM equipos_movimientos
        WHERE id_equipo = :id
        ORDER BY fecha DESC
        LIMIT 1
    ");
    $stmtMov->execute([':id' => $id_equipo]);
    $ultimoMov = $stmtMov->fetch(PDO::FETCH_ASSOC);
}

logActividad($pdo, 'VER_EQUIPO', 'Detalle del equipo visualizado: ID=' . $id);

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
<?php if ($ultimoMov): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
            Se ha generado un recibo de <strong><?= htmlspecialchars($ultimoMov['tipo']) ?></strong>
            para este equipo (<?= htmlspecialchars($ultimoMov['fecha']) ?>).
        </div>
        <div class="btn-group btn-group-sm">
            <a href="recibo_movimiento.php?id=<?= (int)$ultimoMov['id'] ?>"
            target="_blank"
            class="btn btn-outline-secondary">
                Ver recibo
            </a>

            <?php if (!empty($ultimoMov['firma_token'])): ?>
                <a href="firma.php?token=<?= urlencode($ultimoMov['firma_token']) ?>"
                   class="btn btn-primary">
                    Firmar digitalmente
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">Volver al listado</a>
        <a href="averias_list.php?equipo_id=<?= $id ?>" class="btn btn-warning">Ver averías de este equipo</a>
    </div>

    <h4>Información del equipo</h4>
    <table class="table table-bordered">
        <tr><th>ID</th> <td> (id) <?= htmlspecialchars($equipo['id']) ?> (etiqueta) <?= htmlspecialchars($equipo['etiqueta'] ?? '') ?></td></tr>
        <?php if (!empty($equipo['imagen'])): ?>
        <tr>
            <th>Imagen</th>
            <td>
                <img src="<?= htmlspecialchars($equipo['imagen']) ?>"
                     alt="Imagen del equipo"
                     style="max-width: 250px; height: auto;">
            </td>
        </tr>
    <?php endif; ?>
        <tr><th>Tipo</th> <td><?= htmlspecialchars($equipo['tipo']) ?></td></tr>
        <tr><th>Marca</th> <td><?= htmlspecialchars($equipo['marca']) ?></td></tr>
        <tr><th>Modelo</th> <td><?= htmlspecialchars($equipo['modelo']) ?></td></tr>
        <tr><th>Número de serie</th> <td><?= htmlspecialchars($equipo['numero_serie']) ?></td></tr>
        <tr><th>Servicio</th> <td><?= htmlspecialchars($equipo['hostname']) ?></td></tr>
        <tr><th>Usuario asignado</th> <td><?= htmlspecialchars($equipo['usuario_asignado']) ?></td></tr>
        <tr><th>Departamento</th> <td><?= htmlspecialchars($equipo['departamento']) ?></td></tr>
        <tr><th>Ubicación</th> <td><?= htmlspecialchars($equipo['ubicacion']) ?></td></tr>
        <?php if (in_array($equipo['tipo'], ['PC','PORTÁTIL'])): ?>
<tr>
    <th>Monitores asociados</th>
    <td>
        <?php if (!empty($monitores_pc)): ?>
            <ul class="mb-0">
                <?php foreach ($monitores_pc as $m): ?>
                    <li>
                        <a href="equipo_ver.php?id=<?= (int)$m['id'] ?>">
                            <?= htmlspecialchars(($m['marca'] ?? '').' '.($m['modelo'] ?? '')) ?>
                            <?php if (!empty($m['numero_serie'])): ?>
                                (SN: <?= htmlspecialchars($m['numero_serie']) ?>)
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <span class="text-muted">Sin monitores asociados</span>
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>

<?php if ($equipo['tipo'] === 'MONITOR'): ?>
<tr>
    <th>Asignado a PC</th>
    <td>
        <?php if ($pc_asociado): ?>
            <a href="equipo_ver.php?id=<?= (int)$pc_asociado['id'] ?>">
                <?= htmlspecialchars(
                    $pc_asociado['hostname']
                    ?: (($pc_asociado['marca'] ?? '').' '.($pc_asociado['modelo'] ?? ''))
                ) ?>
            </a>
        <?php else: ?>
            <span class="text-muted">No asignado</span>
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>

        <tr>
    <th>Fecha Alta</th>
    <td><?= htmlspecialchars($equipo['fecha_compra'] ?? '') ?></td>
</tr>
<tr><th>Fecha de baja</th> 
    <td>
        <?= $equipo['fecha_baja'] ? htmlspecialchars($equipo['fecha_baja']) : '<span class="text-muted">-</span>' ?>
    </td>
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
<h4 class="mt-4">Material instalado / consumido</h4>

<?php if (!empty($materiales_instalados)): ?>
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Referencia</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Usuario</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($materiales_instalados as $mi): ?>
            <tr>
                <td><?= htmlspecialchars($mi['fecha']) ?></td>
                <td><?= htmlspecialchars($mi['referencia']) ?></td>
                <td><?= htmlspecialchars($mi['descripcion']) ?></td>
                <td><?= (int)$mi['cantidad'] . ' ' . htmlspecialchars($mi['unidad']) ?></td>
                <td><?= htmlspecialchars($mi['usuario']) ?></td>
                <td><?= htmlspecialchars($mi['motivo']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="text-muted">No hay registros de material asociado a este equipo.</p>
<?php endif; ?>

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
