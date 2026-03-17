<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bajas_definitivas_schema.php';

ensureBajasDefinitivasSchema($pdo);

$idLote = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idLote <= 0) {
    die('Lote no valido.');
}

$stmtLote = $pdo->prepare("
    SELECT *
    FROM bajas_definitivas_lotes
    WHERE id = :id AND estado = 'PENDIENTE'
    LIMIT 1
");
$stmtLote->execute([':id' => $idLote]);
$lote = $stmtLote->fetch(PDO::FETCH_ASSOC);
if (!$lote) {
    die('Lote pendiente no encontrado.');
}

$stmtItems = $pdo->prepare("
    SELECT *
    FROM bajas_definitivas_items
    WHERE lote_id = :lote_id
    ORDER BY activo_tipo ASC, activo_descripcion ASC
");
$stmtItems->execute([':lote_id' => $idLote]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$totalEquipos = 0;
$totalTelefonos = 0;
foreach ($items as $it) {
    if (($it['activo_tipo'] ?? '') === 'equipo') {
        $totalEquipos++;
    } elseif (($it['activo_tipo'] ?? '') === 'telefono') {
        $totalTelefonos++;
    }
}

$logoRel = 'assets/logo_departamento.png';
$logoFs = __DIR__ . '/' . $logoRel;
$tieneLogo = is_file($logoFs);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte Lote Pendiente <?= htmlspecialchars((string)$lote['codigo']) ?></title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f6f8;
            padding: 24px;
            font-size: 13px;
        }
        .doc {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            padding: 26px 28px;
        }
        .doc h1 {
            font-size: 22px;
            margin: 0 0 2px;
        }
        .doc h2 {
            font-size: 15px;
            margin-top: 18px;
            margin-bottom: 8px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 4px;
        }
        .meta-label {
            width: 220px;
            color: #555;
        }
        .no-print {
            display: block;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .doc {
                border: none;
                box-shadow: none;
                border-radius: 0;
                max-width: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="doc">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <div>
                <a href="bajas_definitivas.php" class="btn btn-sm btn-outline-secondary">Volver</a>
            </div>
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
        </div>

        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="d-flex align-items-center gap-3">
                <?php if ($tieneLogo): ?>
                    <img src="<?= htmlspecialchars($logoRel) ?>" alt="Logo" style="max-height:62px;">
                <?php endif; ?>
                <div>
                    <h1>Listado de Lote Pendiente para Baja Definitiva</h1>
                    <div class="text-muted">Lote: <strong><?= htmlspecialchars((string)$lote['codigo']) ?></strong></div>
                </div>
            </div>
            <div class="text-end small">
                <div>Generado: <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></div>
                <div>Estado: <strong><?= htmlspecialchars((string)$lote['estado']) ?></strong></div>
            </div>
        </div>

        <div class="alert alert-info">
            Este es un listado provisional de los elementos en el lote pendiente. Aún no se ha confirmado la baja definitiva.
        </div>

        <h2>Listado de activos en el lote</h2>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Etiqueta</th>
                        <th>Descripción</th>
                        <th>Identificador</th>
                        <th>Estado previo</th>
                        <th>Fecha baja original</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Sin elementos en este lote.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $it): ?>
                            <tr>
                                <td><?= (int)$idx + 1 ?></td>
                                <td><?= htmlspecialchars(($it['activo_tipo'] ?? '') === 'equipo' ? 'Equipo' : 'Teléfono') ?></td>
                                <td><?= htmlspecialchars((string)($it['activo_etiqueta'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($it['activo_descripcion'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($it['activo_identificador'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($it['estado_previo'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($it['fecha_baja_original'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>