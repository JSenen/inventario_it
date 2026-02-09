<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

$stmt = $pdo->query("
    SELECT
        r.*,
        eold.etiqueta AS old_etiqueta,
        eold.marca AS old_marca,
        eold.modelo AS old_modelo,
        eold.numero_serie AS old_sn,
        eold.hostname AS old_host,
        enew.etiqueta AS new_etiqueta,
        enew.marca AS new_marca,
        enew.modelo AS new_modelo,
        enew.numero_serie AS new_sn,
        enew.hostname AS new_host
    FROM renovaciones r
    JOIN equipos eold ON eold.id = r.equipo_old_id
    JOIN equipos enew ON enew.id = r.equipo_new_id
    ORDER BY r.fecha DESC
    LIMIT 500
");
$renovaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$accionesMon = [
    'trasladar' => 'Traslado al nuevo equipo',
    'almacen'   => 'Envío a Almacén',
    'baja'      => 'Baja de monitores',
];

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Recibos de renovaciones</h1>
        <a href="index.php" class="btn btn-sm btn-secondary">← Volver al dashboard</a>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="mb-2 small text-muted">
                Últimas 500 renovaciones registradas. Consulta los recibos sin necesidad de localizar antes el equipo.
            </p>

            <div class="table-responsive">
                <table id="tablaRenovaciones" class="table table-striped table-hover table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Equipo renovado</th>
                            <th>Equipo activo</th>
                            <th>IP principal</th>
                            <th>Monitores</th>
                            <th>Estado equipo renovado</th>
                            <th>Firma</th>
                            <th>Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($renovaciones as $r): ?>
                            <?php
                                $firmado = (int)($r['firmado'] ?? 0) === 1;
                                $firmaBadge = $firmado ? 'success' : 'secondary';
                                $firmaLabel = $firmado ? 'Firmado' : 'Pendiente';
                                $monLabel = $accionesMon[$r['mon_accion'] ?? ''] ?? 'Sin cambios';

                                $oldTitulo = trim(($r['old_marca'] ?? '') . ' ' . ($r['old_modelo'] ?? ''));
                                if (!empty($r['old_etiqueta'])) {
                                    $oldTitulo = '[' . $r['old_etiqueta'] . '] ' . $oldTitulo;
                                }

                                $newTitulo = trim(($r['new_marca'] ?? '') . ' ' . ($r['new_modelo'] ?? ''));
                                if (!empty($r['new_etiqueta'])) {
                                    $newTitulo = '[' . $r['new_etiqueta'] . '] ' . $newTitulo;
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['fecha'] ?? '') ?></td>
                                <td>
                                    <div><?= htmlspecialchars($oldTitulo) ?></div>
                                    <div class="text-muted small">
                                        <?= !empty($r['old_sn']) ? 'SN: ' . htmlspecialchars($r['old_sn']) : 'SN: -' ?>
                                        <?php if (!empty($r['old_host'])): ?>
                                            · Servicio: <?= htmlspecialchars($r['old_host']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($newTitulo) ?></div>
                                    <div class="text-muted small">
                                        <?= !empty($r['new_sn']) ? 'SN: ' . htmlspecialchars($r['new_sn']) : 'SN: -' ?>
                                        <?php if (!empty($r['new_host'])): ?>
                                            · Servicio: <?= htmlspecialchars($r['new_host']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($r['ip_move'])): ?>
                                        <span class="badge bg-success">Trasladada</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin traslado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($monLabel) ?></td>
                                <td><?= htmlspecialchars($r['estado_old'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-<?= $firmaBadge ?>">
                                        <?= $firmaLabel ?>
                                    </span>
                                    <?php if (!$firmado && !empty($r['firma_token'])): ?>
                                        <br>
                                        <a href="firma.php?token=<?= urlencode($r['firma_token']) ?>"
                                           class="small d-inline-block mt-1">
                                            Enviar/abrir firma
                                        </a>
                                    <?php elseif ($firmado && !empty($r['firma_token'])): ?>
                                        <br>
                                        <a href="firma.php?token=<?= urlencode($r['firma_token']) ?>"
                                           class="small d-inline-block mt-1">
                                            Ver formulario de firma
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="recibo_renovacion.php?id=<?= (int)$r['id'] ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-secondary">
                                        Recibo
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.DataTable) {
        $('#tablaRenovaciones').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            language: {
                url: 'vendor/datatables/i18n/es-ES.json'
            }
        });
    }
});
</script>

<?php
include __DIR__ . '/includes/footer.php';
