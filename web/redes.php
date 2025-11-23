<?php
require_once __DIR__ . '/config.php';

// Obtenemos todas las redes
$stmtRedes = $pdo->query("SELECT id, nombre, direccion_red, mascara FROM redes ORDER BY id ASC");

$redes = $stmtRedes->fetchAll(PDO::FETCH_ASSOC);

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
    Resumen de IPs por red. CDIR /24 (1–254) para el cálculo de libres
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
        $prefix       = calcularPrefix($direccionRed);

        $textoRed = $direccionRed;
if (!empty($mascara)) {
    $textoRed .= '/' . $mascara;
}

        $inicio = 1;
        $fin    = 254;

        $usadas = [];
        $listaUsadas = $ipsPorRed[$redId] ?? [];

        // Marcar últimos octetos usados
        foreach ($listaUsadas as $row) {
            $ip = $row['ip'];
            if ($prefix && strpos($ip, $prefix) === 0) {
                $lastOctet = (int)substr($ip, strrpos($ip, '.') + 1);
                if ($lastOctet >= $inicio && $lastOctet <= $fin) {
                    $usadas[$lastOctet] = true;
                }
            }
        }

        $totalPosibles = ($fin - $inicio + 1);
        $totalUsadas   = count($usadas);
        $totalLibres   = $totalPosibles - $totalUsadas;

        // Generar una pequeña muestra de IPs libres
        $muestraLibres = [];
        if ($prefix) {
            for ($i = $inicio; $i <= $fin && count($muestraLibres) < 10; $i++) {
                if (!isset($usadas[$i])) {
                    $muestraLibres[] = $prefix . $i;
                }
            }
        }
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
                <?php if ($prefix === null): ?>
                    <div class="alert alert-warning">
                        No se ha podido interpretar el prefijo de esta red a partir de
                        <code><?= htmlspecialchars($direccionRed) ?></code>. Revisa el formato
                        (ejemplo válido: <code>10.52.2.0</code> o <code>10.52.2.0/24</code>).
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
