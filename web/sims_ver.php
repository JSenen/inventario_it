<?php
require_once 'auth.php';
require_once 'config.php';

// Obtener ID de la SIM
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID no válido.");
}

// Buscar la SIM en la base de datos
$stmt = $pdo->prepare("SELECT * FROM sims WHERE id = :id");
$stmt->execute([':id' => $id]);
$sim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sim) {
    die("Tarjeta SIM no encontrada.");
}

// Consultar si está asignada a algún teléfono
$sqlTel = "
    SELECT t.id, t.marca, t.modelo, t.imei
    FROM telefono_sim ts
    JOIN telefonos t ON t.id = ts.telefono_id
    WHERE ts.sim_id = :id
      AND ts.fecha_liberacion IS NULL
    LIMIT 1
";
$stmtTel = $pdo->prepare($sqlTel);
$stmtTel->execute([':id' => $id]);
$telefonoActual = $stmtTel->fetch(PDO::FETCH_ASSOC);

// Consultar si está asignada a un equipo PTI
$sqlEquipo = "
    SELECT e.id, e.marca, e.modelo, e.numero_serie, e.etiqueta, e.hostname
    FROM equipo_sim es
    JOIN equipos e ON e.id = es.equipo_id
    WHERE es.sim_id = :id
      AND es.fecha_liberacion IS NULL
    ORDER BY es.fecha_asignacion DESC
    LIMIT 1
";
$stmtEq = $pdo->prepare($sqlEquipo);
$stmtEq->execute([':id' => $id]);
$equipoActual = $stmtEq->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Tarjeta SIM - <?= htmlspecialchars(($sim['etiqueta'] ? '['.$sim['etiqueta'].'] ' : '') . $sim['numero']) ?></h2>

        <div>
            <a href="sims_editar.php?id=<?= (int)$id ?>" class="btn btn-warning">Editar</a>

            <?php if ($telefonoActual): ?>
                <a href="telefonos_ver.php?id=<?= (int)$telefonoActual['id'] ?>" class="btn btn-info">
                    Ver teléfono asignado
                </a>
            <?php elseif ($equipoActual): ?>
                <a href="equipo_ver.php?id=<?= (int)$equipoActual['id'] ?>" class="btn btn-info">
                    Ver equipo asignado
                </a>
            <?php endif; ?>

            <!-- Cuando implementemos histórico SIM:
            <a href="sim_historial.php?id=<?= (int)$id ?>" class="btn btn-secondary">Historial</a>
            -->

            <a href="sims.php" class="btn btn-secondary">Volver</a>
        </div>
    </div>

    <table class="table table-bordered">
        <tr><th>ID</th> <td><?= (int)$sim['id'] ?></td></tr>
        <tr><th>Etiqueta</th> <td><?= htmlspecialchars($sim['etiqueta'] ?? '') ?></td></tr>
        <tr><th>Número</th> <td><?= htmlspecialchars($sim['numero']) ?></td></tr>
        <tr><th>ICCID</th> <td><?= htmlspecialchars($sim['iccid']) ?></td></tr>
        <tr><th>Operador</th> <td><?= htmlspecialchars($sim['operador']) ?></td></tr>
        <tr><th>Tarifa</th> <td><?= htmlspecialchars($sim['tarifa']) ?></td></tr>
        <tr><th>PIN</th> <td><?= htmlspecialchars($sim['pin'] ?: '-') ?></td></tr>
        <tr><th>PUK</th> <td><?= htmlspecialchars($sim['puk'] ?: '-') ?></td></tr>
        <tr><th>Estado</th> <td><?= htmlspecialchars($sim['estado']) ?></td></tr>
        <tr><th>Fecha alta</th> <td><?= htmlspecialchars($sim['fecha_alta']) ?></td></tr>
        <tr><th>Fecha baja</th> <td><?= htmlspecialchars($sim['fecha_baja'] ?: '-') ?></td></tr>

        <tr>
            <th>Observaciones</th>
            <td><?= nl2br(htmlspecialchars($sim['observaciones'] ?: '-')) ?></td>
        </tr>
    </table>

    <?php if ($telefonoActual): ?>
        <div class="alert alert-success mt-3">
            <a href="sims_editar.php?id=<?= (int)$id ?>" class="btn btn-warning">Editar</a>
            <b>SIM actualmente asignada a:</b><br>
            <a href="telefonos_ver.php?id=<?= (int)$telefonoActual['id'] ?>">
                <?= htmlspecialchars($telefonoActual['marca'] . ' ' . $telefonoActual['modelo']) ?>
                (IMEI: <?= htmlspecialchars($telefonoActual['imei']) ?>)
            </a>
        </div>
    <?php elseif ($equipoActual): ?>
        <div class="alert alert-success mt-3">
            <a href="sims_editar.php?id=<?= (int)$id ?>" class="btn btn-warning">Editar</a>
            <b>SIM actualmente asignada a equipo (PTI):</b><br>
            <a href="equipo_ver.php?id=<?= (int)$equipoActual['id'] ?>">
                <?= htmlspecialchars(($equipoActual['etiqueta'] ? '['.$equipoActual['etiqueta'].'] ' : '') . ($equipoActual['marca'] ?? '') . ' ' . ($equipoActual['modelo'] ?? '')) ?>
                <?php if (!empty($equipoActual['hostname'])): ?>
                    (Servicio: <?= htmlspecialchars($equipoActual['hostname']) ?>)
                <?php endif; ?>
                <?php if (!empty($equipoActual['numero_serie'])): ?>
                    (SN: <?= htmlspecialchars($equipoActual['numero_serie']) ?>)
                <?php endif; ?>
            </a>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-3">
            Esta SIM no está asignada actualmente a ningún teléfono o equipo.
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
