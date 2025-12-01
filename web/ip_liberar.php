<?php
// ip_liberar.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$idIp = isset($_POST['id_ip']) ? (int)$_POST['id_ip'] : 0;

if ($idIp <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

// LIBERAR = eliminar la fila de ips_equipos
$sql = "DELETE FROM ips_equipos WHERE id = :id";
$stmt = $pdo->prepare($sql);
$ok = $stmt->execute([':id' => $idIp]);

if ($ok) {
    echo 'OK';
} else {
    http_response_code(500);
    echo 'Error al liberar la IP';
}
