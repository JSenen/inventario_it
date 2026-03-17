<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/recibos_pdf_helper.php';
ensureRecibosSchema($pdo);

// Listado de movimientos de equipos
$stmt = $pdo->query("
    SELECT
        m.*,
        e.marca,
        e.modelo,
        e.numero_serie,
        e.hostname
    FROM equipos_movimientos m
    JOIN equipos e ON e.id = m.id_equipo
    ORDER BY m.fecha DESC
");
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Movimientos de equipos</h1>
        <a href="index.php" class="btn btn-sm btn-secondary">← Volver al dashboard</a>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="mb-2 small text-muted">
                Histórico completo de movimientos registrados (entregas, recogidas, préstamos y devoluciones).
            </p>

            <div class="table-responsive">
                <table id="tablaMovimientos" class="table table-striped table-hover table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Equipo</th>
                            <th>Nº Serie</th>
                            <th>Servicio</th>
                            <th>Usuario destino</th>
                            <th>Técnico</th>
                            <th>Estado origen</th>
                            <th>Estado destino</th>
                            <th>Firma</th>
                            <th>Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $m): ?>
                            <?php
                                $tipo = $m['tipo'] ?? '';
                                $badgeTipo = 'secondary';
                                switch ($tipo) {
                                    case 'entrega':
                                        $badgeTipo = 'success';
                                        break;
                                    case 'recogida':
                                        $badgeTipo = 'warning';
                                        break;
                                    case 'prestamo':
                                        $badgeTipo = 'info';
                                        break;
                                    case 'devolucion':
                                        $badgeTipo = 'primary';
                                        break;
                                }

                                $firmado = (int)($m['firmado'] ?? 0) === 1;
                                $firmaLabel = $firmado ? 'Firmado' : 'Pendiente';
                                $firmaBadge = $firmado ? 'success' : 'secondary';

                                $pdfPath   = $m['pdf_path'] ?? '';
                                $firmaTok  = $m['firma_token'] ?? '';
                            ?>
                            <tr>
                                <td><?= date("d-m-y H:i", strtotime(htmlspecialchars($m['fecha'] ?? '')))  ?></td>
                                <td>
                                    <span class="badge bg-<?= $badgeTipo ?>">
                                        <?= htmlspecialchars(ucfirst($tipo)) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(trim(($m['marca'] ?? '') . ' ' . ($m['modelo'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($m['numero_serie'] ?? '') ?></td>
                                <td><?= htmlspecialchars($m['hostname'] ?? '') ?></td>
                                <td><?= htmlspecialchars($m['usuario_destino'] ?? '') ?></td>
                                <td><?= htmlspecialchars($m['tecnico'] ?? '') ?></td>
                                <td><?= htmlspecialchars($m['estado_origen'] ?? '') ?></td>
                                <td><?= htmlspecialchars($m['estado_destino'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-<?= $firmaBadge ?>">
                                        <?= $firmaLabel ?>
                                    </span>
                                    <?php if (!$firmado && $firmaTok): ?>
                                        <br>
                                        <a href="firma.php?token=<?= urlencode($firmaTok) ?>"
                                           class="small d-inline-block mt-1">
                                            Enviar/abrir firma
                                        </a>
                                    <?php elseif ($firmado && $firmaTok): ?>
                                        <br>
                                        <a href="firma.php?token=<?= urlencode($firmaTok) ?>"
                                           class="small d-inline-block mt-1">
                                            Ver formulario de firma
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($pdfPath)): ?>
                                        <a href="<?= htmlspecialchars($pdfPath) ?>"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-secondary">
                                            Recibo PDF
                                        </a>
                                    <?php else: ?>
                                        <a href="recibo_movimiento.php?id=<?= (int)$m['id'] ?>"
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
            </div> <!-- /.table-responsive -->
        </div> <!-- /.card-body -->
    </div> <!-- /.card -->
</div> <!-- /.container-fluid -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.DataTable) {
        $('#tablaMovimientos').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']], // Fecha descendente
            language: {
                url: 'vendor/datatables/i18n/es-ES.json'
            }
        });
    }
});
</script>

<?php
include __DIR__ . '/includes/footer.php';
