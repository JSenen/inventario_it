<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

header('Content-Type: application/json; charset=utf-8');

$redId    = isset($_GET['red_id']) ? (int)$_GET['red_id'] : 0;
$equipoId = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;

if ($redId <= 0) {
    echo json_encode([]);
    exit;
}

/**
 * Convierte una máscara en formato 255.255.254.0 a CIDR (23, 24, etc.)
 */
function maskToCidr(string $mask): ?int {
    $long = ip2long($mask);
    if ($long === false) {
        return null;
    }
    $bits = 0;
    for ($i = 31; $i >= 0; $i--) {
        if ($long & (1 << $i)) {
            $bits++;
        }
    }
    return $bits;
}

/**
 * A partir de direccion_red y mascara (DB) obtiene IP base y prefijo CIDR
 */
function parseNetworkFromDb(string $direccionRed, ?string $mascaraDb): ?array {
    $direccionRed = trim($direccionRed);
    $mascaraDb    = $mascaraDb !== null ? trim($mascaraDb) : '';

    if ($direccionRed === '') {
        return null;
    }

    $ip   = null;
    $cidr = null;

    // Posible formato "10.52.2.0/24"
    if (strpos($direccionRed, '/') !== false) {
        [$ipPart, $cidrPart] = explode('/', $direccionRed, 2);
        if (filter_var($ipPart, FILTER_VALIDATE_IP)) {
            $ip = $ipPart;
            if (ctype_digit($cidrPart)) {
                $cidr = (int)$cidrPart;
            }
        }
    } else {
        // Solo IP: "10.52.2.0"
        if (filter_var($direccionRed, FILTER_VALIDATE_IP)) {
            $ip = $direccionRed;
        }
    }

    // Si no viene CIDR en la dirección, usamos la columna mascara
    if ($cidr === null && $mascaraDb !== '') {
        if (strpos($mascaraDb, '.') !== false) {
            // mascara en formato 255.255.254.0
            $cidr = maskToCidr($mascaraDb);
        } elseif (ctype_digit($mascaraDb)) {
            // mascara tipo "23"
            $cidr = (int)$mascaraDb;
        }
    }

    // Fallback a /24 si sigue sin estar claro
    if ($cidr === null) {
        $cidr = 24;
    }

    if (!$ip || $cidr < 0 || $cidr > 32) {
        return null;
    }

    return [$ip, $cidr];
}

/**
 * Calcula el rango de hosts (inicio y fin) a partir de IP base y CIDR
 */
function calcularRangoHosts(string $ip, int $cidr): ?array {
    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return null;
    }

    if ($cidr === 32) {
        $network   = $ipLong;
        $hostStart = $ipLong;
        $hostEnd   = $ipLong;
    } else {
        $maskLong  = ~((1 << (32 - $cidr)) - 1) & 0xFFFFFFFF;
        $network   = $ipLong & $maskLong;
        $broadcast = $network | (~$maskLong & 0xFFFFFFFF);

        // Para redes "normales" quitamos network y broadcast
        $hostStart = $network + 1;
        $hostEnd   = $broadcast - 1;
    }

    if ($hostStart > $hostEnd) {
        $hostEnd = $hostStart;
    }

    return [
        'network_long' => $network,
        'start_long'   => $hostStart,
        'end_long'     => $hostEnd,
    ];
}

// 1) Sacamos la red
$stmt = $pdo->prepare("SELECT direccion_red, mascara FROM redes WHERE id = :id");
$stmt->execute([':id' => $redId]);
$red = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$red) {
    echo json_encode([]);
    exit;
}

[$ipBase, $cidr] = parseNetworkFromDb($red['direccion_red'], $red['mascara']) ?? [null, null];

if ($ipBase === null || $cidr === null) {
    echo json_encode([]);
    exit;
}

$rango = calcularRangoHosts($ipBase, $cidr);
if ($rango === null) {
    echo json_encode([]);
    exit;
}

$inicioLong = $rango['start_long'];
$finLong    = $rango['end_long'];

// 2) IPs ya usadas en esa red
$stmt = $pdo->prepare("SELECT ip, equipo_id FROM ips_equipos WHERE red_id = :red_id");
$stmt->execute([':red_id' => $redId]);

$usadas = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ipRow      = $row['ip'];
    $equipoFila = (int)$row['equipo_id'];

    // Si estamos editando un equipo, su propia IP debe seguir disponible
    if ($equipoId > 0 && $equipoFila === $equipoId) {
        continue;
    }

    $usadas[$ipRow] = true;
}

// 3) Generar IPs libres
$libres = [];

for ($ipLong = $inicioLong; $ipLong <= $finLong; $ipLong++) {
    $ip = long2ip($ipLong);
    if (!isset($usadas[$ip])) {
        $libres[] = $ip;
    }
}

// Devuelve array JSON de IPs libres
echo json_encode($libres);
