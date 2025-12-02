<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';

$idMov = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idMov <= 0) {
    die('Movimiento no válido');
}

$stmt = $pdo->prepare("
    SELECT 
        m.*,
        e.marca,
        e.modelo,
        e.numero_serie,
        e.hostname
    FROM equipos_movimientos m
    JOIN equipos e ON e.id = m.id_equipo
    WHERE m.id = :id
");
$stmt->execute([':id' => $idMov]);
$mov = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mov) {
    die('Movimiento no encontrado');
}

$equipoTxt = trim(($mov['marca'] ?? '') . ' ' . ($mov['modelo'] ?? ''));
$equipoTxt .= ' (S/N: ' . ($mov['numero_serie'] ?? '-') . ', HOST: ' . ($mov['hostname'] ?? '-') . ')';

$firmaPath = $mov['firma_path'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo movimiento #<?= htmlspecialchars($mov['id']) ?></title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            font-size: 12px;
            padding: 20px;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .bloque { margin-bottom: 10px; }
        .label { font-weight: bold; }
        .firma {
            margin-top: 40px;
        }
        .firma img {
            max-width: 320px;
            max-height: 180px;
            border: 1px solid #ccc;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 10mm;
            }
        }
    </style>
</head>
<body>

<div class="no-print mb-3 text-end">
    <button class="btn btn-sm btn-secondary" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<h1>Recibo de <?= strtoupper(htmlspecialchars($mov['tipo'])) ?> de equipo informático</h1>

<div class="bloque">
    <span class="label">Fecha del movimiento:</span>
    <?= htmlspecialchars($mov['fecha']) ?>
</div>

<div class="bloque">
    <span class="label">Equipo:</span> <?= htmlspecialchars($equipoTxt) ?><br>
    <span class="label">Estado origen:</span> <?= htmlspecialchars($mov['estado_origen'] ?? '-') ?><br>
    <span class="label">Estado destino:</span> <?= htmlspecialchars($mov['estado_destino'] ?? '-') ?><br>
</div>

<div class="bloque">
    <span class="label">Usuario destino:</span> <?= htmlspecialchars($mov['usuario_destino'] ?? '-') ?><br>
    <span class="label">Técnico:</span> <?= htmlspecialchars($mov['tecnico'] ?? '-') ?><br>
</div>

<?php if (!empty($mov['observaciones'])): ?>
    <div class="bloque">
        <span class="label">Observaciones:</span><br>
        <?= nl2br(htmlspecialchars($mov['observaciones'])) ?>
    </div>
<?php endif; ?>

<div class="bloque">
    <span class="label">Identificador de movimiento:</span>
    #<?= (int)$mov['id'] ?>
</div>

<div class="firma">
    <span class="label">Firma del usuario:</span><br>
    <?php if ($firmaPath && is_file(__DIR__ . '/' . $firmaPath)): ?>
        <img src="<?= htmlspecialchars($firmaPath) ?>" alt="Firma del usuario">
        <div class="mt-2">
            Firmado electrónicamente el
            <?= htmlspecialchars($mov['firmado_fecha'] ?? '') ?>
        </div>
    <?php else: ?>
        <div style="margin-top: 40px;">
            ________________________________<br>
            (pendiente de firma)
        </div>
    <?php endif; ?>
</div>

</body>
</html>
