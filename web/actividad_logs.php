<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/logger.php';

if (($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado');
}

ensureActividadLogsSchema($pdo);

$f_q          = trim($_GET['q'] ?? '');
$f_usuario    = trim($_GET['usuario'] ?? '');
$f_accion     = trim($_GET['accion'] ?? '');
$f_modulo     = trim($_GET['modulo'] ?? '');
$f_nivel      = strtoupper(trim($_GET['nivel'] ?? ''));
$f_desde      = trim($_GET['desde'] ?? '');
$f_hasta      = trim($_GET['hasta'] ?? '');
$f_limit      = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$f_limit      = max(20, min(200, $f_limit));
$page         = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page         = max(1, $page);
$offset       = ($page - 1) * $f_limit;

$where = [];
$params = [];

if ($f_q !== '') {
    $where[] = "(
        l.accion LIKE :q
        OR l.detalles LIKE :q
        OR l.usuario_tip LIKE :q
        OR l.ip LIKE :q
        OR COALESCE(l.ruta, '') LIKE :q
        OR COALESCE(l.user_agent, '') LIKE :q
    )";
    $params[':q'] = '%' . $f_q . '%';
}
if ($f_usuario !== '') {
    $where[] = 'l.usuario_tip = :usuario';
    $params[':usuario'] = $f_usuario;
}
if ($f_accion !== '') {
    $where[] = 'l.accion = :accion';
    $params[':accion'] = $f_accion;
}
if ($f_modulo !== '') {
    $where[] = 'COALESCE(l.modulo, \'\') = :modulo';
    $params[':modulo'] = $f_modulo;
}
if ($f_nivel !== '' && in_array($f_nivel, ['INFO', 'WARN', 'ERROR', 'SECURITY'], true)) {
    $where[] = 'COALESCE(l.nivel, \'INFO\') = :nivel';
    $params[':nivel'] = $f_nivel;
}
if ($f_desde !== '') {
    $where[] = 'DATE(l.creado_en) >= :desde';
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $where[] = 'DATE(l.creado_en) <= :hasta';
    $params[':hasta'] = $f_hasta;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sqlCount = "SELECT COUNT(*) FROM actividad_logs l $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) {
    $stmtCount->bindValue($k, $v, PDO::PARAM_STR);
}
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $f_limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $f_limit;
}

$sqlLogs = "
    SELECT
        l.id,
        l.usuario_tip,
        l.accion,
        COALESCE(l.modulo, 'GENERAL') AS modulo,
        COALESCE(l.nivel, 'INFO') AS nivel,
        l.detalles,
        l.ip,
        l.ruta,
        l.metodo,
        l.user_agent,
        l.creado_en
    FROM actividad_logs l
    $whereSql
    ORDER BY l.id DESC
    LIMIT :limit OFFSET :offset
";
$stmtLogs = $pdo->prepare($sqlLogs);
foreach ($params as $k => $v) {
    $stmtLogs->bindValue($k, $v, PDO::PARAM_STR);
}
$stmtLogs->bindValue(':limit', $f_limit, PDO::PARAM_INT);
$stmtLogs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

$usuarios = $pdo->query("SELECT usuario_tip, COUNT(*) c FROM actividad_logs GROUP BY usuario_tip ORDER BY usuario_tip ASC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
$acciones = $pdo->query("SELECT accion FROM actividad_logs GROUP BY accion ORDER BY MAX(id) DESC LIMIT 300")->fetchAll(PDO::FETCH_COLUMN);
$modulos  = $pdo->query("SELECT COALESCE(modulo, 'GENERAL') AS modulo FROM actividad_logs GROUP BY COALESCE(modulo, 'GENERAL') ORDER BY modulo ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(DATE(creado_en) = CURDATE()) AS hoy,
        SUM(creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS semana,
        SUM(COALESCE(nivel, 'INFO') = 'ERROR') AS errores,
        COUNT(DISTINCT usuario_tip) AS usuarios
    FROM actividad_logs
")->fetch(PDO::FETCH_ASSOC);

function badgeNivel(string $nivel): string
{
    $n = strtoupper($nivel);
    if ($n === 'ERROR') {
        return 'danger';
    }
    if ($n === 'WARN') {
        return 'warning';
    }
    if ($n === 'SECURITY') {
        return 'dark';
    }
    return 'secondary';
}

function buildPageLink(int $newPage): string
{
    $query = $_GET;
    $query['page'] = $newPage;
    return 'actividad_logs.php?' . http_build_query($query);
}

include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Registro de actividad</h2>
        <div class="text-muted small">Total logs: <?= number_format((int)($stats['total'] ?? 0), 0, ',', '.') ?></div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Hoy</div>
                    <div class="h5 mb-0"><?= number_format((int)($stats['hoy'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Últimos 7 días</div>
                    <div class="h5 mb-0"><?= number_format((int)($stats['semana'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Errores</div>
                    <div class="h5 mb-0 text-danger"><?= number_format((int)($stats['errores'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Usuarios distintos</div>
                    <div class="h5 mb-0"><?= number_format((int)($stats['usuarios'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($f_q) ?>" placeholder="acción, detalle, ruta, ip...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Usuario</label>
                    <select name="usuario" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <?php $tip = (string)$u['usuario_tip']; ?>
                            <option value="<?= htmlspecialchars($tip) ?>" <?= $f_usuario === $tip ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tip) ?> (<?= (int)$u['c'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Acción</label>
                    <select name="accion" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= $f_accion === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Módulo</label>
                    <select name="modulo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $f_modulo === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Nivel</label>
                    <select name="nivel" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['INFO', 'WARN', 'ERROR', 'SECURITY'] as $n): ?>
                            <option value="<?= $n ?>" <?= $f_nivel === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($f_desde) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($f_hasta) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Filas</label>
                    <select name="limit" class="form-select form-select-sm">
                        <?php foreach ([20, 50, 100, 200] as $l): ?>
                            <option value="<?= $l ?>" <?= $f_limit === $l ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 d-flex justify-content-end gap-2">
                    <a href="actividad_logs.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><strong>Resultados</strong></span>
            <span class="small text-muted">
                Mostrando <?= $total > 0 ? ($offset + 1) : 0 ?>-<?= min($offset + $f_limit, $total) ?> de <?= number_format($total, 0, ',', '.') ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:120px;">Fecha</th>
                        <th style="width:120px;">Usuario</th>
                        <th style="width:120px;">Módulo</th>
                        <th style="width:110px;">Nivel</th>
                        <th style="width:220px;">Acción</th>
                        <th>Detalles</th>
                        <th style="width:110px;">IP</th>
                        <th style="width:240px;">Ruta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= (int)$log['id'] ?></td>
                                <td class="small"><?= htmlspecialchars((string)$log['creado_en']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string)$log['usuario_tip']) ?></span></td>
                                <td><span class="badge bg-info-subtle text-info-emphasis border"><?= htmlspecialchars((string)$log['modulo']) ?></span></td>
                                <td><span class="badge bg-<?= badgeNivel((string)$log['nivel']) ?>"><?= htmlspecialchars((string)$log['nivel']) ?></span></td>
                                <td><code><?= htmlspecialchars((string)$log['accion']) ?></code></td>
                                <td class="small">
                                    <div class="text-break" style="max-width: 420px; white-space: pre-line;"><?= htmlspecialchars((string)($log['detalles'] ?? '')) ?></div>
                                    <?php if (!empty($log['user_agent'])): ?>
                                        <div class="text-muted mt-1 text-break">UA: <?= htmlspecialchars((string)$log['user_agent']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string)($log['ip'] ?? '')) ?></td>
                                <td class="small">
                                    <div><span class="badge bg-secondary"><?= htmlspecialchars((string)($log['metodo'] ?? '-')) ?></span></div>
                                    <div class="text-muted text-break"><?= htmlspecialchars((string)($log['ruta'] ?? '')) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Sin resultados para los filtros seleccionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="small text-muted">Página <?= $page ?> de <?= $totalPages ?></div>
                <div class="btn-group btn-group-sm">
                    <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? htmlspecialchars(buildPageLink($page - 1)) : '#' ?>">Anterior</a>
                    <a class="btn btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page < $totalPages ? htmlspecialchars(buildPageLink($page + 1)) : '#' ?>">Siguiente</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
