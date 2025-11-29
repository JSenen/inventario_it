<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

// Obtenemos todas las redes
$stmtRedes = $pdo->query("SELECT id, nombre, direccion_red, mascara FROM redes ORDER BY id ASC");

$redes = $stmtRedes->fetchAll(PDO::FETCH_ASSOC);

// Funciones auxiliares para cálculo de rangos IP
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
// A partir de direccion_red y mascara (DB) obtiene IP base y prefijo CIDR
function parseNetworkFromDb(string $direccionRed, ?string $mascaraDb): ?array {
    $direccionRed = trim($direccionRed);
    $mascaraDb    = $mascaraDb !== null ? trim($mascaraDb) : '';

    if ($direccionRed === '') {
        return null;
    }

    $ip   = null;
    $cidr = null;

    // "10.52.2.0/24"
    if (strpos($direccionRed, '/') !== false) {
        [$ipPart, $cidrPart] = explode('/', $direccionRed, 2);
        if (filter_var($ipPart, FILTER_VALIDATE_IP)) {
            $ip = $ipPart;
            if (ctype_digit($cidrPart)) {
                $cidr = (int)$cidrPart;
            }
        }
    } else {
        if (filter_var($direccionRed, FILTER_VALIDATE_IP)) {
            $ip = $direccionRed;
        }
    }

    if ($cidr === null && $mascaraDb !== '') {
        if (strpos($mascaraDb, '.') !== false) {
            $cidr = maskToCidr($mascaraDb);
        } elseif (ctype_digit($mascaraDb)) {
            $cidr = (int)$mascaraDb;
        }
    }

    if ($cidr === null) {
        $cidr = 24;
    }

    if (!$ip || $cidr < 0 || $cidr > 32) {
        return null;
    }

    return [$ip, $cidr];
}
//  Calcula rango de hosts usables a partir de IP y CIDR
function calcularRangoHosts(string $ip, int $cidr): ?array {
    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return null;
    }

    $maskLong  = ~((1 << (32 - $cidr)) - 1) & 0xFFFFFFFF;
    $network   = $ipLong & $maskLong;
    $broadcast = $network | (~$maskLong & 0xFFFFFFFF);

    $hostStart = $network + 1;
    $hostEnd   = $broadcast - 1;

    if ($hostStart > $hostEnd) {
        $hostEnd = $hostStart;
    }

    return [
        'network_long' => $network,
        'start_long'   => $hostStart,
        'end_long'     => $hostEnd,
        'network_ip'   => long2ip($network),
        'start_ip'     => long2ip($hostStart),
        'end_ip'       => long2ip($hostEnd),
    ];
}


function calcularPrefix($direccionRed) {
    // Esperamos algo tipo "10.52.2.0" o "10.52.2.0/24"
    if (preg_match('/^(\d+\.\d+\.\d+)\./', $direccionRed, $m)) {
        return $m[1] . '.';
    }
    return null;
}

// Cargamos todas las IPs usadas agrupadas por red_id
$stmtIps = $pdo->query("
    SELECT 
        ip.red_id,
        ip.ip,
        ip.mac,
        e.id        AS equipo_id,
        e.hostname  AS equipo_hostname,
        e.usuario_asignado AS equipo_usuario,
        e.tipo      AS equipo_tipo,
        e.departamento AS equipo_departamento
    FROM ips_equipos ip
    LEFT JOIN equipos e ON e.id = ip.equipo_id
    ORDER BY ip.red_id ASC, ip.ip ASC
");

$ipsPorRed = [];
while ($row = $stmtIps->fetch(PDO::FETCH_ASSOC)) {
    $redId = (int)$row['red_id'];
    if (!isset($ipsPorRed[$redId])) {
        $ipsPorRed[$redId] = [];
    }
    $ipsPorRed[$redId][] = $row;
}

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4">Control de direcciones IP</h1>

<p class="text-muted">
    Resumen de IPs por red. El cálculo de IPs libres se realiza según la máscara CIDR de cada red.
</p>


<?php if (empty($redes)): ?>
    <div class="alert alert-info">
        No hay redes definidas todavía. Añade redes en la tabla <code>redes</code> de la base de datos.
    </div>
<?php else: ?>

    <?php foreach ($redes as $r): ?>
        <?php
        $redId        = (int)$r['id'];
        $nombreRed    = $r['nombre'];
        $direccionRed = $r['direccion_red'];
        $mascaraDb    = $r['mascara'] ?? '';

        $listaUsadas = $ipsPorRed[$redId] ?? [];

        $networkInfo = parseNetworkFromDb($direccionRed, $mascaraDb);

        $totalPosibles = 0;
        $totalUsadas   = 0;
        $totalLibres   = 0;
        $muestraLibres = [];
        $textoRed      = $direccionRed;

        // Si podemos interpretar bien la red…
        if ($networkInfo !== null) {
            [$ipBase, $cidr] = $networkInfo;
            $rango = calcularRangoHosts($ipBase, $cidr);

            if ($rango !== null) {
                $inicioLong = $rango['start_long'];
                $finLong    = $rango['end_long'];

                // Texto bonito: 10.52.2.0/23, etc.
                $textoRed = $rango['network_ip'] . '/' . $cidr;

                // Marcar IPs usadas en esa red
                $usadas = [];
                // foreach ($listaUsadas as $row) {
                //     $ip = $row['ip'];
                //     $ipLong = ip2long($ip);
                //     if ($ipLong === false) {
                //         continue;
                //     }
                //     if ($ipLong >= $inicioLong && $ipLong <= $finLong) {
                //         $usadas[$ip] = true;
                //     }
                // }

                $totalPosibles = max(0, $finLong - $inicioLong + 1);
                $totalUsadas   = count($usadas);
                $totalLibres   = max(0, $totalPosibles - $totalUsadas);

                // Primera muestra de IPs libres (máx. 10)
                $muestraLibres = [];
                for ($ipLong = $inicioLong; $ipLong <= $finLong && count($muestraLibres) < 10; $ipLong++) {
                    $ip = long2ip($ipLong);
                    if (!isset($usadas[$ip])) {
                        $muestraLibres[] = $ip;
                    }
                }
            }
        } else {
            // No se entiende la red -> se mostrará aviso en el cuerpo de la card
            $cidr        = null;
            $inicioLong  = null;
            $finLong     = null;
        }

        
        // Marcar últimos octetos usados
        // foreach ($listaUsadas as $row) {
        //     $ip = $row['ip'];
        //     if ($prefix && strpos($ip, $prefix) === 0) {
        //         $lastOctet = (int)substr($ip, strrpos($ip, '.') + 1);
        //         if ($lastOctet >= $inicio && $lastOctet <= $fin) {
        //             $usadas[$lastOctet] = true;
        //         }
        //     }
        // }

        //$totalPosibles = ($fin - $inicio + 1);
        $totalUsadas   = count($usadas);
        $totalLibres   = $totalPosibles - $totalUsadas;

        // Generar una pequeña muestra de IPs libres
        //$muestraLibres = [];
        // if ($prefix) {
        //     for ($i = $inicio; $i <= $fin && count($muestraLibres) < 10; $i++) {
        //         if (!isset($usadas[$i])) {
        //             $muestraLibres[] = $prefix . $i;
        //         }
        //     }
        // }
        ?>

        <div class="card mb-4">
           <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong><?= htmlspecialchars($nombreRed) ?></strong>
            <span class="text-muted">
                (<?= htmlspecialchars($textoRed) ?>)
            </span>
        </div>

                <div>
                    <span class="badge bg-secondary">Total: <?= $totalPosibles ?> IPs</span>
                    <span class="badge bg-success">Libres: <?= $totalLibres ?></span>
                    <span class="badge bg-danger">Usadas: <?= $totalUsadas ?></span>
                </div>
            </div>
            <div class="card-body">
               <?php if ($networkInfo === null): ?>
                    <div class="alert alert-warning">
                        No se ha podido interpretar la red a partir de
                        <code><?= htmlspecialchars($direccionRed) ?></code>.
                        Formatos válidos: <code>10.52.2.0</code> con máscara CIDR en la columna
                        o <code>10.52.2.0/24</code>.
                    </div>
                <?php else: ?>


                    <?php if ($totalLibres > 0): ?>
                        <p class="mb-2">
                            <strong>Primeras IPs libres sugeridas:</strong>
                            <?php foreach ($muestraLibres as $ipLibre): ?>
                                <span class="badge bg-light text-muted border"><?= htmlspecialchars($ipLibre) ?></span>
                            <?php endforeach; ?>
                            <?php if ($totalLibres > count($muestraLibres)): ?>
                                <span class="text-muted">… (hay más)</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-danger">No hay IPs libres en esta red según el rango configurado.</p>
                    <?php endif; ?>

                    <?php if (empty($listaUsadas)): ?>
                        <div class="alert alert-info mb-0">
                            No hay IPs asignadas todavía en esta red.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 120px;">IP</th>
                                        <th style="width: 160px;">MAC</th>
                                        <th>Equipo / Hostname</th>
                                        <th>Usuario / Depto.</th>
                                        <th>Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listaUsadas as $row): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['ip']) ?></span></td>
                                            <td><small><?= htmlspecialchars($row['mac'] ?? '') ?></small></td>
                                            <td>
                                                <?php if (!empty($row['equipo_id'])): ?>
                                                    <a href="equipo_editar.php?id=<?= (int)$row['equipo_id'] ?>">
                                                        <?= htmlspecialchars($row['equipo_hostname'] ?? 'Sin hostname') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin equipo vinculado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($row['equipo_usuario'] ?? '') ?><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($row['equipo_departamento'] ?? '') ?>
                                                </small>
                                            </td>
                                            <td><?= htmlspecialchars($row['equipo_tipo'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
