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

    <div class="row mb-3">
    <div class="col-md-4">
        <label for="buscarIp" class="form-label mb-1">Buscar IP</label>
        <input type="text"
               id="buscarIp"
               class="form-control form-control-sm"
               placeholder="Ej: 10.52.2.106">
    </div>
</div>




   <div class="accordion" id="accordionRedes">

<?php foreach ($redes as $red): ?>
<?php
    $redId        = (int)$red['id'];
    $direccionRed = trim((string)$red['direccion_red']);
    $mascaraDb    = isset($red['mascara']) ? trim((string)$red['mascara']) : '';
    $listaUsadas  = $ipsPorRed[$redId] ?? [];

    // ============================
    // 1) Obtener IP base y CIDR
    // ============================

    $ipBase = $direccionRed;
    $cidr   = null;

    // Caso "10.52.2.1/24"
    if (strpos($direccionRed, '/') !== false) {
        [$ipBaseRaw, $cidrRaw] = explode('/', $direccionRed, 2);
        $ipBase = trim($ipBaseRaw);
        $cidrRaw = trim($cidrRaw);
        if ($cidrRaw !== '' && ctype_digit($cidrRaw)) {
            $cidr = (int)$cidrRaw;
        }
    }

    // Si no venía CIDR en direccion_red, usar campo mascara
    if ($cidr === null && $mascaraDb !== '') {

        if (strpos($mascaraDb, '.') !== false) {
            // Máscara tipo 255.255.255.0 -> CIDR
            $parts = array_map('intval', explode('.', $mascaraDb));
            if (count($parts) === 4) {
                $binMask = '';
                foreach ($parts as $p) {
                    $binMask .= str_pad(decbin($p), 8, '0', STR_PAD_LEFT);
                }
                $cidr = substr_count($binMask, '1');
            }
        } elseif (ctype_digit($mascaraDb)) {
            $cidr = (int)$mascaraDb;
        }
    }

    // Si sigue sin CIDR, no podemos calcular nada
    if ($cidr === null) {
        ?>
        <div class="alert alert-warning">
            Red <?= htmlspecialchars($red['nombre']) ?> mal definida (<?= htmlspecialchars($direccionRed) ?> / <?= htmlspecialchars($mascaraDb) ?>)
        </div>
        <?php
        continue;
    }

    $ipLong = ip2long($ipBase);
    if ($ipLong === false) {
        ?>
        <div class="alert alert-warning">
            IP base inválida en la red <?= htmlspecialchars($red['nombre']) ?> (<?= htmlspecialchars($ipBase) ?>)
        </div>
        <?php
        continue;
    }

    // ============================
    // 2) Calcular rango de hosts
    // ============================

    $maskLong      = -1 << (32 - $cidr);
    $networkLong   = $ipLong & $maskLong;
    $broadcastLong = $networkLong | ~$maskLong;

    // Rango de hosts (para /31 o /32, tomamos toda la red)
    if ($cidr >= 31) {
        $firstHostLong = $networkLong;
        $lastHostLong  = $broadcastLong;
    } else {
        $firstHostLong = $networkLong + 1;
        $lastHostLong  = $broadcastLong - 1;
    }

    if ($firstHostLong > $lastHostLong) {
        ?>
        <div class="alert alert-warning">
            Rango de hosts inválido en <?= htmlspecialchars($red['nombre']) ?>
        </div>
        <?php
        continue;
    }

    $totalPosibles = $lastHostLong - $firstHostLong + 1;

    // ============================
    // 3) Contar IPs usadas
    // ============================

    $usadas = [];
    foreach ($listaUsadas as $row) {
        $ipU = $row['ip'] ?? null;
        $ipLongU = $ipU !== null ? ip2long($ipU) : false;
        if ($ipLongU === false) continue;

        if ($ipLongU >= $firstHostLong && $ipLongU <= $lastHostLong) {
            $usadas[$ipLongU] = true;
        }
    }

    $totalUsadas = count($usadas);
    $totalLibres = max(0, $totalPosibles - $totalUsadas);

    // ============================
    // 4) Primeras IPs libres
    // ============================

    $ipsLibres = [];
    for ($ipLongIter = $firstHostLong;
         $ipLongIter <= $lastHostLong && count($ipsLibres) < 10;
         $ipLongIter++) {

        if (!isset($usadas[$ipLongIter])) {
            $ipsLibres[] = long2ip($ipLongIter);
        }
    }

    // IP de red "bonita" para mostrar (10.52.2.0/24, etc)
    $ipNetworkStr = long2ip($networkLong);
?>

    <div class="accordion-item mb-3">
        <h2 class="accordion-header" id="heading-<?= $redId ?>">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapse-<?= $redId ?>"
                    aria-expanded="false"
                    aria-controls="collapse-<?= $redId ?>">

                <?= htmlspecialchars($red['nombre']) ?>
                &nbsp; (<?= htmlspecialchars($ipNetworkStr) ?>/<?= $cidr ?>)

                <span class="ms-auto">
                    <span class="badge bg-secondary">Total: <?= $totalPosibles ?> IPs</span>
                    <span class="badge bg-success ms-1">Libres: <?= $totalLibres ?></span>
                    <span class="badge bg-danger ms-1">Usadas: <?= $totalUsadas ?></span>
                </span>
            </button>
        </h2>

        <div id="collapse-<?= $redId ?>"
             class="accordion-collapse collapse"
             aria-labelledby="heading-<?= $redId ?>"
                data-bs-parent="#accordionRedes">

            <div class="accordion-body">

                <?php if (!empty($ipsLibres)): ?>
                    <div class="mb-2">
                        <strong>Primeras IPs libres sugeridas:</strong>
                        <?php foreach ($ipsLibres as $ipLibre): ?>
                            <span class="badge bg-light text-muted border">
                                <?= htmlspecialchars($ipLibre) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($totalLibres > count($ipsLibres)): ?>
                            <span class="text-muted">… (hay más)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; 

                $listaUsadas  = $ipsPorRed[$redId] ?? [];

// ORDENAR IPs ASCENDENTE
usort($listaUsadas, function ($a, $b) {
    $ipA = isset($a['ip']) ? ip2long($a['ip']) : 0;
    $ipB = isset($b['ip']) ? ip2long($b['ip']) : 0;
    return $ipA <=> $ipB;
}); ?>

                <table class="table table-sm table-hover align-middle mb-0 tabla-ips-red">
                    <thead class="table-light">
                        <tr>
                            <th>IP</th>
                            <th>MAC</th>
                            <th>Equipo / Hostname</th>
                            <th>Usuario / Depto.</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                  <tbody>
    <?php foreach ($listaUsadas as $row): ?>
        <tr>
            <td>
                <span class="badge bg-secondary">
                    <?= htmlspecialchars($row['ip']) ?>
                </span>
            </td>

            <td>
                <small><?= htmlspecialchars($row['mac'] ?? '') ?></small>
            </td>

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

            <td>
                <?= htmlspecialchars($row['equipo_tipo'] ?? '') ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

                </table>

            </div>
        </div>
    </div>

<?php endforeach; ?>

</div> <!-- /accordionRedes -->

<?php endif; ?>

<script>

document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.DataTable) {

        var opciones = {
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            order: [],          // respeta el orden de las filas del HTML
            searching: true,    // necesitamos el motor de búsqueda activo
            language: {
                url: 'vendor/datatables/i18n/es-ES.json'
            }
        };

        // Inicializar TODAS las tablas de IPs de golpe
        var tablasIps = $('.tabla-ips-red').DataTable(opciones);
        // ↑ Importante: esto devuelve un API que apunta a TODAS las tablas coincidentes

        // Buscador global por IP
        $('#buscarIp').on('keyup', function () {
            var valor = this.value;
            tablasIps.search(valor).draw();   // aplica el filtro a todas las tablas
        });
    }
});




</script>

<?php
require_once __DIR__ . '/includes/footer.php';
