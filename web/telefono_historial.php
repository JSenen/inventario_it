<?php
require_once 'auth.php';
require_once 'config.php';

// ID del teléfono
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de teléfono no válido.");
}

// Datos del teléfono
$stmtTel = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
$stmtTel->execute([':id' => $id]);
$tel = $stmtTel->fetch(PDO::FETCH_ASSOC);

if (!$tel) {
    die("Teléfono no encontrado.");
}

// Historial de SIMs
$sql = "
    SELECT 
        ts.fecha_asignacion,
        ts.fecha_liberacion,
        s.numero,
        s.operador,
        s.iccid
    FROM telefono_sim ts
    JOIN sims s ON s.id = ts.sim_id
    WHERE ts.telefono_id = :id
    ORDER BY ts.fecha_asignacion DESC
";
$stmtHist = $pdo->prepare($sql);
$stmtHist->execute([':id' => $id]);
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Histórico de SIMs</h2>
        <div>
            <a href="telefonos_ver.php?id=<?= (int)$id ?>" class="btn btn-secondary">
                Volver al teléfono
            </a>
        </div>
    </div>

    <div class="mb-2">
        <strong>Teléfono:</strong>
        <?= htmlspecialchars($tel['marca'] . ' ' . $tel['modelo']) ?>
        (IMEI: <?= htmlspecialchars($tel['imei']) ?>)
    </div>

    <?php if (!$historial): ?>
        <div class="alert alert-info mt-3">
            Este teléfono no tiene historial de SIMs registrado.
        </div>
    <?php else: ?>
        <table class="table table-striped table-bordered mt-3">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Operador</th>
                    <th>ICCID</th>
                    <th>Fecha asignación</th>
                    <th>Fecha liberación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['numero']) ?></td>
                        <td><?= htmlspecialchars($h['operador']) ?></td>
                        <td><?= htmlspecialchars($h['iccid']) ?></td>
                        <td><?= htmlspecialchars($h['fecha_asignacion']) ?></td>
                        <td><?= htmlspecialchars($h['fecha_liberacion'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
