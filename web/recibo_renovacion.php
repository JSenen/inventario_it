<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/mail_helper.php';
require_once __DIR__ . '/includes/recibos_pdf_helper.php';
ensureRecibosSchema($pdo);

$idRen  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tokenQ = $_GET['token'] ?? '';

if ($idRen <= 0 && $tokenQ === '') {
    die('Faltan parámetros para mostrar el recibo.');
}

$stmtRen = $pdo->prepare("
    SELECT r.*, eold.etiqueta AS old_etiqueta, eold.marca AS old_marca, eold.modelo AS old_modelo,
           eold.numero_serie AS old_sn, eold.hostname AS old_host, eold.estado AS old_estado,
           enew.etiqueta AS new_etiqueta, enew.marca AS new_marca, enew.modelo AS new_modelo,
           enew.numero_serie AS new_sn, enew.hostname AS new_host,
           enew.ubicacion AS new_ubicacion, enew.departamento AS new_dep, enew.seccion_id AS new_seccion_id,
           snew.nombre AS new_seccion_nombre, snew.correo AS seccion_correo,
           enew.id AS new_id, eold.id AS old_id
    FROM renovaciones r
    JOIN equipos eold ON eold.id = r.equipo_old_id
    JOIN equipos enew ON enew.id = r.equipo_new_id
    LEFT JOIN secciones snew ON snew.id = enew.seccion_id
    WHERE (:id > 0 AND r.id = :id) OR (:tok <> '' AND r.firma_token = :tok)
    LIMIT 1
");
$stmtRen->execute([
    ':id'  => $idRen,
    ':tok' => $tokenQ,
]);
$ren = $stmtRen->fetch(PDO::FETCH_ASSOC);

if (!$ren) {
    die('Recibo de renovación no encontrado.');
}

if (empty($ren['pdf_unsigned_path'])) {
    generarReciboRenovacionPdf($pdo, (int)$ren['id'], false);
}
if (!empty($ren['firmado']) && empty($ren['pdf_signed_path'])) {
    generarReciboRenovacionPdf($pdo, (int)$ren['id'], true);
}

$id_old   = (int)$ren['equipo_old_id'];
$id_new   = (int)$ren['equipo_new_id'];
$ip_move  = (bool)$ren['ip_move'];
$mon_acc  = $ren['mon_accion'] ?? 'trasladar';
$mon_nuevo= (int)($ren['mon_nuevo_id'] ?? 0);
$estado_viejo = $ren['estado_old'] ?? '';

// Info adicional
$stmtIpNew = $pdo->prepare("SELECT ip, mac FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1 LIMIT 1");
$stmtIpNew->execute([':id' => $id_new]);
$ip_new = $stmtIpNew->fetch(PDO::FETCH_ASSOC);

$stmtMons = $pdo->prepare("
    SELECT e.*
    FROM pc_monitores pm
    JOIN equipos e ON e.id = pm.id_monitor
    WHERE pm.id_pc = :id
");
$stmtMons->execute([':id' => $id_new]);
$monitores_nuevos = $stmtMons->fetchAll(PDO::FETCH_ASSOC);

$monitor_extra = null;
if ($mon_nuevo > 0) {
    $stmtME = $pdo->prepare("SELECT * FROM equipos WHERE id = :id");
    $stmtME->execute([':id' => $mon_nuevo]);
    $monitor_extra = $stmtME->fetch(PDO::FETCH_ASSOC);
}

$accionesMon = [
    'trasladar' => 'Se trasladan los monitores del equipo renovado al nuevo',
    'almacen'   => 'Los monitores del equipo renovado se envían a Almacén',
    'baja'      => 'Los monitores del equipo renovado se dan de baja',
];

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
         . $_SERVER['HTTP_HOST'];
$urlFirma = $baseUrl . '/firma.php?token=' . urlencode($ren['firma_token'] ?? '');

$mailOk = null;
$mailMsg = null;
$seccionCorreo = trim((string)($ren['seccion_correo'] ?? ''));
$mailSubject = 'Firma de recibo de renovacion #' . (int)$ren['id'];
$mailBody = "Hola,\n\n" .
    "Se ha generado un recibo de renovación pendiente de firma.\n\n" .
    "Renovación: #" . (int)$ren['id'] . "\n" .
    "Equipo renovado: " . trim(($ren['old_marca'] ?? '') . ' ' . ($ren['old_modelo'] ?? '')) . " (SN: " . ($ren['old_sn'] ?? '-') . ")\n" .
    "Equipo nuevo: " . trim(($ren['new_marca'] ?? '') . ' ' . ($ren['new_modelo'] ?? '')) . " (SN: " . ($ren['new_sn'] ?? '-') . ")\n" .
    "Sección destino: " . ($ren['new_seccion_nombre'] ?? '-') . "\n\n" .
    "Enlace de firma:\n" . $urlFirma . "\n\n" .
    "Mensaje generado automáticamente por Inventario IT.";
$mailtoLink = '';
if ($seccionCorreo !== '' && filter_var($seccionCorreo, FILTER_VALIDATE_EMAIL)) {
    $mailtoLink = 'mailto:' . rawurlencode($seccionCorreo)
        . '?subject=' . rawurlencode($mailSubject)
        . '&body=' . rawurlencode($mailBody);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_correo_seccion'])) {
    if ($seccionCorreo === '' || !filter_var($seccionCorreo, FILTER_VALIDATE_EMAIL)) {
        $mailOk = false;
        $mailMsg = 'La sección no tiene un correo válido configurado.';
    } elseif (empty($ren['firma_token'])) {
        $mailOk = false;
        $mailMsg = 'No hay token de firma disponible para este recibo.';
    } else {
        $err = null;
        $mailOk = sendPlainEmail($seccionCorreo, $mailSubject, $mailBody, $err);
        if ($mailOk) {
            $mailMsg = 'Correo enviado a ' . $seccionCorreo . '.';
            logActividad(
                $pdo,
                'ENVIO_CORREO_RECIBO_RENOV',
                'Renovacion=' . (int)$ren['id'] . '; destino=' . $seccionCorreo,
                ['modulo' => 'RECIBOS', 'nivel' => 'INFO']
            );
        } else {
            $mailMsg = $err ?: 'No se pudo enviar el correo.';
            logActividad(
                $pdo,
                'ENVIO_CORREO_RECIBO_RENOV_FALLO',
                'Renovacion=' . (int)$ren['id'] . '; destino=' . $seccionCorreo . '; motivo=' . $mailMsg,
                ['modulo' => 'RECIBOS', 'nivel' => 'WARN']
            );
        }
    }
}

$mostrarPopupEnvio = ($_SERVER['REQUEST_METHOD'] !== 'POST')
    && empty($ren['firmado'])
    && !empty($ren['firma_token'])
    && ($seccionCorreo !== '')
    && filter_var($seccionCorreo, FILTER_VALIDATE_EMAIL);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de renovación</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body { padding: 25px; background: #f8f9fa; }
        .documento { max-width: 960px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ddd; border-radius: 6px; }
        .section-title { font-weight: bold; border-bottom: 2px solid #000; margin-top: 20px; padding-bottom: 4px; }
        @media print { body { padding: 0; } .no-print { display: none!important; } .documento { border: none; box-shadow: none; } }
    </style>
</head>
<body>
<div class="documento">
    <div class="no-print text-end mb-3">
        <form id="formEnviarCorreoSeccion" method="post" class="d-inline">
            <input type="hidden" name="enviar_correo_seccion" value="1">
            <button type="submit" class="btn btn-outline-primary btn-sm">Enviar correo a sección</button>
        </form>
        <a href="equipo_ver.php?id=<?= (int)$id_new ?>" class="btn btn-secondary btn-sm">Volver al equipo</a>
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
    <?php if ($mailMsg !== null): ?>
        <div class="alert alert-<?= $mailOk ? 'success' : 'warning' ?> no-print">
            <?= htmlspecialchars($mailMsg) ?>
            <?php if (!$mailOk && $mailtoLink !== ''): ?>
                <a href="<?= htmlspecialchars($mailtoLink) ?>" class="ms-2">Abrir cliente de correo</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Recibo de renovación de equipo</h2>
    <p class="text-muted">Resumen de la operación realizada.</p>

    <div class="section-title">Equipos implicados</div>
    <table class="table table-bordered">
        <tr>
            <th style="width:40%;">Equipo renovado</th>
            <td><?= htmlspecialchars(($ren['old_etiqueta'] ? '['.$ren['old_etiqueta'].'] ' : '').($ren['old_marca'] ?? '').' '.($ren['old_modelo'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>N.º serie / Servicio</th>
            <td><strong><?= htmlspecialchars($ren['old_sn'] ?? '-') ?></strong> / <?= htmlspecialchars($ren['old_host'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Estado tras renovación</th>
            <td><?= htmlspecialchars($estado_viejo ?: ($ren['old_estado'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Equipo que se queda</th>
            <td><?= htmlspecialchars(($ren['new_etiqueta'] ? '['.$ren['new_etiqueta'].'] ' : '').($ren['new_marca'] ?? '').' '.($ren['new_modelo'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>N.º serie / Servicio</th>
            <td><strong><?= htmlspecialchars($ren['new_sn'] ?? '-') ?></strong> / <?= htmlspecialchars($ren['new_host'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Ubicación / Departamento / Sección</th>
            <td><?= htmlspecialchars($ren['new_ubicacion'] ?? '-') ?> / <?= htmlspecialchars($ren['new_dep'] ?? '-') ?> / <?= htmlspecialchars($ren['new_seccion_nombre'] ?? ('ID ' . ($ren['new_seccion_id'] ?? '-'))) ?></td>
        </tr>
    </table>

    <div class="section-title">Acciones realizadas</div>
    <ul>
        <li>IP principal: <?= $ip_move ? 'Se trasladó la IP del equipo renovado al nuevo' : 'No se trasladó la IP' ?><?php if ($ip_new): ?> (IP actual nuevo: <?= htmlspecialchars($ip_new['ip']) ?>)<?php endif; ?></li>
        <li>Monitores: <?= htmlspecialchars($accionesMon[$mon_acc] ?? 'No se modificaron') ?></li>
        <?php if (!empty($monitores_nuevos)): ?>
            <li>Monitores actualmente vinculados al nuevo:
                <ul>
                    <?php foreach ($monitores_nuevos as $m): ?>
                        <li>
                            <?= htmlspecialchars(($m['marca'] ?? '') . ' ' . ($m['modelo'] ?? '')) ?>
                            <?php if (!empty($m['numero_serie'])): ?>
                                SN:<strong><?= htmlspecialchars($m['numero_serie']) ?></strong>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endif; ?>
        <?php if ($monitor_extra): ?>
            <li>
                Monitor adicional vinculado:
                <?= htmlspecialchars(($monitor_extra['marca'] ?? '') . ' ' . ($monitor_extra['modelo'] ?? '')) ?>
                <?php if (!empty($monitor_extra['numero_serie'])): ?>
                    SN:<strong><?= htmlspecialchars($monitor_extra['numero_serie']) ?></strong>
                <?php endif; ?>
            </li>
        <?php endif; ?>
        <li>El resto de datos (ubicación, departamento y sección) se mantienen respecto al equipo renovado.</li>
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
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app_popups.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mostrarPopup = <?= $mostrarPopupEnvio ? 'true' : 'false' ?>;
    if (!mostrarPopup) return;

    const key = 'prompt_envio_renov_<?= (int)$ren['id'] ?>';
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');

    window.appDialogs.confirm(
        '¿Quieres enviar este recibo por correo a la sección (<?= addslashes($seccionCorreo) ?>) para su firma?',
        { title: 'Confirmar movimiento interno', okText: 'Enviar correo' }
    ).then(function (ok) {
        if (!ok) return;
        const f = document.getElementById('formEnviarCorreoSeccion');
        if (f) f.submit();
    });
});
</script>
</body>
</html>
