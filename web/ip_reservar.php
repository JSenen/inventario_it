<?php
// ip_reservar.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$redId = isset($_POST['red_id']) ? (int)$_POST['red_id'] : 0;
$ip    = trim($_POST['ip'] ?? '');

if ($redId <= 0 || !filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    exit('Datos inválidos');
}

// ¿Ya existe esa IP en la tabla?
$sqlCheck = "SELECT id, estado, equipo_id 
             FROM ips_equipos 
             WHERE red_id = :red_id AND ip = :ip
             LIMIT 1";
$stmt = $pdo->prepare($sqlCheck);
$stmt->execute([
    ':red_id' => $redId,
    ':ip'     => $ip,
]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // Si ya existe, la marcamos como RESERVADA y dejamos equipo_id NULL
    $sqlUpdate = "UPDATE ips_equipos
                  SET estado = 'RESERVADA', equipo_id = NULL
                  WHERE id = :id";
    $stmt2 = $pdo->prepare($sqlUpdate);
    $ok = $stmt2->execute([':id' => $row['id']]);
} else {

    // No existe -> creamos una nueva fila con equipo_id NULL y estado RESERVADA
    $sqlInsert = "INSERT INTO ips_equipos (equipo_id, red_id, ip, estado)
                  VALUES (NULL, :red_id, :ip, 'RESERVADA')";
    $stmt2 = $pdo->prepare($sqlInsert);
    $ok = $stmt2->execute([
        ':red_id' => $redId,
        ':ip'     => $ip,
    ]);


}

if ($ok) {
    echo 'OK';
} else {
    http_response_code(500);
    echo 'Error al reservar la IP';
}
