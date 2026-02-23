<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/mail_helper.php';
require_once __DIR__ . '/includes/recibos_pdf_helper.php';
ensureRecibosSchema($pdo);

$idMov = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idMov <= 0) {
    die('Movimiento no válido');
}

// 🔹 Cargamos movimiento + datos principales del equipo + sección
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        e.tipo        AS tipo_equipo,
        e.marca,
        e.modelo,
        e.numero_serie,
        e.hostname,
        e.usuario_asignado,
        e.departamento,
        e.ubicacion,
        e.proveedor,
        e.coste,
        e.estado      AS estado_equipo,
        s.nombre      AS seccion_nombre,
        s.correo      AS seccion_correo
    FROM equipos_movimientos m
    JOIN equipos e ON e.id = m.id_equipo
    LEFT JOIN secciones s ON s.id = e.seccion_id
    WHERE m.id = :id
");

$stmt->execute([':id' => $idMov]);
$mov = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mov) { die('Movimiento no encontrado'); }

if (empty($mov['pdf_unsigned_path'])) {
    generarReciboMovimientoPdf($pdo, (int)$mov['id'], false);
}
if (!empty($mov['firmado']) && empty($mov['pdf_signed_path'])) {
    generarReciboMovimientoPdf($pdo, (int)$mov['id'], true);
}

// Texto resumen de equipo
$equipoTxt = trim(($mov['marca'] ?? '') . ' ' . ($mov['modelo'] ?? ''));
$equipoTxt .= ' (S/N: ' . ($mov['numero_serie'] ?? '-') . ', HOST: ' . ($mov['hostname'] ?? '-') . ')';

$firmaPath = $mov['firma_path'] ?? null;

// Logo: pon aquí la ruta de tu logo dentro de /web
$logoRel = 'assets/logo_departamento.png'; // por ej: assets/logo_inventario.png
$logoFs  = __DIR__ . '/' . $logoRel;
$tieneLogo = is_file($logoFs);

// URL para firma digital (para enviarla al usuario)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
         . $_SERVER['HTTP_HOST'];
$urlFirma = $baseUrl . '/firma.php?token=' . urlencode($mov['firma_token']);

$mailOk = null;
$mailMsg = null;
$seccionCorreo = trim((string)($mov['seccion_correo'] ?? ''));
$mailSubject = 'Firma de recibo de movimiento #' . (int)$mov['id'];
$mailBody = "Hola,\n\n" .
    "Se ha generado un recibo de movimiento pendiente de firma.\n\n" .
    "Movimiento: #" . (int)$mov['id'] . "\n" .
    "Equipo: " . trim(($mov['marca'] ?? '') . ' ' . ($mov['modelo'] ?? '')) . "\n" .
    "Serie: " . ($mov['numero_serie'] ?? '-') . "\n" .
    "Sección: " . ($mov['seccion_nombre'] ?? '-') . "\n\n" .
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
    } elseif (empty($mov['firma_token'])) {
        $mailOk = false;
        $mailMsg = 'No hay token de firma disponible para este recibo.';
    } else {
        $err = null;
        $mailOk = sendPlainEmail($seccionCorreo, $mailSubject, $mailBody, $err);
        if ($mailOk) {
            $mailMsg = 'Correo enviado a ' . $seccionCorreo . '.';
            logActividad(
                $pdo,
                'ENVIO_CORREO_RECIBO_MOV',
                'Movimiento=' . (int)$mov['id'] . '; destino=' . $seccionCorreo,
                ['modulo' => 'RECIBOS', 'nivel' => 'INFO']
            );
        } else {
            $mailMsg = $err ?: 'No se pudo enviar el correo.';
            logActividad(
                $pdo,
                'ENVIO_CORREO_RECIBO_MOV_FALLO',
                'Movimiento=' . (int)$mov['id'] . '; destino=' . $seccionCorreo . '; motivo=' . $mailMsg,
                ['modulo' => 'RECIBOS', 'nivel' => 'WARN']
            );
        }
    }
}

$mostrarPopupEnvio = ($_SERVER['REQUEST_METHOD'] !== 'POST')
    && empty($mov['firmado'])
    && !empty($mov['firma_token'])
    && ($seccionCorreo !== '')
    && filter_var($seccionCorreo, FILTER_VALIDATE_EMAIL);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Recibo de movimiento #<?= htmlspecialchars($mov['id']) ?></title>
<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">

<style>
body {
    font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
    font-size: 13px;
    padding: 25px;
    background: #f8f9fa;
}
h1 {
    font-size: 22px;
    margin-bottom: 15px;
}
.section-title {
    font-size: 15px;
    font-weight: bold;
    margin-top: 20px;
    margin-bottom: 8px;
    border-bottom: 2px solid #000;
}
.table td {
    padding: 4px 8px;
}
.firma-panel {
    margin-top: 25px;
    padding: 15px;
    border: 1px solid #555;
    background: white;
}
.firma-panel img {
    max-width: 350px;
    max-height: 180px;
    border: 1px solid #aaa;
}
.small-text {
    font-size: 11px;
    color: #555;
}
.instrucciones {
    background: #fffbea;
    border: 1px solid #ffe58f;
    padding: 10px;
    font-size: 12px;
    border-radius: 4px;
}
.no-print { display: block; }
@media print {
    .no-print { display: none !important; }
    body { padding: 10mm; }
}
.header-doc {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.header-doc-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.header-doc-title {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
}

/* Marco general estilo "hoja" centrada */
.documento {
    max-width: 900px;        /* Ancho total de la hoja */
    margin: 0 auto;          /* CENTRADO */
    background: white;       /* Fondo blanco */
    padding: 30px 40px;      /* Márgenes internos */
    border: 1px solid #ddd;  /* Borde suave */
    border-radius: 6px;
    box-shadow: 0 0 10px rgba(0,0,0,0.08);
}

/* En impresión quitamos el borde y la sombra */
@media print {
    .documento {
        border: none;
        box-shadow: none;
        padding: 0;
    }
}

</style>
</head>

<body>
<div class="documento">
<div class="no-print text-end mb-3">
    <form id="formEnviarCorreoSeccion" method="post" class="d-inline">
        <input type="hidden" name="enviar_correo_seccion" value="1">
        <button type="submit" class="btn btn-outline-primary btn-sm">Enviar correo a sección</button>
    </form>
    <button onclick="window.print()" class="btn btn-secondary btn-sm">
        Imprimir / Guardar como PDF
    </button>
</div>
<?php if ($mailMsg !== null): ?>
    <div class="alert alert-<?= $mailOk ? 'success' : 'warning' ?> no-print">
        <?= htmlspecialchars($mailMsg) ?>
        <?php if (!$mailOk && $mailtoLink !== ''): ?>
            <a href="<?= htmlspecialchars($mailtoLink) ?>" class="ms-2">Abrir cliente de correo</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="header-doc">
    <div class="header-doc-left">
        <?php if ($tieneLogo): ?>
            <img src="<?= htmlspecialchars($logoRel) ?>" alt="Logo" style="max-height:60px;">
        <?php endif; ?>
        <div>
            <div class="header-doc-title">Recibo de movimiento de equipo informático</div>
            <div class="small-text">
                ID movimiento: #<?= (int)$mov['id'] ?> · Generado: <?= htmlspecialchars($mov['fecha']) ?>
            </div>
        </div>
    </div>
</div>

<div class="instrucciones mb-3">
    <strong>INSTRUCCIONES PARA EL USUARIO:</strong><br>
    1. Revise que los datos del equipo y del movimiento son correctos.<br>
    2. Si está conforme con la entrega o recogida, firme en el recuadro de abajo.<br>
    3. Puede firmar:
    <ul class="mb-1">
        <li>Digitalmente, al pulsar sobre formulario firma digital podrá firmar con el ratón o el dedo.</li>
        <li>Manuscrita, si este documento se ha impreso.</li>
    </ul>
    4. Para cualquier duda, contacte con el Departamento de Informática.
</div>

<!-- DATOS DEL MOVIMIENTO -->
<div class="section-title">Datos del Movimiento</div>
<table class="table table-bordered">
    <tr><td><strong>Tipo de movimiento:</strong></td><td><?= strtoupper(htmlspecialchars($mov['tipo'])) ?></td></tr>
    <tr><td><strong>Fecha:</strong></td><td><?= htmlspecialchars($mov['fecha']) ?></td></tr>
    <tr><td><strong>Técnico (TIP):</strong></td><td><?= htmlspecialchars($mov['tecnico']) ?></td></tr>
    <tr><td><strong>Observaciones:</strong></td>
        <td><?= nl2br(htmlspecialchars($mov['observaciones'] ?: "Ninguna")) ?></td>
    </tr>
</table>

<!-- DATOS DEL EQUIPO -->
<div class="section-title">Datos del Equipo</div>
<table class="table table-bordered">
    <tr><td><strong>Tipo de equipo:</strong></td><td><?= htmlspecialchars($mov['tipo_equipo'] ?? '-') ?></td></tr>
    <tr><td><strong>Marca:</strong></td><td><?= htmlspecialchars($mov['marca'] ?? '-') ?></td></tr>
    <tr><td><strong>Modelo:</strong></td><td><?= htmlspecialchars($mov['modelo'] ?? '-') ?></td></tr>
    <tr><td><strong>N.º de serie:</strong></td><td><strong><?= htmlspecialchars($mov['numero_serie'] ?? '-') ?></strong></td></tr>
    <tr><td><strong>Servicio:</strong></td><td><?= htmlspecialchars($mov['hostname'] ?? '-') ?></td></tr>
    <tr><td><strong>Ubicación:</strong></td><td><?= htmlspecialchars($mov['ubicacion'] ?? '-') ?></td></tr>
    <tr><td><strong>Departamento:</strong></td><td><?= htmlspecialchars($mov['departamento'] ?? '-') ?></td></tr>
    <tr><td><strong>Sección:</strong></td><td><?= htmlspecialchars($mov['seccion_nombre'] ?? '-') ?></td></tr>
    
</table>


<!-- DATOS DEL USUARIO -->
<div class="section-title">Datos del Usuario</div>
<table class="table table-bordered">
    <tr><td><strong>Usuario destino:</strong></td><td><?= htmlspecialchars($mov['usuario_destino']) ?></td></tr>
</table>

<!-- ENLACE DE FIRMA PARA EL TÉCNICO 
<div class="section-title no-print">Enlace de firma para enviar al usuario</div>
<div class="no-print mb-3">
    <div class="small-text mb-1">
        Copia este enlace y envíaselo al usuario por correo o mensajería para que firme digitalmente:
    </div>
    <div class="input-group input-group-sm" style="max-width: 650px;">
        <input type="text" id="linkFirma" class="form-control" readonly
               value="<?= htmlspecialchars($urlFirma) ?>">
        <button class="btn btn-outline-secondary" type="button" onclick="copiarLink()">
            Copiar enlace
        </button>
    </div>
</div>-->

<div class="section-title">Normas de Uso y Responsabilidades</div>
<div class="normas small-text" style="background:#fff0e1;padding:12px;border:1px solid #ffd9b3;border-radius:4px;">
    <p>
        El usuario declara haber recibido el equipo indicado en este documento y se compromete a:
    </p>
    <ul>
        <li>Utilizar el equipo exclusivamente para funciones propias del puesto de trabajo.</li>
        <li>Cumplir las políticas de seguridad, privacidad y uso aceptable de medios informáticos establecidas por la organización.</li>
        <li>Custodiar el dispositivo y evitar cualquier situación que pueda derivar en pérdida, hurto o acceso no autorizado.</li>
        <li>Comunicar de inmediato al Departamento de Informática cualquier incidente relacionado con el equipo o su información.</li>
        <li>No ceder, manipular, prestar o reasignar el equipo sin autorización expresa del personal técnico.</li>
        <li>Permitir las revisiones, mantenimientos y auditorías del equipo cuando sean necesarias.</li>
        <li>Proceder a su devolución en el mismo estado en que fue entregado cuando así se requiera.</li>
    </ul>
    <p>
        Mediante la firma del presente documento, el usuario acepta las condiciones aquí expuestas y asume la responsabilidad del uso adecuado del equipo asignado.
    </p>
</div>


<!-- FIRMA -->
<div class="section-title">Firma del Usuario</div>

<div class="firma-panel">
<?php if ($firmaPath && is_file(__DIR__ . '/' . $firmaPath)): ?>
    <div><strong>Documento firmado electrónicamente.</strong></div>
    <img src="<?= htmlspecialchars($firmaPath) ?>" alt="Firma del usuario">
    <div class="small-text mt-2">Firmado el <?= htmlspecialchars($mov['firmado_fecha']) ?></div>
<?php else: ?>
    <div style="height:180px;">
        ________________________________<br>
        <span class="small-text">(firma manuscrita en caso de impresión o pendiente de firma digital)</span>
    </div>

    <div class="no-print mt-2">
        <a href="firma.php?token=<?= urlencode($mov['firma_token']) ?>" class="btn btn-primary btn-sm">
            Abrir formulario de firma digital
        </a>
    </div>
<?php endif; ?>
</div>

<div class="small-text mt-3">
    Documento generado automáticamente por el Sistema de Inventario IT.  
    Este recibo certifica que el usuario indicado ha recibido o entregado el equipo detallado en la fecha mostrada.
</div>

<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app_popups.js"></script>
<script>
function copiarLink() {
    var input = document.getElementById('linkFirma');
    input.select();
    input.setSelectionRange(0, 99999);
    try {
        document.execCommand('copy');
        window.appDialogs.alert('Enlace copiado. Pégalo en un correo o chat al usuario.');
    } catch (e) {
        window.appDialogs.alert('No se ha podido copiar automáticamente. Selecciona el texto y cópialo manualmente.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const mostrarPopup = <?= $mostrarPopupEnvio ? 'true' : 'false' ?>;
    if (!mostrarPopup) return;

    const key = 'prompt_envio_mov_<?= (int)$mov['id'] ?>';
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
</div> <!-- /.documento -->
</body>
</html>
