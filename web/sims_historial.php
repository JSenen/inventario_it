<?php
require_once 'auth.php';
require_once 'config.php';

// ID de la SIM
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de SIM no válido.");
}

// Datos de la SIM
$stmtSim = $pdo->prepare("SELECT * FROM sims WHERE id = :id");
$stmtSim->execute([':id' => $id]);
$sim = $stmtSim->fetch(PDO::FETCH_ASSOC);

if (!$sim) {
    die("Tarjeta SIM no encontrada.");
}

// Historial: todos los teléfonos donde ha estado
$sql = "
    SELECT
        ts.fecha_asignacion,
        ts.fecha_liberacion,
        t.id AS telefono_id,
        t.marca,
        t.modelo,
        t.imei,
        t.usuario_asignado,
        t.departamento
    FROM telefono_sim ts
    JOIN telefonos t ON t.id = ts.telefono_id
    WHERE ts.sim_id = :sim_id
    ORDER BY ts.fecha_asignacion DESC
";
$stmtHist = $pdo->prepare($sql);
$stmtHist->execute([':sim_id' => $id]);
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Histórico de uso de la SIM</h2>
        <div>
            <a href="sims_ver.php?id=<?= (int)$id ?>" class="btn btn-secondary">
                Volver a la SIM
            </a>
        </div>
    </div>

    <div class="mb-2">
        <strong>SIM:</strong>
        Nº <?= htmlspecialchars($sim['numero']) ?> 
        (Operador: <?= htmlspecialchars($sim['operador']) ?>, ICCID: <?= htmlspecialchars($sim['iccid']) ?>)
    </div>

    <?php if (!$historial): ?>
        <div class="alert alert-info mt-3">
            Esta SIM no tiene historial de asignaciones registrado.
        </div>
    <?php else: ?>
        <table class="table table-striped table-bordered mt-3">
            <thead>
                <tr>
                    <th>Teléfono</th>
                    <th>IMEI</th>
                    <th>Usuario</th>
                    <th>Departamento</th>
                    <th>Fecha asignación</th>
                    <th>Fecha liberación</th>
                    <th>Ver teléfono</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['marca'] . ' ' . $h['modelo']) ?></td>
                        <td><?= htmlspecialchars($h['imei']) ?></td>
                        <td><?= htmlspecialchars($h['usuario_asignado'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($h['departamento'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($h['fecha_asignacion']) ?></td>
                        <td><?= htmlspecialchars($h['fecha_liberacion'] ?: '-') ?></td>
                        <td>
                            <a href="telefonos_ver.php?id=<?= (int)$h['telefono_id'] ?>" class="btn btn-sm btn-info">
                                Ver teléfono
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
