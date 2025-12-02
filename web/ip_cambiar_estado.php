<?php
// ip_cambiar_estado.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$idIp   = isset($_POST['id_ip']) ? (int)$_POST['id_ip'] : 0;
$estado = $_POST['estado'] ?? '';

$estadosValidos = ['USADA', 'RESERVADA']; // solo estos dos las libres se calculan con get_ips_libres.php

if ($idIp <= 0 || !in_array($estado, $estadosValidos, true)) {
    http_response_code(400);
    exit('Datos inválidos');
}

$sql = "UPDATE ips_equipos SET estado = :estado WHERE id = :id";
$stmt = $pdo->prepare($sql);

if ($stmt->execute([':estado' => $estado, ':id' => $idIp])) {
    echo 'OK';
} else {
    http_response_code(500);
    echo 'Error al actualizar';
}
