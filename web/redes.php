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
// $stmtIps = $pdo->query("
//     SELECT 
//         ip.red_id,
//         ip.ip,
//         ip.mac,
//         e.id        AS equipo_id,
//         e.hostname  AS equipo_hostname,
//         e.usuario_asignado AS equipo_usuario,
//         e.tipo      AS equipo_tipo,
//         e.departamento AS equipo_departamento
//     FROM ips_equipos ip
//     LEFT JOIN equipos e ON e.id = ip.equipo_id
//     ORDER BY ip.red_id ASC, ip.ip ASC
// ");

$stmtIps = $pdo->query("
    SELECT 
        ip.id,
        ip.red_id,
        ip.ip,
        ip.mac,
        ip.estado,
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

    // $usadas = [];
    // foreach ($listaUsadas as $row) {
    //     $ipU = $row['ip'] ?? null;
    //     $ipLongU = $ipU !== null ? ip2long($ipU) : false;
    //     if ($ipLongU === false) continue;

    //     if ($ipLongU >= $firstHostLong && $ipLongU <= $lastHostLong) {
    //         $usadas[$ipLongU] = true;
    //     }
    // }

    // $totalUsadas = count($usadas);
    // $totalLibres = max(0, $totalPosibles - $totalUsadas);

    // ============================
// 3) Contar IPs usadas y reservadas
// ============================

$usadas         = [];   // para calcular IPs libres
$totalUsadas    = 0;    // IPs en uso (equipos)
$totalReservadas = 0;   // IPs marcadas como reservadas

foreach ($listaUsadas as $row) {
    $ipU = $row['ip'] ?? null;
    $ipLongU = $ipU !== null ? ip2long($ipU) : false;
    if ($ipLongU === false) {
        continue;
    }

    if ($ipLongU >= $firstHostLong && $ipLongU <= $lastHostLong) {
        // Esta IP está dentro del rango de la red
        $usadas[$ipLongU] = true;

        // Miramos el estado (USADA / RESERVADA)
        $estadoFila = $row['estado'] ?? 'USADA';

        if ($estadoFila === 'RESERVADA') {
            $totalReservadas++;
        } else {
            // Todo lo que no sea RESERVADA lo contamos como USADA
            $totalUsadas++;
        }
    }
}

// Libres = total posibles - usadas - reservadas
$totalLibres = max(0, $totalPosibles - $totalUsadas - $totalReservadas);


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
                    <span class="badge bg-warning text-dark ms-1">Reservadas: <?= $totalReservadas ?></span>
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
            <span class="badge bg-light text-muted border me-1">
                <?= htmlspecialchars($ipLibre) ?>
            </span>
            <button
                type="button"
                class="btn btn-sm btn-outline-warning reservar-ip me-2"
                data-red-id="<?= (int)$redId ?>"
                data-ip="<?= htmlspecialchars($ipLibre) ?>"
            >
                Reservar
            </button>
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
            <th>Estado</th>
            <th>Usuario / Depto.</th>
            <th>Tipo</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($listaUsadas as $row): ?>
        <tr>
            <!-- IP -->
            <td>
                <span class="badge bg-secondary">
                    <?= htmlspecialchars($row['ip']) ?>
                </span>
            </td>

            <!-- MAC -->
            <td><?= htmlspecialchars($row['mac'] ?? '') ?></td>

            <!-- Equipo / Hostname -->
            <td>
                <?php if (!empty($row['equipo_id'])): ?>
                    <a href="equipos_ver.php?id=<?= (int)$row['equipo_id'] ?>">
                        <?= htmlspecialchars($row['equipo_hostname'] ?? 'Ver equipo') ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">Sin equipo vinculado</span>
                <?php endif; ?>
            </td>

            <!-- ESTADO USADA / RESERVADA -->
            <td>
                <?php
                    $estadoActual = $row['estado'] ?? 'USADA';
                    if ($estadoActual !== 'RESERVADA') {
                        $estadoActual = 'USADA';
                    }
                ?>
                <select
                    class="form-select form-select-sm cambiar-estado-ip"
                    data-id-ip="<?= (int)$row['id'] ?>"
                >
                    <option value="USADA"     <?= $estadoActual === 'USADA' ? 'selected' : '' ?>>USADA</option>
                    <option value="RESERVADA" <?= $estadoActual === 'RESERVADA' ? 'selected' : '' ?>>RESERVADA</option>
                </select>
            </td>

            <!-- Usuario / Depto. -->
            <td>
                <?= htmlspecialchars($row['equipo_usuario'] ?? '') ?><br>
                <small class="text-muted">
                    <?= htmlspecialchars($row['equipo_departamento'] ?? '') ?>
                </small>
            </td>

            <!-- Tipo -->
            <td><?= htmlspecialchars($row['equipo_tipo'] ?? '') ?></td>

            <!-- Acciones: LIBERAR -->
            <td>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger liberar-ip"
                    data-id-ip="<?= (int)$row['id'] ?>"
                    data-ip="<?= htmlspecialchars($row['ip']) ?>"
                >
                    Liberar
                </button>
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
<script src="vendor/jquery/jquery-3.7.1.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Comprobamos que jQuery está cargado
    if (typeof $ === 'undefined') {
        console.error('jQuery no está cargado en redes.php');
        return;
    }

    // Cambiar USADA <-> RESERVADA
    $(document).on('change', '.cambiar-estado-ip', function (e) {
        e.preventDefault();

        var select   = $(this);
        var idIp     = parseInt(select.data('id-ip'), 10);
        var nuevoEst = select.val();

        console.log('cambiar-estado-ip click', { idIp, nuevoEst });

        if (!idIp) return;

        $.post('ip_cambiar_estado.php', {
            id_ip: idIp,
            estado: nuevoEst
        }, function (respuesta) {
            console.log('Respuesta cambiar estado:', respuesta);
        }).fail(function (xhr) {
            console.error('Error cambiar estado:', xhr.responseText);
            alert('Error al cambiar el estado de la IP.');
        });
    });

    // Reservar IP libre desde las sugeridas
    $(document).on('click', '.reservar-ip', function (e) {
        e.preventDefault();

        var btn   = $(this);
        var redId = parseInt(btn.data('red-id'), 10);
        var ip    = btn.data('ip');

        console.log('reservar-ip click', { redId, ip });

        if (!redId || !ip) return;

        if (!confirm('¿Reservar la IP ' + ip + ' para pruebas?')) return;

        $.post('ip_reservar.php', {
            red_id: redId,
            ip: ip
        }, function (respuesta) {
            console.log('Respuesta reservar:', respuesta);
            location.reload();
        }).fail(function (xhr) {
            console.error('Error reservar:', xhr.responseText);
            alert('Error al reservar la IP.');
        });
    });

    // Liberar IP (borrar de ips_equipos)
    $(document).on('click', '.liberar-ip', function (e) {
        e.preventDefault();

        var btn   = $(this);
        var idIp  = parseInt(btn.data('id-ip'), 10);
        var ip    = btn.data('ip');

        console.log('liberar-ip click', { idIp, ip });

        if (!idIp) return;

        if (!confirm('¿Liberar la IP ' + ip + '?\nDejará de estar asociada a equipo o reserva.')) return;

        $.post('ip_liberar.php', {
            id_ip: idIp
        }, function (respuesta) {
            console.log('Respuesta liberar:', respuesta);
            location.reload();
        }).fail(function (xhr) {
            console.error('Error liberar:', xhr.responseText);
            alert('Error al liberar la IP.');
        });
    });

});
</script>


<?php
require_once __DIR__ . '/includes/footer.php';
