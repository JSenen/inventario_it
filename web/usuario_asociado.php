<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';

ensureSimsSchema($pdo);

$tipParam = trim((string)($_GET['tip'] ?? ''));
if ($tipParam === '' && !empty($_SESSION['tip'])) {
    $tipParam = (string)$_SESSION['tip'];
}
$tip = strtoupper($tipParam);
$buscarLike = '%' . $tip . '%';

$tipsDestacados = [];
$sqlTips = "
    SELECT
        z.tip,
        SUM(z.equipos_count) AS equipos_count,
        SUM(z.telefonos_count) AS telefonos_count,
        SUM(z.sims_count) AS sims_count,
        (SUM(z.equipos_count) + SUM(z.telefonos_count) + SUM(z.sims_count)) AS total_count
    FROM (
        SELECT
            UPPER(TRIM(e.usuario_asignado)) AS tip,
            COUNT(*) AS equipos_count,
            0 AS telefonos_count,
            0 AS sims_count
        FROM equipos e
        WHERE COALESCE(TRIM(e.usuario_asignado), '') <> ''
          AND UPPER(TRIM(e.usuario_asignado)) <> 'VARIOS'
        GROUP BY UPPER(TRIM(e.usuario_asignado))

        UNION ALL

        SELECT
            UPPER(TRIM(t.usuario_asignado)) AS tip,
            0 AS equipos_count,
            COUNT(*) AS telefonos_count,
            0 AS sims_count
        FROM telefonos t
        WHERE COALESCE(TRIM(t.usuario_asignado), '') <> ''
          AND UPPER(TRIM(t.usuario_asignado)) <> 'VARIOS'
        GROUP BY UPPER(TRIM(t.usuario_asignado))

        UNION ALL

        SELECT
            UPPER(TRIM(t.usuario_asignado)) AS tip,
            0 AS equipos_count,
            0 AS telefonos_count,
            COUNT(DISTINCT s.id) AS sims_count
        FROM telefono_sim ts
        JOIN sims s ON s.id = ts.sim_id
        JOIN telefonos t ON t.id = ts.telefono_id
        WHERE ts.fecha_liberacion IS NULL
          AND COALESCE(TRIM(t.usuario_asignado), '') <> ''
          AND UPPER(TRIM(t.usuario_asignado)) <> 'VARIOS'
        GROUP BY UPPER(TRIM(t.usuario_asignado))

        UNION ALL

        SELECT
            UPPER(TRIM(e.usuario_asignado)) AS tip,
            0 AS equipos_count,
            0 AS telefonos_count,
            COUNT(DISTINCT s.id) AS sims_count
        FROM equipo_sim es
        JOIN sims s ON s.id = es.sim_id
        JOIN equipos e ON e.id = es.equipo_id
        WHERE es.fecha_liberacion IS NULL
          AND COALESCE(TRIM(e.usuario_asignado), '') <> ''
          AND UPPER(TRIM(e.usuario_asignado)) <> 'VARIOS'
        GROUP BY UPPER(TRIM(e.usuario_asignado))
    ) z
    GROUP BY z.tip
    ORDER BY total_count DESC, z.tip ASC
    LIMIT 15
";

try {
    $stTips = $pdo->query($sqlTips);
    $tipsDestacados = $stTips->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tipsDestacados = [];
}

$equipos = [];
$telefonos = [];
$simsAsociadas = [];

if ($tip !== '') {
    $sqlEquipos = "
        SELECT
            e.id,
            e.etiqueta,
            e.tipo,
            e.marca,
            e.modelo,
            e.numero_serie,
            e.hostname,
            e.estado,
            e.departamento,
            e.ubicacion,
            s.nombre AS seccion,
            e.usuario_asignado
        FROM equipos e
        LEFT JOIN secciones s ON s.id = e.seccion_id
        WHERE COALESCE(TRIM(e.usuario_asignado), '') <> ''
          AND (UPPER(e.usuario_asignado) = :tip OR UPPER(e.usuario_asignado) LIKE :tip_like)
        ORDER BY e.estado ASC, e.tipo ASC, e.marca ASC, e.modelo ASC, e.id DESC
    ";
    $stEq = $pdo->prepare($sqlEquipos);
    $stEq->execute([':tip' => $tip, ':tip_like' => $buscarLike]);
    $equipos = $stEq->fetchAll(PDO::FETCH_ASSOC);

    $sqlTelefonos = "
        SELECT
            t.id,
            t.etiqueta,
            t.marca,
            t.modelo,
            t.imei,
            t.numero_serie,
            t.estado,
            t.departamento,
            t.ubicacion,
            t.seccion,
            t.usuario_asignado
        FROM telefonos t
        WHERE COALESCE(TRIM(t.usuario_asignado), '') <> ''
          AND (UPPER(t.usuario_asignado) = :tip OR UPPER(t.usuario_asignado) LIKE :tip_like)
        ORDER BY t.estado ASC, t.marca ASC, t.modelo ASC, t.id DESC
    ";
    $stTel = $pdo->prepare($sqlTelefonos);
    $stTel->execute([':tip' => $tip, ':tip_like' => $buscarLike]);
    $telefonos = $stTel->fetchAll(PDO::FETCH_ASSOC);

    $sqlSims = "
        SELECT * FROM (
            SELECT
                s.id,
                s.etiqueta,
                s.numero,
                s.iccid,
                s.operador,
                s.estado,
                'Telefono' AS origen,
                t.id AS origen_id,
                CONCAT(COALESCE(t.marca, ''), ' ', COALESCE(t.modelo, '')) AS origen_nombre,
                t.usuario_asignado AS usuario_origen
            FROM sims s
            JOIN telefono_sim ts ON ts.sim_id = s.id AND ts.fecha_liberacion IS NULL
            JOIN telefonos t ON t.id = ts.telefono_id
            WHERE COALESCE(TRIM(t.usuario_asignado), '') <> ''
              AND (UPPER(t.usuario_asignado) = :tip1 OR UPPER(t.usuario_asignado) LIKE :tip1_like)

            UNION ALL

            SELECT
                s.id,
                s.etiqueta,
                s.numero,
                s.iccid,
                s.operador,
                s.estado,
                'Equipo PTI' AS origen,
                e.id AS origen_id,
                CONCAT(COALESCE(e.marca, ''), ' ', COALESCE(e.modelo, '')) AS origen_nombre,
                e.usuario_asignado AS usuario_origen
            FROM sims s
            JOIN equipo_sim es ON es.sim_id = s.id AND es.fecha_liberacion IS NULL
            JOIN equipos e ON e.id = es.equipo_id
            WHERE COALESCE(TRIM(e.usuario_asignado), '') <> ''
              AND (UPPER(e.usuario_asignado) = :tip2 OR UPPER(e.usuario_asignado) LIKE :tip2_like)
        ) x
        ORDER BY x.estado ASC, x.id DESC, x.origen ASC
    ";
    $stSims = $pdo->prepare($sqlSims);
    $stSims->execute([
        ':tip1' => $tip,
        ':tip1_like' => $buscarLike,
        ':tip2' => $tip,
        ':tip2_like' => $buscarLike,
    ]);
    $simsAsociadas = $stSims->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0">Activos asociados por TIP</h2>
            <div class="text-muted small">Consulta todo lo asignado a un usuario en equipos, telefonos y SIMs.</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">TIPs destacados (excluye VARIOS)</div>
        <div class="card-body">
            <?php if (empty($tipsDestacados)): ?>
                <span class="text-muted">No hay datos suficientes para destacar TIPs.</span>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php foreach ($tipsDestacados as $rowTip): ?>
                        <a
                            href="usuario_asociado.php?tip=<?= urlencode((string)$rowTip['tip']) ?>"
                            class="btn btn-sm <?= $tip === (string)$rowTip['tip'] ? 'btn-primary' : 'btn-outline-primary' ?>"
                        >
                            <?= htmlspecialchars((string)$rowTip['tip']) ?>
                            <span class="badge text-bg-light ms-1"><?= (int)$rowTip['total_count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted">
                    Total = equipos + telefonos + SIMs activas asociadas.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">TIP o texto de usuario</label>
                    <input
                        type="text"
                        name="tip"
                        class="form-control"
                        value="<?= htmlspecialchars($tipParam) ?>"
                        placeholder="Ej: T12345"
                    >
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="usuario_asociado.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </div>
        </div>
    </form>

    <?php if ($tip === ''): ?>
        <div class="alert alert-info">Introduce un TIP para ver todo lo que tiene asociado el usuario.</div>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="card text-bg-primary h-100">
                    <div class="card-body">
                        <div class="small text-uppercase">Equipos</div>
                        <div class="fs-3 fw-bold"><?= count($equipos) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-bg-success h-100">
                    <div class="card-body">
                        <div class="small text-uppercase">Telefonos</div>
                        <div class="fs-3 fw-bold"><?= count($telefonos) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-bg-info h-100">
                    <div class="card-body">
                        <div class="small text-uppercase">SIMs activas</div>
                        <div class="fs-3 fw-bold"><?= count($simsAsociadas) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Equipos asociados</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Etiqueta</th>
                            <th>Tipo</th>
                            <th>Equipo</th>
                            <th>Serie</th>
                            <th>Servicio</th>
                            <th>Estado</th>
                            <th>Ubicacion</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($equipos)): ?>
                        <tr><td colspan="9" class="text-muted">Sin equipos asociados para este TIP.</td></tr>
                    <?php else: ?>
                        <?php foreach ($equipos as $e): ?>
                            <tr>
                                <td><?= (int)$e['id'] ?></td>
                                <td><?= htmlspecialchars($e['etiqueta'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($e['tipo'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($e['numero_serie'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($e['hostname'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($e['estado'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(trim(($e['departamento'] ?? '-') . ' / ' . ($e['seccion'] ?? '-') . ' / ' . ($e['ubicacion'] ?? '-'))) ?></td>
                                <td>
                                    <a href="equipo_ver.php?id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Telefonos asociados</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Etiqueta</th>
                            <th>Telefono</th>
                            <th>IMEI</th>
                            <th>Serie</th>
                            <th>Estado</th>
                            <th>Ubicacion</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($telefonos)): ?>
                        <tr><td colspan="8" class="text-muted">Sin telefonos asociados para este TIP.</td></tr>
                    <?php else: ?>
                        <?php foreach ($telefonos as $t): ?>
                            <tr>
                                <td><?= (int)$t['id'] ?></td>
                                <td><?= htmlspecialchars($t['etiqueta'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(trim(($t['marca'] ?? '') . ' ' . ($t['modelo'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($t['imei'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($t['numero_serie'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($t['estado'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(trim(($t['departamento'] ?? '-') . ' / ' . ($t['seccion'] ?? '-') . ' / ' . ($t['ubicacion'] ?? '-'))) ?></td>
                                <td>
                                    <a href="telefonos_ver.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">SIMs asociadas (via telefono o equipo PTI)</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Etiqueta</th>
                            <th>Numero</th>
                            <th>Operador</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($simsAsociadas)): ?>
                        <tr><td colspan="7" class="text-muted">Sin SIMs asociadas para este TIP.</td></tr>
                    <?php else: ?>
                        <?php foreach ($simsAsociadas as $s): ?>
                            <tr>
                                <td><?= (int)$s['id'] ?></td>
                                <td><?= htmlspecialchars($s['etiqueta'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['numero'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['operador'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['estado'] ?: '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($s['origen']) ?>:
                                    <?php if ((string)$s['origen'] === 'Telefono'): ?>
                                        <a href="telefonos_ver.php?id=<?= (int)$s['origen_id'] ?>"><?= htmlspecialchars(trim((string)$s['origen_nombre'])) ?></a>
                                    <?php else: ?>
                                        <a href="equipo_ver.php?id=<?= (int)$s['origen_id'] ?>"><?= htmlspecialchars(trim((string)$s['origen_nombre'])) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="sims_ver.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">Ver SIM</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
