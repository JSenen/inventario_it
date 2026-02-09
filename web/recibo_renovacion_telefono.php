<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';

$idRen  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tokenQ = $_GET['token'] ?? '';

if ($idRen <= 0 && $tokenQ === '') {
    die('Faltan parámetros para mostrar el recibo.');
}

$stmtRen = $pdo->prepare("
    SELECT r.*, 
           told.etiqueta AS old_etiqueta,
           told.marca AS old_marca, told.modelo AS old_modelo, told.imei AS old_imei, told.numero_serie AS old_sn,
           told.usuario_asignado AS old_usuario,
           tnew.etiqueta AS new_etiqueta,
           tnew.marca AS new_marca, tnew.modelo AS new_modelo, tnew.imei AS new_imei, tnew.numero_serie AS new_sn,
           tnew.usuario_asignado AS new_usuario,
           tnew.departamento AS new_departamento,
           tnew.ubicacion AS new_ubicacion,
           tnew.seccion AS new_seccion,
           tnew.id AS new_id, told.id AS old_id
    FROM renovaciones_telefonos r
    JOIN telefonos told ON told.id = r.tel_old_id
    JOIN telefonos tnew ON tnew.id = r.tel_new_id
    WHERE (:id > 0 AND r.id = :id) OR (:tok <> '' AND r.firma_token = :tok)
    LIMIT 1
");
$stmtRen->execute([
    ':id'  => $idRen,
    ':tok' => $tokenQ,
]);
$ren = $stmtRen->fetch(PDO::FETCH_ASSOC);

if (!$ren) {
    die('Recibo de renovación de teléfono no encontrado.');
}

// SIM actual del teléfono nuevo (ya trasladada)
$stmtSim = $pdo->prepare("
    SELECT s.numero, s.operador, s.iccid
    FROM telefono_sim ts
    JOIN sims s ON s.id = ts.sim_id
    WHERE ts.telefono_id = :id
      AND ts.fecha_liberacion IS NULL
    ORDER BY ts.fecha_asignacion DESC
    LIMIT 1
");
$stmtSim->execute([':id' => (int)$ren['tel_new_id']]);
$simNueva = $stmtSim->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de renovación de teléfono</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body { padding: 25px; background: #f8f9fa; }
        .documento { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ddd; border-radius: 6px; }
        .section-title { font-weight: bold; border-bottom: 2px solid #000; margin-top: 20px; padding-bottom: 4px; }
        @media print { body { padding: 0; } .no-print { display: none!important; } .documento { border: none; box-shadow: none; } }
    </style>
</head>
<body>
<div class="documento">
    <div class="no-print text-end mb-3">
        <a href="telefonos_ver.php?id=<?= (int)$ren['new_id'] ?>" class="btn btn-secondary btn-sm">Volver al teléfono</a>
        <?php if (!empty($ren['firma_token'])): ?>
            <?php if (!empty($ren['firmado'])): ?>
                <span class="badge bg-success">Firmado</span>
                <?php if (!empty($ren['firma_path'])): ?>
                    <a href="<?= htmlspecialchars($ren['firma_path']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Ver firma</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="firma.php?token=<?= urlencode($ren['firma_token']) ?>" class="btn btn-primary btn-sm">Enviar a firma</a>
            <?php endif; ?>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary btn-sm">Imprimir / PDF</button>
    </div>

    <h2>Recibo de renovación de teléfono móvil</h2>
    <p class="text-muted">Resumen de la operación realizada.</p>

    <div class="section-title">Teléfonos implicados</div>
    <table class="table table-bordered">
        <tr>
            <th style="width:40%;">Teléfono renovado</th>
            <td><?= htmlspecialchars(($ren['old_etiqueta'] ? '['.$ren['old_etiqueta'].'] ' : '') . ($ren['old_marca'] ?? '') . ' ' . ($ren['old_modelo'] ?? '')) ?></td>
        </tr>
        <tr><th>IMEI / Nº serie</th><td><?= htmlspecialchars($ren['old_imei'] ?? '-') ?> / <?= htmlspecialchars($ren['old_sn'] ?? '-') ?></td></tr>
        <tr><th>Usuario</th><td><?= htmlspecialchars($ren['old_usuario'] ?? '-') ?></td></tr>
        <tr><th>Estado tras renovación</th><td><?= htmlspecialchars($ren['estado_old'] ?? 'Baja') ?></td></tr>
        <tr>
            <th>Teléfono que se queda</th>
            <td><?= htmlspecialchars(($ren['new_etiqueta'] ? '['.$ren['new_etiqueta'].'] ' : '') . ($ren['new_marca'] ?? '') . ' ' . ($ren['new_modelo'] ?? '')) ?></td>
        </tr>
        <tr><th>IMEI / Nº serie</th><td><?= htmlspecialchars($ren['new_imei'] ?? '-') ?> / <?= htmlspecialchars($ren['new_sn'] ?? '-') ?></td></tr>
        <tr><th>Usuario</th><td><?= htmlspecialchars($ren['new_usuario'] ?? '-') ?></td></tr>
        <tr><th>Ubicación / Departamento / Sección</th><td><?= htmlspecialchars($ren['new_ubicacion'] ?? '-') ?> / <?= htmlspecialchars($ren['new_departamento'] ?? '-') ?> / <?= htmlspecialchars($ren['new_seccion'] ?? '-') ?></td></tr>
    </table>

    <div class="section-title">Acciones realizadas</div>
    <ul>
        <li>SIM: Se trasladó la SIM del teléfono renovado al nuevo.</li>
        <?php if ($simNueva): ?>
            <li>SIM actual del nuevo: Nº <?= htmlspecialchars($simNueva['numero']) ?> (<?= htmlspecialchars($simNueva['operador']) ?>) · ICCID <?= htmlspecialchars($simNueva['iccid']) ?>.</li>
        <?php endif; ?>
        <li>El teléfono renovado queda en estado <strong>Baja</strong>.</li>
        <li>Se mantienen usuario, ubicación, departamento y sección del dispositivo renovado.</li>
    </ul>

    <div class="section-title">Firmas</div>
    <?php if (!empty($ren['firmado'])): ?>
        <p class="text-success mb-2">Recibo firmado el <?= htmlspecialchars($ren['firmado_fecha'] ?? '') ?>.</p>
        <?php if (!empty($ren['firma_path'])): ?>
            <img src="<?= htmlspecialchars($ren['firma_path']) ?>" alt="Firma" style="max-width:320px;border:1px solid #aaa;padding:4px;">
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted">Pendiente de firma. Puedes enviarlo al usuario con el botón superior "Enviar a firma".</p>
        <div style="border:1px solid #999; height:140px; margin-bottom:20px;"></div>
    <?php endif; ?>
</div>
</body>
</html>
