<?php
function logActividad(PDO $pdo, string $accion, string $detalles = ''): void {
    $usuario = $_SESSION['tip'] ?? 'DESCONOCIDO';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare("
        INSERT INTO actividad_logs (usuario_tip, accion, detalles, ip)
        VALUES (:u, :a, :d, :ip)
    ");
    $stmt->execute([
        ':u'  => $usuario,
        ':a'  => $accion,
        ':d'  => $detalles,
        ':ip' => $ip
    ]);
}
