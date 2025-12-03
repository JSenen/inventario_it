<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/movimientos_helper.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$token = $data['token'] ?? '';
$firma = $data['firma'] ?? '';

if ($token === '' || strpos($firma, 'data:image/png;base64,') !== 0) {
    http_response_code(400);
    echo "Datos no válidos";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM equipos_movimientos WHERE firma_token = :t");
$stmt->execute([':t' => $token]);
$mov = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mov) {
    http_response_code(404);
    echo "Movimiento no encontrado";
    exit;
}

$imgData = substr($firma, strlen('data:image/png;base64,'));
$imgBin  = base64_decode($imgData);

$dirFirmas = __DIR__ . '/uploads/firmas/';
if (!is_dir($dirFirmas)) {
    mkdir($dirFirmas, 0775, true);
}

$fileName   = 'firma_mov_' . $mov['id'] . '.png';
$rutaFisica = $dirFirmas . $fileName;
file_put_contents($rutaFisica, $imgBin);

$rutaRelativa = 'uploads/firmas/' . $fileName;

// Guardar la firma y marcar como firmado
$stmtUp = $pdo->prepare("
    UPDATE equipos_movimientos
    SET firma_path = :firma, firmado = 1, firmado_fecha = NOW()
    WHERE id = :id
");
$stmtUp->execute([
    ':firma' => $rutaRelativa,
    ':id'    => $mov['id'],
]);

// Regenerar el PDF para incrustar la firma
generarPdfMovimiento($pdo, (int)$mov['id']);

echo '<div class="alert alert-success">Firma guardada correctamente. Ya puedes cerrar esta ventana.</div>';
