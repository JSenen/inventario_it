<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$redId    = isset($_GET['red_id']) ? (int)$_GET['red_id'] : 0;
$equipoId = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;

if ($redId <= 0) {
    echo json_encode([]);
    exit;
}

// 1) Sacamos la red
$stmt = $pdo->prepare("SELECT direccion_red FROM redes WHERE id = :id");
$stmt->execute([':id' => $redId]);
$red = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$red) {
    echo json_encode([]);
    exit;
}

$direccionRed = $red['direccion_red']; // ej: "10.52.2.0"
$prefix = null;

if (preg_match('/^(\d+\.\d+\.\d+)\./', $direccionRed, $m)) {
    $prefix = $m[1] . '.';
} else {
    echo json_encode([]);
    exit;
}

// 2) IPs ya usadas en esa red
$stmt = $pdo->prepare("SELECT ip, equipo_id FROM ips_equipos WHERE red_id = :red_id");
$stmt->execute([':red_id' => $redId]);

$usadas = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Si estamos editando, ignoramos la IP del propio equipo para que siga saliendo como "libre"
    if ($equipoId > 0 && (int)$row['equipo_id'] === $equipoId) {
        continue;
    }

    $ip = $row['ip'];
    if (strpos($ip, $prefix) === 0) {
        $lastOctet = (int)substr($ip, strrpos($ip, '.') + 1);
        $usadas[$lastOctet] = true;
    }
}

// 3) Generar IPs libres
$libres = [];
$inicio = 1;
$fin    = 254;

for ($i = $inicio; $i <= $fin; $i++) {
    if (!isset($usadas[$i])) {
        $libres[] = $prefix . $i;
    }
}

echo json_encode($libres);
