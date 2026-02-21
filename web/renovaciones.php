<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

$sqlUnion = "
    (SELECT
        r.id,
        r.fecha,
        r.ip_move,
        r.mon_accion,
        r.estado_old,
        r.firmado,
        r.firma_token,
        eold.etiqueta AS old_etiqueta,
        eold.marca AS old_marca,
        eold.modelo AS old_modelo,
        eold.numero_serie AS old_sn,
        eold.hostname AS old_host,
        enew.etiqueta AS new_etiqueta,
        enew.marca AS new_marca,
        enew.modelo AS new_modelo,
        enew.numero_serie AS new_sn,
        enew.hostname AS new_host,
        'equipo' AS tipo,
        NULL AS sim_movida,
        NULL AS sim_id,
        NULL AS sim_etiqueta,
        NULL AS sim_numero
    FROM renovaciones r
    JOIN equipos eold ON eold.id = r.equipo_old_id
    JOIN equipos enew ON enew.id = r.equipo_new_id)
    UNION ALL
    (SELECT
        rt.id,
        rt.fecha,
        rt.sim_movida AS ip_move,
        NULL AS mon_accion,
        rt.estado_old,
        rt.firmado,
        rt.firma_token,
        told.etiqueta AS old_etiqueta,
        told.marca AS old_marca,
        told.modelo AS old_modelo,
        told.imei AS old_sn,
        NULL AS old_host,
        tnew.etiqueta AS new_etiqueta,
        tnew.marca AS new_marca,
        tnew.modelo AS new_modelo,
        tnew.imei AS new_sn,
        NULL AS new_host,
        'telefono' AS tipo,
        rt.sim_movida,
        s.id AS sim_id,
        s.etiqueta AS sim_etiqueta,
        s.numero AS sim_numero
    FROM renovaciones_telefonos rt
    JOIN telefonos told ON told.id = rt.tel_old_id
    JOIN telefonos tnew ON tnew.id = rt.tel_new_id
    LEFT JOIN telefono_sim ts ON ts.id = (
        SELECT ts2.id
        FROM telefono_sim ts2
        WHERE ts2.telefono_id = rt.tel_new_id
          AND ts2.fecha_asignacion <= rt.fecha
        ORDER BY ts2.fecha_asignacion DESC, ts2.id DESC
        LIMIT 1
    )
    LEFT JOIN sims s ON s.id = ts.sim_id)
    ORDER BY fecha DESC
";

// Si la tabla de renovaciones_telefonos aún no existe en la BD antigua, la creamos al vuelo
try {
    $stmt = $pdo->query($sqlUnion);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'renovaciones_telefonos') !== false) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS renovaciones_telefonos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tel_old_id INT NOT NULL,
                tel_new_id INT NOT NULL,
                sim_movida TINYINT(1) NOT NULL DEFAULT 0,
                estado_old VARCHAR(20),
                fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
                firma_token VARCHAR(64),
                firma_path VARCHAR(255),
                firmado TINYINT(1) DEFAULT 0,
                firmado_fecha DATETIME NULL,
                KEY idx_token (firma_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $stmt = $pdo->query($sqlUnion);
    } else {
        // si es otro error, relanzamos para no ocultarlo
        throw $e;
    }
}
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
                Histórico completo de renovaciones registradas. Consulta los recibos sin necesidad de localizar antes el equipo.
            </p>

            <div class="table-responsive">
                <table id="tablaRenovaciones" class="table table-striped table-hover table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Equipo/Telef. renovado</th>
                            <th>Equipo/Telef. activo</th>
                            <th>IP/SIM</th>
                            <th>Monitores</th>
                            <th>Estado renovado</th>
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
                                $monLabel = $accionesMon[$r['mon_accion'] ?? ''] ?? ($r['tipo'] === 'telefono' ? 'N/A' : 'Sin cambios');

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
                                    <?php if ($r['tipo'] === 'telefono'): ?>
                                        <?php if (!empty($r['sim_movida']) && !empty($r['sim_id'])): ?>
                                            <?php $simTexto = trim((string)($r['sim_etiqueta'] ?? '')); ?>
                                            <?php if ($simTexto === '') { $simTexto = trim((string)($r['sim_numero'] ?? '')); } ?>
                                            <?php if ($simTexto === '') { $simTexto = 'SIM #' . (int)$r['sim_id']; } ?>
                                            <a href="sims_ver.php?id=<?= (int)$r['sim_id'] ?>" class="sim-numero-destacado text-decoration-none">
                                                <?= htmlspecialchars($simTexto) ?>
                                            </a>
                                        <?php elseif (!empty($r['sim_movida'])): ?>
                                            <span class="badge bg-success">SIM trasladada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin traslado</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($r['ip_move'])): ?>
                                            <span class="badge bg-success">IP trasladada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin traslado</span>
                                        <?php endif; ?>
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
                                    <?php if ($r['tipo'] === 'telefono'): ?>
                                        <a href="recibo_renovacion_telefono.php?id=<?= (int)$r['id'] ?>"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-secondary">
                                            Recibo
                                        </a>
                                    <?php else: ?>
                                        <a href="recibo_renovacion.php?id=<?= (int)$r['id'] ?>"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-secondary">
                                            Recibo
                                        </a>
                                    <?php endif; ?>
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
