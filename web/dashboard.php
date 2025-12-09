<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';

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

function parseNetworkFromDb(string $direccionRed, ?string $mascaraDb): ?array {
    $direccionRed = trim($direccionRed);
    $mascaraDb    = $mascaraDb !== null ? trim($mascaraDb) : '';

    if ($direccionRed === '') {
        return null;
    }

    $ip   = null;
    $cidr = null;

    // Tipo "10.52.2.0/24"
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

function calcularRangoHosts(string $ip, int $cidr): ?array {
    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return null;
    }

    $maskLong  = ~((1 << (32 - $cidr)) - 1) & 0xFFFFFFFF;
    $network   = $ipLong & $maskLong;
    $broadcast = $network | (~$maskLong & 0xFFFFFFFF);

    // /31 o /32: tomamos toda la red
    if ($cidr >= 31) {
        $hostStart = $network;
        $hostEnd   = $broadcast;
    } else {
        $hostStart = $network + 1;
        $hostEnd   = $broadcast - 1;
    }

    if ($hostStart > $hostEnd) {
        return null;
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


/**
 * DASHBOARD PRINCIPAL INVENTARIO_IT
 * Esquema basado en inventario_schema.sql
 */

// =======================
// 1) KPIs INVENTARIO (EQUIPOS)
// =======================

// Total equipos
$totalEquipos = (int)$pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn();

// Equipos por estado (según columna equipos.estado - texto libre)
// Ajusta los nombres si en la práctica usas otros (por ejemplo 'En uso')
$equiposActivo   = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Activo'")->fetchColumn();
$equiposAlmacen  = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Almacén'")->fetchColumn();
$equiposBaja     = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Baja'")->fetchColumn();
$equiposPrestado = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Prestado'")->fetchColumn();
$equiposPrivados = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Privado'")->fetchColumn();
$equiposEtiquetados = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE etiqueta IS NOT NULL AND etiqueta <> ''")->fetchColumn();

// También puedes querer saber cuántos siguen en 'En uso'
$equiposEnUso = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'En uso'")->fetchColumn();



// =======================
// 2) KPIs REDES & IPs (ips_equipos)
// =======================

// Total redes
$totalRedes = (int)$pdo->query("SELECT COUNT(*) FROM redes WHERE nombre = 'RED 2' OR nombre = 'RED 3'")->fetchColumn();

// Resumen IPs (LIBRE / USADA / RESERVADA) de ips_equipos
$sqlIps = "
    SELECT
        COUNT(*) AS total_ips,
        SUM(CASE WHEN estado = 'LIBRE'      THEN 1 ELSE 0 END) AS libres,
        SUM(CASE WHEN estado = 'USADA'      THEN 1 ELSE 0 END) AS usadas,
        SUM(CASE WHEN estado = 'RESERVADA'  THEN 1 ELSE 0 END) AS reservadas
    FROM ips_equipos
";
$ipsResumen = $pdo->query($sqlIps)->fetch(PDO::FETCH_ASSOC) ?: [
    'total_ips' => 0, 'libres' => 0, 'usadas' => 0, 'reservadas' => 0
];

// =======================
// 3) TELÉFONOS & SIM (según tus tablas nuevas)
// =======================

// Teléfonos
if ($pdo->query("SHOW TABLES LIKE 'telefonos'")->rowCount() > 0) {
    $totalTelefonos   = (int)$pdo->query("SELECT COUNT(*) FROM telefonos")->fetchColumn();
    $telActivos       = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Activo'")->fetchColumn();
    $telAlmacen       = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Almacén'")->fetchColumn();
} else {
    $totalTelefonos = $telActivos = $telAlmacen = 0;
}

// SIMs
if ($pdo->query("SHOW TABLES LIKE 'sims'")->rowCount() > 0) {
    $totalSims      = (int)$pdo->query("SELECT COUNT(*) FROM sims")->fetchColumn();
    $simsAsignadas  = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Asignada'")->fetchColumn();
    $simsDisponibles = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Disponible'")->fetchColumn();
} else {
    $totalSims = $simsAsignadas = $simsDisponibles = 0;
}

// =======================
// 4) AVERÍAS & MATERIALES
// =======================

if ($pdo->query("SHOW TABLES LIKE 'averias'")->rowCount() > 0) {
    // Consideramos “abierta” todo lo que NO esté 'CERRADA'
    $averiasAbiertas = (int)$pdo->query("
        SELECT COUNT(*) FROM averias
        WHERE estado <> 'CERRADA'
    ")->fetchColumn();
} else {
    $averiasAbiertas = 0;
}

if ($pdo->query("SHOW TABLES LIKE 'materiales'")->rowCount() > 0) {
    $materialesCriticos = (int)$pdo->query("
        SELECT COUNT(*) FROM materiales
        WHERE stock_actual <= stock_minimo
    ")->fetchColumn();
} else {
    $materialesCriticos = 0;
}

// =======================
// 5) Top tipos de equipo (equipos.tipo)
// =======================

$tiposEquipos = $pdo->query("
    SELECT tipo, COUNT(*) AS total
    FROM equipos
    GROUP BY tipo
    ORDER BY total DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// 6) Top 5 redes por uso (IPS USADAS + RESERVADAS)
// =======================

$redesTopUso = $pdo->query("
    SELECT
        r.id,
        r.nombre,
        r.direccion_red,
        r.mascara,
        SUM(CASE WHEN i.estado = 'USADA'     THEN 1 ELSE 0 END) AS usadas,
        SUM(CASE WHEN i.estado = 'RESERVADA' THEN 1 ELSE 0 END) AS reservadas
    FROM redes r
    LEFT JOIN ips_equipos i ON i.red_id = r.id
    WHERE nombre = 'RED 2' OR nombre = 'RED 3'
    GROUP BY r.id, r.nombre, r.direccion_red, r.mascara
")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// Totales globales de IPs por CIDR
// =======================

$totalIpsPosiblesGlobal = 0;
$usadasGlobal           = 0;
$reservadasGlobal       = 0;

foreach ($redesTopUso as $r) {
    $usadas     = (int)($r['usadas'] ?? 0);
    $reservadas = (int)($r['reservadas'] ?? 0);

    $parsed = parseNetworkFromDb($r['direccion_red'], $r['mascara']);
    if ($parsed === null) {
        continue;
    }
    [$ipBase, $cidr] = $parsed;
    $rango = calcularRangoHosts($ipBase, $cidr);
    if (!$rango) {
        continue;
    }

    $totalPosibles = $rango['end_long'] - $rango['start_long'] + 1;

    $totalIpsPosiblesGlobal += $totalPosibles;
    $usadasGlobal           += $usadas;
    $reservadasGlobal       += $reservadas;
}

$libresGlobal = max(0, $totalIpsPosiblesGlobal - $usadasGlobal - $reservadasGlobal);



// =======================
// 7) Últimos equipos añadidos (equipos.creado_en)
// =======================

$ultimosEquipos = $pdo->query("
    SELECT id, marca, modelo, numero_serie, tipo, creado_en, estado
    FROM equipos
    ORDER BY creado_en DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="container-fluid mt-3">

    <h1 class="h3 mb-3">Panel general de inventario</h1>

    <!-- ========== FILA 1: CARDS KPI ========== -->
    <div class="row g-3">

        <!-- Total equipos -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="card-title text-muted">💻 Equipos totales</h6>
                    <div class="display-6 fw-bold"><?= $totalEquipos ?></div>
                    <small class="text-muted d-block">
                        Activo: <?= $equiposActivo ?> · Almacén: <?= $equiposAlmacen ?>
                    </small>
                    <small class="text-muted d-block">
                        Prestado: <?= $equiposPrestado ?> · Baja: <?= $equiposBaja ?> .  Privados: <?= $equiposPrestado ?> </small>
                    
                    <small class="text-success">
                        Etiquetados: <?= $equiposEtiquetados ?>
                    </small> 
                </div>
            </div>
        </div>

        <!-- Redes & IPs -->
        <!-- Redes & IPs -->
<div class="col-6 col-md-3">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h6 class="card-title text-muted">Redes Intranet / IPs</h6>
            <div class="display-6 fw-bold"><?= $totalRedes ?></div>

            <small class="text-muted d-block">
                IPs totales (posibles): <?= $totalIpsPosiblesGlobal ?>
            </small>
            <small class="text-muted d-block">
                Libres: <?= $libresGlobal ?> · Usadas: <?= $usadasGlobal ?>
            </small>
            <small class="text-muted">
                Reservadas: <?= $reservadasGlobal ?>
            </small>
        </div>
    </div>
</div>


        <!-- Teléfonos -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="card-title text-muted">☎️ Teléfonos móviles</h6>
                    <div class="display-6 fw-bold"><?= $totalTelefonos ?></div>
                    <small class="text-muted d-block">
                        Activos: <?= $telActivos ?> · Almacén: <?= $telAlmacen ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- SIM & Averías -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="card-title text-muted">SIM / ⚠️ Averías</h6>
                    <div class="display-6 fw-bold"><?= $totalSims ?></div>
                    <small class="text-muted d-block">
                        SIM asignadas: <?= $simsAsignadas ?> · Disp.: <?= $simsDisponibles ?>
                    </small>
                    <small class="text-danger">
                        Averías abiertas: <?= $averiasAbiertas ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== FILA 2: INVENTARIO & REDES ========== -->
    <div class="row g-3 mt-2">

        <!-- Columna izquierda: inventario -->
        <div class="col-md-6">
            <!-- Top tipos de equipo -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light">
                    <strong>Top tipos de equipo</strong>
                </div>
                <div class="card-body">
                    <?php if (empty($tiposEquipos)): ?>
                        <small class="text-muted">No hay datos de equipos.</small>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($tiposEquipos as $t): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($t['tipo']) ?></span>
                                    <span class="badge bg-primary rounded-pill">
                                        <?= (int)$t['total'] ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos equipos añadidos -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <strong>Últimos equipos añadidos</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Marca / Modelo</th>
                                    <th>Serie</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Alta</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($ultimosEquipos)): ?>
                                <tr><td colspan="6" class="text-center text-muted">Sin registros.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ultimosEquipos as $e): ?>
                                    <tr>
                                        <td><?= (int)$e['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($e['marca']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($e['modelo']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($e['numero_serie']) ?></td>
                                        <td><?= htmlspecialchars($e['tipo']) ?></td>
                                        <td><small><?= htmlspecialchars($e['estado']) ?></small></td>
                                        <td><small><?= htmlspecialchars($e['creado_en']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- Columna derecha: Redes + Alertas -->
        <div class="col-md-6">
            <!-- Redes con más uso -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <strong>Redes con más uso</strong>
                </div>
                <div class="card-body">
                    <?php if (empty($redesTopUso)): ?>
                        <small class="text-muted">No hay datos de redes.</small>
                    <?php else: ?>
                       <?php
// Preparamos arrays para la gráfica por red
$chartRedesLabels = [];
$chartRedesPorc   = [];

// Ordenamos las redes por % de uso y nos quedamos con 5
$redesCalculadas = [];

foreach ($redesTopUso as $r) {
    $usadas     = (int)($r['usadas'] ?? 0);
    $reservadas = (int)($r['reservadas'] ?? 0);

    [$ipBase, $cidr] = parseNetworkFromDb($r['direccion_red'], $r['mascara']) ?? [null, null];
    $totalPosibles   = 0;
    $ipNetworkStr    = $r['direccion_red'];

    if ($ipBase !== null && $cidr !== null) {
        $rango = calcularRangoHosts($ipBase, $cidr);
        if ($rango) {
            $totalPosibles = $rango['end_long'] - $rango['start_long'] + 1;
            $ipNetworkStr  = $rango['network_ip'] . '/' . $cidr;
        }
    }

    $ocupadas   = $usadas + $reservadas;
    $porcentaje = ($totalPosibles > 0)
        ? round(($ocupadas / $totalPosibles) * 100)
        : 0;

    $redesCalculadas[] = [
        'nombre'        => $r['nombre'],
        'ip_network'    => $ipNetworkStr,
        'total_posible' => $totalPosibles,
        'usadas'        => $usadas,
        'reservadas'    => $reservadas,
        'porcentaje'    => $porcentaje,
    ];
}

// Ordenar por % de uso desc y limitar a 5
usort($redesCalculadas, fn($a, $b) => $b['porcentaje'] <=> $a['porcentaje']);
$redesCalculadas = array_slice($redesCalculadas, 0, 5);
?>

<?php if (empty($redesCalculadas)): ?>
    <small class="text-muted">No hay datos de redes.</small>
<?php else: ?>
    <?php foreach ($redesCalculadas as $r): ?>
        <?php
            $chartRedesLabels[] = $r['nombre'];
            $chartRedesPorc[]   = $r['porcentaje'];
        ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <div>
                    <strong><?= htmlspecialchars($r['nombre']) ?></strong>
                    <small class="text-muted d-block">
                        <?= htmlspecialchars($r['ip_network']) ?> · IPs: <?= $r['total_posible'] ?>
                    </small>
                </div>
                <div>
                    <span class="badge bg-secondary">
                        <?= $r['porcentaje'] ?>% usado
                    </span>
                </div>
            </div>
            <div class="progress mt-1" style="height: 8px;">
                <div class="progress-bar" role="progressbar"
                     style="width: <?= $r['porcentaje'] ?>%;"
                     aria-valuenow="<?= $r['porcentaje'] ?>"
                     aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="text-muted">
                Usadas: <?= $r['usadas'] ?> · Reservadas: <?= $r['reservadas'] ?>
            </small>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>

            <!-- Alertas -->
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-light">
                    <strong>Alertas</strong>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-1">
                            <?php if ($materialesCriticos > 0): ?>
                                <span class="text-danger">
                                    🔴 Materiales en nivel crítico: <?= $materialesCriticos ?>
                                </span>
                            <?php else: ?>
                                <span class="text-success">
                                    🟢 Material fungible en niveles correctos.
                                </span>
                            <?php endif; ?>
                        </li>
                        <li class="mb-1">
                            <?php if ($averiasAbiertas > 0): ?>
                                <span class="text-warning">
                                    🟠 Averías abiertas: <?= $averiasAbiertas ?>
                                </span>
                            <?php else: ?>
                                <span class="text-success">
                                    🟢 No hay averías abiertas.
                                </span>
                            <?php endif; ?>
                        </li>
                        <!-- Aquí puedes añadir más alertas: equipos sin sección, sin imagen, etc. -->
                    </ul>
                </div>
            </div>

        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
