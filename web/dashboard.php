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
$totalEquipos = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado <> 'Baja definitiva'")->fetchColumn();

// Equipos por estado (según columna equipos.estado - texto libre)
// Ajusta los nombres si en la práctica usas otros (por ejemplo 'En uso')
$equiposActivo   = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Activo'")->fetchColumn();
$equiposAlmacen  = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Almacén'")->fetchColumn();
$equiposBaja     = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Baja'")->fetchColumn();
$equiposBajaDef  = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Baja definitiva'")->fetchColumn();
$equiposPrestado = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Prestado'")->fetchColumn();
$equiposPrivados = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'Privado'")->fetchColumn();
$equiposEtiquetados = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE (etiqueta IS NOT NULL AND etiqueta <> '') AND estado <> 'Baja definitiva'")->fetchColumn();
$equiposSinEtiqueta = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE (etiqueta IS NULL OR etiqueta = '') AND estado <> 'Baja definitiva'")->fetchColumn();
$equiposSinUsuario  = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE (usuario_asignado IS NULL OR usuario_asignado = '') AND estado <> 'Baja definitiva'")->fetchColumn();
$equiposSinDept     = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE (departamento IS NULL OR departamento = '') AND estado <> 'Baja definitiva'")->fetchColumn();
$equiposSinHostname = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE (hostname IS NULL OR hostname = '') AND estado <> 'Baja definitiva'")->fetchColumn();

// También puedes querer saber cuántos siguen en 'En uso'
$equiposEnUso = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'En uso' AND estado <> 'Baja definitiva'")->fetchColumn();



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
    $telAveriados     = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Averiado'")->fetchColumn();
    $telPrestados     = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Prestado'")->fetchColumn();
    $telBaja          = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Baja'")->fetchColumn();
    $telBajaDef       = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Baja definitiva'")->fetchColumn();
    $telAlmacen       = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Almacén'")->fetchColumn();
    $telSinEtiqueta   = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE etiqueta IS NULL OR etiqueta = ''")->fetchColumn();

    // Teléfonos sin SIM (sin relación activa en telefono_sim)
    $telSinSimStmt = $pdo->query("
        SELECT COUNT(*)
        FROM telefonos t
        LEFT JOIN telefono_sim ts
               ON ts.telefono_id = t.id
              AND ts.fecha_liberacion IS NULL
        WHERE ts.id IS NULL
    ");
    $telSinSim = (int)$telSinSimStmt->fetchColumn();
} else {
    $totalTelefonos = $telActivos = $telAveriados = $telPrestados = $telBaja = $telBajaDef = $telAlmacen = $telSinSim = $telSinEtiqueta = 0;
}

// SIMs
if ($pdo->query("SHOW TABLES LIKE 'sims'")->rowCount() > 0) {
    $totalSims      = (int)$pdo->query("SELECT COUNT(*) FROM sims")->fetchColumn();
    $simsAsignadas  = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Asignada'")->fetchColumn();
    $simsDisponibles = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Disponible'")->fetchColumn();
    // SIMs sin teléfono (sin relación activa)
    $simsSinTelefono = (int)$pdo->query("
        SELECT COUNT(*)
        FROM sims s
        LEFT JOIN telefono_sim ts
               ON ts.sim_id = s.id
              AND ts.fecha_liberacion IS NULL
        WHERE ts.id IS NULL
    ")->fetchColumn();
    // Placeholder: si se añade fecha de caducidad, ajustar aquí
    $simsCaducadas = 0;
} else {
    $totalSims = $simsAsignadas = $simsDisponibles = $simsSinTelefono = $simsCaducadas = 0;
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

// Renovaciones de teléfonos pendientes de firma
try {
    $renovPendientes = (int)$pdo->query("SELECT COUNT(*) FROM renovaciones_telefonos WHERE firmado = 0")->fetchColumn();
} catch (Exception $e) {
    $renovPendientes = 0;
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
    WHERE estado <> 'Baja definitiva'
    GROUP BY tipo
    ORDER BY total DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Rankings y pendientes de equipos
$topDeptEquipos = $pdo->query("
    SELECT departamento, COUNT(*) AS total
    FROM equipos
    WHERE departamento IS NOT NULL AND departamento <> '' AND estado <> 'Baja definitiva'
    GROUP BY departamento
    ORDER BY total DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$topModelosEquipos = $pdo->query("
    SELECT modelo, COUNT(*) AS total
    FROM equipos
    WHERE estado <> 'Baja definitiva'
    GROUP BY modelo
    ORDER BY total DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$equiposAlmacenLista = $pdo->query("
    SELECT id, marca, modelo, etiqueta
    FROM equipos
    WHERE estado = 'Almacén'
    ORDER BY id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Rankings teléfonos/SIMs
$topOperadores = $pdo->query("
    SELECT s.operador, COUNT(*) AS total
    FROM telefono_sim ts
    JOIN sims s ON s.id = ts.sim_id
    WHERE ts.fecha_liberacion IS NULL
      AND s.operador IS NOT NULL AND s.operador <> ''
    GROUP BY s.operador
    ORDER BY total DESC
    LIMIT 5
" )->fetchAll(PDO::FETCH_ASSOC);

$topDeptTelefonos = $pdo->query("
    SELECT departamento, COUNT(*) AS total
    FROM telefonos
    WHERE departamento IS NOT NULL AND departamento <> ''
    GROUP BY departamento
    ORDER BY total DESC
    LIMIT 5
" )->fetchAll(PDO::FETCH_ASSOC);

$topModelosTelefonos = $pdo->query("
    SELECT modelo, COUNT(*) AS total
    FROM telefonos
    GROUP BY modelo
    ORDER BY total DESC
    LIMIT 5
" )->fetchAll(PDO::FETCH_ASSOC);

// Pendientes
$telefonosAlmacen = $pdo->query("
    SELECT id, marca, modelo, etiqueta
    FROM telefonos
    WHERE estado = 'Almacén'
    ORDER BY id DESC
    LIMIT 5
" )->fetchAll(PDO::FETCH_ASSOC);

$simsLibres = $pdo->query("
    SELECT id, numero, operador
    FROM sims
    WHERE estado = 'Disponible'
    ORDER BY id DESC
    LIMIT 5
" )->fetchAll(PDO::FETCH_ASSOC);

// Porcentajes visuales
$porcTelActivo   = $totalTelefonos > 0 ? round(($telActivos / $totalTelefonos) * 100) : 0;
$porcTelBaja     = $totalTelefonos > 0 ? round(($telBaja / $totalTelefonos) * 100) : 0;
$porcTelAveriado = $totalTelefonos > 0 ? round(($telAveriados / $totalTelefonos) * 100) : 0;

$porcSimsAsign   = $totalSims > 0 ? round(($simsAsignadas / $totalSims) * 100) : 0;
$porcSimsDisp    = $totalSims > 0 ? round(($simsDisponibles / $totalSims) * 100) : 0;

$porcEqActivo   = $totalEquipos > 0 ? round(($equiposActivo / $totalEquipos) * 100) : 0;
$porcEqAlmacen  = $totalEquipos > 0 ? round(($equiposAlmacen / $totalEquipos) * 100) : 0;
$porcEqBaja     = $totalEquipos > 0 ? round(($equiposBaja / $totalEquipos) * 100) : 0;
$porcEqPrestado = $totalEquipos > 0 ? round(($equiposPrestado / $totalEquipos) * 100) : 0;
$porcEqPrivado  = $totalEquipos > 0 ? round(($equiposPrivados / $totalEquipos) * 100) : 0;

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
    WHERE estado <> 'Baja definitiva'
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
                        Prestado: <?= $equiposPrestado ?> · Baja: <?= $equiposBaja ?> · Baja def.: <?= $equiposBajaDef ?> · Privados: <?= $equiposPrivados ?> </small>
                    
                    <small class="text-success">
                        Etiquetados: <?= $equiposEtiquetados ?>
                    </small> 
                </div>
            </div>
        </div>

        <!-- Redes & IPs -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Redes Intranet / IPs</h6>
                    <div class="display-6 fw-bold"><?= $totalRedes ?></div>
                    <small class="text-muted d-block">IPs posibles: <?= $totalIpsPosiblesGlobal ?></small>
                    <small class="text-muted d-block">Libres: <?= $libresGlobal ?> · Usadas: <?= $usadasGlobal ?></small>
                    <small class="text-muted">Reservadas: <?= $reservadasGlobal ?></small>
                </div>
            </div>
        </div>

        <!-- Teléfonos -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">☎️ Teléfonos</h6>
                    <div class="display-6 fw-bold"><?= $totalTelefonos ?></div>
                    <small class="text-muted d-block">Activos: <?= $telActivos ?> · Averiados: <?= $telAveriados ?></small>
                    <small class="text-muted d-block">Prestados: <?= $telPrestados ?> · Baja: <?= $telBaja ?> · Baja def.: <?= $telBajaDef ?></small>
                    <small class="text-muted">Almacén: <?= $telAlmacen ?> · Sin SIM: <?= $telSinSim ?></small>
                </div>
            </div>
        </div>

        <!-- SIMs -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">SIMs</h6>
                    <div class="display-6 fw-bold"><?= $totalSims ?></div>
                    <small class="text-muted d-block">Asignadas: <?= $simsAsignadas ?> · Disponibles: <?= $simsDisponibles ?></small>
                    <small class="text-muted">Sin teléfono: <?= $simsSinTelefono ?> · Caducadas: <?= $simsCaducadas ?></small>
                </div>
            </div>
        </div>

        <!-- Averías / Renovaciones -->
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">⚠️ Averías / Firmas</h6>
                    <div class="display-6 fw-bold"><?= $averiasAbiertas ?></div>
                    <small class="text-muted d-block">Averías abiertas</small>
                    <small class="text-muted">Renovaciones pendientes firma: <?= $renovPendientes ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== FILA 1b: BARRAS % TEL / SIM ========== -->
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Uso teléfonos</h6>
                    <div class="mb-2">Activo: <?= $porcTelActivo ?>% · Baja: <?= $porcTelBaja ?>% · Averiado: <?= $porcTelAveriado ?>%</div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?= $porcTelActivo ?>%"></div>
                        <div class="progress-bar bg-warning" style="width: <?= $porcTelAveriado ?>%"></div>
                        <div class="progress-bar bg-secondary" style="width: <?= $porcTelBaja ?>%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">Sin etiqueta: <?= $telSinEtiqueta ?> · Sin SIM: <?= $telSinSim ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Uso SIMs</h6>
                    <div class="mb-2">Asignadas: <?= $porcSimsAsign ?>% · Disponibles: <?= $porcSimsDisp ?>%</div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary" style="width: <?= $porcSimsAsign ?>%"></div>
                        <div class="progress-bar bg-success" style="width: <?= $porcSimsDisp ?>%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">Sin teléfono: <?= $simsSinTelefono ?> · Caducadas: <?= $simsCaducadas ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== FILA 1c: EQUIPOS ========== -->
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Uso equipos</h6>
                    <div class="mb-2">Activo: <?= $porcEqActivo ?>% · Almacén: <?= $porcEqAlmacen ?>% · Baja: <?= $porcEqBaja ?>%</div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?= $porcEqActivo ?>%"></div>
                        <div class="progress-bar bg-secondary" style="width: <?= $porcEqAlmacen ?>%"></div>
                        <div class="progress-bar bg-dark" style="width: <?= $porcEqBaja ?>%"></div>
                    </div>
                    <div class="mb-2 mt-3">Prestado: <?= $porcEqPrestado ?>% · Privado: <?= $porcEqPrivado ?>%</div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" style="width: <?= $porcEqPrestado ?>%"></div>
                        <div class="progress-bar bg-info" style="width: <?= $porcEqPrivado ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Alertas equipos</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-1">Sin etiqueta: <strong><?= $equiposSinEtiqueta ?></strong></li>
                        <li class="mb-1">Sin usuario asignado: <strong><?= $equiposSinUsuario ?></strong></li>
                        <li class="mb-1">Sin departamento: <strong><?= $equiposSinDept ?></strong></li>
                        <li class="mb-1">Sin tipo servicio: <strong><?= $equiposSinHostname ?></strong></li>
                        <li class="mb-1">En uso: <strong><?= $equiposEnUso ?></strong></li>
                    </ul>
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

            <!-- Top modelos de equipos -->
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-light">
                    <strong>Top modelos de equipos</strong>
                </div>
                <div class="card-body">
                    <?php if (empty($topModelosEquipos)): ?>
                        <small class="text-muted">Sin datos.</small>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($topModelosEquipos as $m): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($m['modelo']) ?>
                                    <span class="badge bg-info rounded-pill"><?= (int)$m['total'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Equipos en almacén (pendientes asignar) -->
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Equipos en almacén</strong>
                    <a href="index.php?estado=Almac%C3%A9n" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($equiposAlmacenLista)): ?>
                        <small class="text-muted">Sin pendientes.</small>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($equiposAlmacenLista as $eq): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($eq['marca'].' '.$eq['modelo']) ?><?= $eq['etiqueta'] ? ' ['.htmlspecialchars($eq['etiqueta']).']' : '' ?></span>
                                    <a href="equipo_editar.php?id=<?= (int)$eq['id'] ?>" class="btn btn-sm btn-outline-success">Asignar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Columna derecha: Redes + rankings + pendientes + alertas -->
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

            <!-- Rankings Teléfonos/SIMs -->
            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light"><strong>Top operadores</strong></div>
                        <div class="card-body">
                            <?php if (empty($topOperadores)): ?>
                                <small class="text-muted">Sin datos.</small>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($topOperadores as $op): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($op['operador']) ?>
                                            <span class="badge bg-primary rounded-pill"><?= (int)$op['total'] ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light"><strong>Top teléfonos departamentos</strong></div>
                        <div class="card-body">
                            <?php if (empty($topDeptTelefonos)): ?>
                                <small class="text-muted">Sin datos.</small>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($topDeptTelefonos as $d): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($d['departamento']) ?>
                                            <span class="badge bg-secondary rounded-pill"><?= (int)$d['total'] ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light"><strong>Top modelos</strong></div>
                        <div class="card-body">
                            <?php if (empty($topModelosTelefonos)): ?>
                                <small class="text-muted">Sin datos.</small>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($topModelosTelefonos as $m): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($m['modelo']) ?>
                                            <span class="badge bg-info rounded-pill"><?= (int)$m['total'] ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Pendientes asignar -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <strong>Pendientes</strong>
                            <div class="btn-group btn-group-sm" role="group">
                                <a class="btn btn-outline-primary" href="telefonos.php">Ver teléfonos</a>
                                <a class="btn btn-outline-success" href="sims.php">Ver SIMs</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-2 small text-muted">Teléfonos en almacén</div>
                            <?php if (empty($telefonosAlmacen)): ?>
                                <small class="text-muted d-block">Sin pendientes.</small>
                            <?php else: ?>
                                <ul class="list-group list-group-flush mb-3">
                                    <?php foreach ($telefonosAlmacen as $t): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><?= htmlspecialchars($t['marca'].' '.$t['modelo']) ?><?= $t['etiqueta'] ? ' ['.htmlspecialchars($t['etiqueta']).']' : '' ?></span>
                                            <a href="telefonos_editar.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">Asignar</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <div class="mb-2 small text-muted">SIMs libres</div>
                            <?php if (empty($simsLibres)): ?>
                                <small class="text-muted d-block">Sin pendientes.</small>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($simsLibres as $s): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><?= htmlspecialchars($s['numero']) ?> (<?= htmlspecialchars($s['operador']) ?>)</span>
                                            <a href="sims_editar.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-success">Asignar</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Departamentos con más equipos -->
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-light"><strong>Departamentos con más equipos</strong></div>
                <div class="card-body">
                    <?php if (empty($topDeptEquipos)): ?>
                        <small class="text-muted">Sin datos.</small>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($topDeptEquipos as $d): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($d['departamento']) ?>
                                    <span class="badge bg-secondary rounded-pill"><?= (int)$d['total'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
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
