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

$ren = null;
$renTel = null;
if (!$mov) {
    $stmtRen = $pdo->prepare("SELECT * FROM renovaciones WHERE firma_token = :t");
    $stmtRen->execute([':t' => $token]);
    $ren = $stmtRen->fetch(PDO::FETCH_ASSOC);
    if (!$ren) {
        $stmtRenTel = $pdo->prepare("SELECT * FROM renovaciones_telefonos WHERE firma_token = :t");
        $stmtRenTel->execute([':t' => $token]);
        $renTel = $stmtRenTel->fetch(PDO::FETCH_ASSOC);
        if (!$renTel) {
            http_response_code(404);
            echo "Movimiento/Renovación no encontrado";
            exit;
        }
    }
}

$imgData = substr($firma, strlen('data:image/png;base64,'));
$imgBin  = base64_decode($imgData);

$dirFirmas = __DIR__ . '/uploads/firmas/';
if (!is_dir($dirFirmas)) {
    mkdir($dirFirmas, 0775, true);
}

$fileName = '';
if ($mov) {
    $fileName = 'firma_mov_' . $mov['id'] . '.png';
} elseif ($ren) {
    $fileName = 'firma_renov_' . $ren['id'] . '.png';
} else {
    $fileName = 'firma_renov_tel_' . $renTel['id'] . '.png';
}
$rutaFisica = $dirFirmas . $fileName;
file_put_contents($rutaFisica, $imgBin);

$rutaRelativa = 'uploads/firmas/' . $fileName;

if ($mov) {
    $stmtUp = $pdo->prepare("
        UPDATE equipos_movimientos
        SET firma_path = :firma, firmado = 1, firmado_fecha = NOW()
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':firma' => $rutaRelativa,
        ':id'    => $mov['id'],
    ]);

    generarPdfMovimiento($pdo, (int)$mov['id']);
} elseif ($ren) {
    $stmtUp = $pdo->prepare("
        UPDATE renovaciones
        SET firma_path = :firma, firmado = 1, firmado_fecha = NOW()
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':firma' => $rutaRelativa,
        ':id'    => $ren['id'],
    ]);
} else {
    $stmtUp = $pdo->prepare("
        UPDATE renovaciones_telefonos
        SET firma_path = :firma, firmado = 1, firmado_fecha = NOW()
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':firma' => $rutaRelativa,
        ':id'    => $renTel['id'],
    ]);
}

echo '<div class="alert alert-success">Firma guardada correctamente. Ya puedes cerrar esta ventana.</div>';
