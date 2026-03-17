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
    WHERE id = :id
    LIMIT 1
");
$stmtLote->execute([':id' => $idLote]);
$lote = $stmtLote->fetch(PDO::FETCH_ASSOC);
if (!$lote) {
    die('Lote no encontrado.');
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
    <title>Reporte baja definitiva <?= htmlspecialchars((string)$lote['codigo']) ?></title>
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
        .firma-box {
            border-top: 1px solid #111;
            margin-top: 55px;
            padding-top: 6px;
            text-align: center;
            font-size: 12px;
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
                    <h1>Acta de baja definitiva</h1>
                    <div class="text-muted">Lote: <strong><?= htmlspecialchars((string)$lote['codigo']) ?></strong></div>
                </div>
            </div>
            <div class="text-end small">
                <div>Generado: <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></div>
                <div>Estado: <strong><?= htmlspecialchars((string)$lote['estado']) ?></strong></div>
            </div>
        </div>

        <?php if (($lote['estado'] ?? '') !== 'CONFIRMADO'): ?>
            <div class="alert alert-warning">
                Este lote todavia no esta confirmado. El reporte se muestra como borrador.
            </div>
        <?php endif; ?>

        <h2>Datos del traslado</h2>
        <table class="table table-sm table-bordered">
            <tr>
                <th class="meta-label">Codigo de lote</th>
                <td><?= htmlspecialchars((string)($lote['codigo'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Creado en</th>
                <td><?= htmlspecialchars((string)($lote['creado_en'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Creado por</th>
                <td><?= htmlspecialchars((string)($lote['creado_por'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Confirmado en</th>
                <td><?= htmlspecialchars((string)($lote['confirmado_en'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Confirmado por</th>
                <td><?= htmlspecialchars((string)($lote['confirmado_por'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Punto limpio / gestor</th>
                <td><?= htmlspecialchars((string)($lote['punto_limpio'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Transportado por</th>
                <td><?= htmlspecialchars((string)($lote['transportado_por'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Observaciones</th>
                <td><?= nl2br(htmlspecialchars((string)($lote['observaciones'] ?? '-'))) ?></td>
            </tr>
            <tr>
                <th class="meta-label">Resumen</th>
                <td>
                    Total elementos: <strong><?= count($items) ?></strong>
                    | Equipos: <strong><?= $totalEquipos ?></strong>
                    | Telefonos: <strong><?= $totalTelefonos ?></strong>
                </td>
            </tr>
        </table>

        <h2>Listado de activos dados de baja definitiva</h2>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Etiqueta</th>
                        <th>Descripcion</th>
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
                                <td><?= htmlspecialchars(($it['activo_tipo'] ?? '') === 'equipo' ? 'Equipo' : 'Telefono') ?></td>
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

        <h2>Firmas</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="firma-box">Responsable IT</div>
            </div>
            <div class="col-md-4">
                <div class="firma-box">Entrega al punto limpio</div>
            </div>
            <div class="col-md-4">
                <div class="firma-box">Recepcion gestor residuos</div>
            </div>
        </div>
    </div>
</body>
</html>
