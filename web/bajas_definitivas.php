<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/bajas_definitivas_schema.php';

ensureBajasDefinitivasSchema($pdo);

function bdFlashAdd(string $tipo, string $mensaje): void
{
    if (!isset($_SESSION['bd_flash']) || !is_array($_SESSION['bd_flash'])) {
        $_SESSION['bd_flash'] = [];
    }
    $_SESSION['bd_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function bdFlashConsume(): array
{
    $items = $_SESSION['bd_flash'] ?? [];
    unset($_SESSION['bd_flash']);
    return is_array($items) ? $items : [];
}

function bdGetPendingLote(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT *
        FROM bajas_definitivas_lotes
        WHERE estado = 'PENDIENTE'
        ORDER BY id DESC
        LIMIT 1
    ");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $row ?: null;
}

function bdCreatePendingLote(PDO $pdo, string $usuarioTip): array
{
    $prefijo = 'BD-' . date('Ymd') . '-';
    $stmtNum = $pdo->prepare("
        SELECT COUNT(*)
        FROM bajas_definitivas_lotes
        WHERE codigo LIKE :prefijo
    ");
    $stmtNum->execute([':prefijo' => $prefijo . '%']);
    $num = (int)$stmtNum->fetchColumn() + 1;
    $codigo = $prefijo . str_pad((string)$num, 3, '0', STR_PAD_LEFT);

    $ins = $pdo->prepare("
        INSERT INTO bajas_definitivas_lotes (codigo, estado, creado_por)
        VALUES (:codigo, 'PENDIENTE', :creado_por)
    ");
    $ins->execute([
        ':codigo'    => $codigo,
        ':creado_por'=> $usuarioTip !== '' ? $usuarioTip : 'DESCONOCIDO',
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'codigo' => $codigo,
        'estado' => 'PENDIENTE',
    ];
}

function bdGetOrCreatePendingLote(PDO $pdo, string $usuarioTip): array
{
    $lote = bdGetPendingLote($pdo);
    if ($lote) {
        return $lote;
    }
    return bdCreatePendingLote($pdo, $usuarioTip);
}

function bdNormalizeIds($raw): array
{
    $ids = [];
    if (!is_array($raw)) {
        return $ids;
    }
    foreach ($raw as $v) {
        $id = (int)$v;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function bdAgregarActivos(PDO $pdo, int $loteId, string $tipo, array $ids): array
{
    if (!$ids) {
        return ['agregados' => 0, 'omitidos' => 0, 'no_baja' => 0];
    }

    $idCsv = implode(',', array_map('intval', $ids));
    $agregados = 0;
    $omitidos = 0;

    $stmtPend = $pdo->prepare("
        SELECT i.activo_id
        FROM bajas_definitivas_items i
        JOIN bajas_definitivas_lotes l ON l.id = i.lote_id
        WHERE l.estado = 'PENDIENTE'
          AND i.activo_tipo = :tipo
          AND i.activo_id IN ($idCsv)
    ");
    $stmtPend->execute([':tipo' => $tipo]);
    $enPendiente = array_map('intval', $stmtPend->fetchAll(PDO::FETCH_COLUMN));
    $mapPendiente = array_fill_keys($enPendiente, true);

    if ($tipo === 'equipo') {
        $rows = $pdo->query("
            SELECT id, etiqueta, tipo, marca, modelo, numero_serie, hostname, fecha_baja
            FROM equipos
            WHERE id IN ($idCsv) AND estado = 'Baja'
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $pdo->query("
            SELECT id, etiqueta, marca, modelo, imei, numero_serie, fecha_baja
            FROM telefonos
            WHERE id IN ($idCsv) AND estado = 'Baja'
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $noBaja = count($ids) - count($rows);

    $ins = $pdo->prepare("
        INSERT INTO bajas_definitivas_items (
            lote_id,
            activo_tipo,
            activo_id,
            activo_etiqueta,
            activo_descripcion,
            activo_identificador,
            estado_previo,
            fecha_baja_original
        ) VALUES (
            :lote_id,
            :activo_tipo,
            :activo_id,
            :activo_etiqueta,
            :activo_descripcion,
            :activo_identificador,
            'Baja',
            :fecha_baja_original
        )
    ");

    foreach ($rows as $r) {
        $idActivo = (int)$r['id'];
        if (isset($mapPendiente[$idActivo])) {
            $omitidos++;
            continue;
        }

        if ($tipo === 'equipo') {
            $desc = trim(
                trim((string)($r['tipo'] ?? '')) . ' ' .
                trim((string)($r['marca'] ?? '')) . ' ' .
                trim((string)($r['modelo'] ?? ''))
            );
            $idText = trim((string)($r['numero_serie'] ?? ''));
            if ($idText === '') {
                $idText = trim((string)($r['hostname'] ?? ''));
            }
        } else {
            $desc = trim(
                trim((string)($r['marca'] ?? '')) . ' ' .
                trim((string)($r['modelo'] ?? ''))
            );
            $idText = trim((string)($r['imei'] ?? ''));
            if ($idText === '') {
                $idText = trim((string)($r['numero_serie'] ?? ''));
            }
        }

        if ($desc === '') {
            $desc = ($tipo === 'equipo' ? 'Equipo' : 'Telefono') . ' #' . $idActivo;
        }

        $ins->execute([
            ':lote_id'              => $loteId,
            ':activo_tipo'          => $tipo,
            ':activo_id'            => $idActivo,
            ':activo_etiqueta'      => trim((string)($r['etiqueta'] ?? '')) ?: null,
            ':activo_descripcion'   => $desc,
            ':activo_identificador' => $idText !== '' ? $idText : null,
            ':fecha_baja_original'  => !empty($r['fecha_baja']) ? $r['fecha_baja'] : null,
        ]);

        $agregados++;
    }

    return [
        'agregados' => $agregados,
        'omitidos'  => $omitidos,
        'no_baja'   => max(0, $noBaja),
    ];
}

$usuarioTip = trim((string)($_SESSION['tip'] ?? 'DESCONOCIDO'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'add_equipos' || $accion === 'add_telefonos') {
            $tipo = $accion === 'add_equipos' ? 'equipo' : 'telefono';
            $ids = bdNormalizeIds($_POST[$tipo === 'equipo' ? 'equipos_ids' : 'telefonos_ids'] ?? []);
            if (!$ids) {
                bdFlashAdd('warning', 'No has seleccionado elementos para anadir al lote.');
                header('Location: bajas_definitivas.php');
                exit;
            }

            $pdo->beginTransaction();
            $lote = bdGetOrCreatePendingLote($pdo, $usuarioTip);
            $res = bdAgregarActivos($pdo, (int)$lote['id'], $tipo, $ids);
            $pdo->commit();

            $txtTipo = $tipo === 'equipo' ? 'equipos' : 'telefonos';
            bdFlashAdd(
                'success',
                "Lote {$lote['codigo']}: $txtTipo anadidos {$res['agregados']}, ya en cola {$res['omitidos']}, no validos {$res['no_baja']}."
            );
            header('Location: bajas_definitivas.php');
            exit;
        }

        if ($accion === 'remove_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId > 0) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    SELECT i.id
                    FROM bajas_definitivas_items i
                    JOIN bajas_definitivas_lotes l ON l.id = i.lote_id
                    WHERE i.id = :id AND l.estado = 'PENDIENTE'
                    LIMIT 1
                ");
                $stmt->execute([':id' => $itemId]);
                if ($stmt->fetchColumn()) {
                    $del = $pdo->prepare("DELETE FROM bajas_definitivas_items WHERE id = :id");
                    $del->execute([':id' => $itemId]);
                    bdFlashAdd('success', 'Elemento eliminado del lote pendiente.');
                } else {
                    bdFlashAdd('warning', 'No se pudo eliminar: el elemento no esta en un lote pendiente.');
                }
                $pdo->commit();
            }
            header('Location: bajas_definitivas.php');
            exit;
        }

        if ($accion === 'confirmar_lote') {
            $loteId = (int)($_POST['lote_id'] ?? 0);
            $puntoLimpio = trim((string)($_POST['punto_limpio'] ?? ''));
            $transportadoPor = trim((string)($_POST['transportado_por'] ?? ''));
            $observaciones = trim((string)($_POST['observaciones'] ?? ''));
            $fechaRaw = trim((string)($_POST['fecha_confirmacion'] ?? ''));

            $fechaConfirmacion = date('Y-m-d H:i:s');
            if ($fechaRaw !== '') {
                $ts = strtotime($fechaRaw);
                if ($ts !== false) {
                    $fechaConfirmacion = date('Y-m-d H:i:s', $ts);
                }
            }

            if ($loteId <= 0) {
                bdFlashAdd('danger', 'Lote no valido para confirmar.');
                header('Location: bajas_definitivas.php');
                exit;
            }

            $pdo->beginTransaction();
            $stmtLote = $pdo->prepare("
                SELECT *
                FROM bajas_definitivas_lotes
                WHERE id = :id AND estado = 'PENDIENTE'
                LIMIT 1
                FOR UPDATE
            ");
            $stmtLote->execute([':id' => $loteId]);
            $lote = $stmtLote->fetch(PDO::FETCH_ASSOC);

            if (!$lote) {
                $pdo->rollBack();
                bdFlashAdd('warning', 'El lote indicado no esta pendiente o no existe.');
                header('Location: bajas_definitivas.php');
                exit;
            }

            $stmtItems = $pdo->prepare("
                SELECT *
                FROM bajas_definitivas_items
                WHERE lote_id = :lote_id
                ORDER BY id ASC
                FOR UPDATE
            ");
            $stmtItems->execute([':lote_id' => $loteId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                $pdo->rollBack();
                bdFlashAdd('warning', 'No hay elementos en el lote pendiente.');
                header('Location: bajas_definitivas.php');
                exit;
            }

            $updEquipo = $pdo->prepare("
                UPDATE equipos
                SET estado = 'Baja definitiva',
                    fecha_baja = COALESCE(fecha_baja, NOW())
                WHERE id = :id
                  AND estado = 'Baja'
            ");
            $updTelefono = $pdo->prepare("
                UPDATE telefonos
                SET estado = 'Baja definitiva',
                    fecha_baja = COALESCE(fecha_baja, CURDATE())
                WHERE id = :id
                  AND estado = 'Baja'
            ");

            $erroresConfirm = [];
            foreach ($items as $item) {
                $idActivo = (int)$item['activo_id'];
                if ($item['activo_tipo'] === 'equipo') {
                    $updEquipo->execute([':id' => $idActivo]);
                    if ($updEquipo->rowCount() !== 1) {
                        $erroresConfirm[] = "Equipo #$idActivo no estaba en estado Baja.";
                    }
                } elseif ($item['activo_tipo'] === 'telefono') {
                    $updTelefono->execute([':id' => $idActivo]);
                    if ($updTelefono->rowCount() !== 1) {
                        $erroresConfirm[] = "Telefono #$idActivo no estaba en estado Baja.";
                    }
                }
            }

            if ($erroresConfirm) {
                $pdo->rollBack();
                bdFlashAdd('danger', 'No se pudo confirmar el lote: ' . implode(' ', array_slice($erroresConfirm, 0, 4)));
                header('Location: bajas_definitivas.php');
                exit;
            }

            $updItems = $pdo->prepare("
                UPDATE bajas_definitivas_items
                SET confirmado_en = :confirmado_en
                WHERE lote_id = :lote_id
            ");
            $updItems->execute([
                ':confirmado_en' => $fechaConfirmacion,
                ':lote_id'       => $loteId,
            ]);

            $reportePath = 'bajas_definitivas_reporte.php?id=' . $loteId;
            $updLote = $pdo->prepare("
                UPDATE bajas_definitivas_lotes
                SET estado = 'CONFIRMADO',
                    confirmado_en = :confirmado_en,
                    confirmado_por = :confirmado_por,
                    punto_limpio = :punto_limpio,
                    transportado_por = :transportado_por,
                    observaciones = :observaciones,
                    reporte_path = :reporte_path
                WHERE id = :id
            ");
            $updLote->execute([
                ':confirmado_en'   => $fechaConfirmacion,
                ':confirmado_por'  => $usuarioTip !== '' ? $usuarioTip : 'DESCONOCIDO',
                ':punto_limpio'    => $puntoLimpio !== '' ? $puntoLimpio : null,
                ':transportado_por'=> $transportadoPor !== '' ? $transportadoPor : null,
                ':observaciones'   => $observaciones !== '' ? $observaciones : null,
                ':reporte_path'    => $reportePath,
                ':id'              => $loteId,
            ]);

            logActividad(
                $pdo,
                'BAJA_DEFINITIVA_CONFIRMADA',
                'Lote=' . $loteId . '; codigo=' . ($lote['codigo'] ?? '') . '; items=' . count($items),
                ['modulo' => 'BAJAS_DEFINITIVAS', 'nivel' => 'INFO']
            );

            $pdo->commit();
            header('Location: bajas_definitivas_reporte.php?id=' . $loteId);
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        bdFlashAdd('danger', 'Error en el proceso de bajas definitivas: ' . $e->getMessage());
        header('Location: bajas_definitivas.php');
        exit;
    }
}

$flashMessages = bdFlashConsume();
$lotePendiente = bdGetPendingLote($pdo);

$itemsPendientes = [];
if ($lotePendiente) {
    $stmtItemsPend = $pdo->prepare("
        SELECT *
        FROM bajas_definitivas_items
        WHERE lote_id = :lote_id
        ORDER BY activo_tipo ASC, activo_descripcion ASC
    ");
    $stmtItemsPend->execute([':lote_id' => (int)$lotePendiente['id']]);
    $itemsPendientes = $stmtItemsPend->fetchAll(PDO::FETCH_ASSOC);
}

$equiposBajaDisponibles = $pdo->query("
    SELECT e.id, e.etiqueta, e.tipo, e.marca, e.modelo, e.numero_serie, e.hostname, e.fecha_baja
    FROM equipos e
    LEFT JOIN (
        SELECT i.activo_id
        FROM bajas_definitivas_items i
        JOIN bajas_definitivas_lotes l ON l.id = i.lote_id
        WHERE l.estado = 'PENDIENTE'
          AND i.activo_tipo = 'equipo'
        GROUP BY i.activo_id
    ) pend ON pend.activo_id = e.id
    WHERE e.estado = 'Baja'
      AND pend.activo_id IS NULL
    ORDER BY e.fecha_baja DESC, e.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$telefonosBajaDisponibles = $pdo->query("
    SELECT t.id, t.etiqueta, t.marca, t.modelo, t.imei, t.numero_serie, t.fecha_baja
    FROM telefonos t
    LEFT JOIN (
        SELECT i.activo_id
        FROM bajas_definitivas_items i
        JOIN bajas_definitivas_lotes l ON l.id = i.lote_id
        WHERE l.estado = 'PENDIENTE'
          AND i.activo_tipo = 'telefono'
        GROUP BY i.activo_id
    ) pend ON pend.activo_id = t.id
    WHERE t.estado = 'Baja'
      AND pend.activo_id IS NULL
    ORDER BY t.fecha_baja DESC, t.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$lotesConfirmados = $pdo->query("
    SELECT
        l.id,
        l.codigo,
        l.confirmado_en,
        l.confirmado_por,
        l.punto_limpio,
        COUNT(i.id) AS total_items,
        SUM(CASE WHEN i.activo_tipo = 'equipo' THEN 1 ELSE 0 END) AS total_equipos,
        SUM(CASE WHEN i.activo_tipo = 'telefono' THEN 1 ELSE 0 END) AS total_telefonos
    FROM bajas_definitivas_lotes l
    LEFT JOIN bajas_definitivas_items i ON i.lote_id = l.id
    WHERE l.estado = 'CONFIRMADO'
    GROUP BY l.id
    ORDER BY l.confirmado_en DESC, l.id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Bajas definitivas</h2>
    <?php if ($lotePendiente): ?>
        <span class="badge bg-warning text-dark">Lote pendiente: <?= htmlspecialchars((string)$lotePendiente['codigo']) ?></span>
    <?php else: ?>
        <span class="badge bg-success">No hay lote pendiente</span>
    <?php endif; ?>
</div>

<?php foreach ($flashMessages as $msg): ?>
    <div class="alert alert-<?= htmlspecialchars((string)($msg['tipo'] ?? 'info')) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars((string)($msg['mensaje'] ?? '')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Equipos en Baja disponibles</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="accion" value="add_equipos">
                    <label class="form-label mb-1">Buscador rapido</label>
                    <input
                        type="text"
                        class="form-control form-control-sm js-live-filter mb-2"
                        data-target-list="equiposDisponiblesList"
                        data-counter-id="equiposDisponiblesCount"
                        placeholder="Etiqueta, tipo, serie, host..."
                    >
                    <div id="equiposDisponiblesList" class="border rounded p-2 bd-live-list">
                        <?php if (!$equiposBajaDisponibles): ?>
                            <div class="text-muted small mb-0">No hay equipos en estado Baja disponibles.</div>
                        <?php else: ?>
                            <?php foreach ($equiposBajaDisponibles as $e): ?>
                                <?php
                                $texto = trim(
                                    '[' . ((string)($e['etiqueta'] ?? '') !== '' ? (string)$e['etiqueta'] : ('EQ-' . (int)$e['id'])) . '] ' .
                                    trim((string)($e['tipo'] ?? '') . ' ' . (string)($e['marca'] ?? '') . ' ' . (string)($e['modelo'] ?? '')) .
                                    ' | SN: ' . ((string)($e['numero_serie'] ?? '') !== '' ? (string)$e['numero_serie'] : '-') .
                                    ' | Host: ' . ((string)($e['hostname'] ?? '') !== '' ? (string)$e['hostname'] : '-') .
                                    ' | Baja: ' . ((string)($e['fecha_baja'] ?? '') !== '' ? (string)$e['fecha_baja'] : '-')
                                );
                                ?>
                                <label class="form-check bd-live-item py-1 mb-0">
                                    <input class="form-check-input" type="checkbox" name="equipos_ids[]" value="<?= (int)$e['id'] ?>">
                                    <span class="form-check-label bd-live-item-text" data-original="<?= htmlspecialchars($texto, ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($texto) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text" id="equiposDisponiblesCount"></div>
                    <button type="submit" class="btn btn-outline-primary btn-sm mt-2" <?= $equiposBajaDisponibles ? '' : 'disabled' ?>>
                        Anadir equipos al lote pendiente
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Telefonos en Baja disponibles</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="accion" value="add_telefonos">
                    <label class="form-label mb-1">Buscador rapido</label>
                    <input
                        type="text"
                        class="form-control form-control-sm js-live-filter mb-2"
                        data-target-list="telefonosDisponiblesList"
                        data-counter-id="telefonosDisponiblesCount"
                        placeholder="Etiqueta, marca, imei, serie..."
                    >
                    <div id="telefonosDisponiblesList" class="border rounded p-2 bd-live-list">
                        <?php if (!$telefonosBajaDisponibles): ?>
                            <div class="text-muted small mb-0">No hay telefonos en estado Baja disponibles.</div>
                        <?php else: ?>
                            <?php foreach ($telefonosBajaDisponibles as $t): ?>
                                <?php
                                $texto = trim(
                                    '[' . ((string)($t['etiqueta'] ?? '') !== '' ? (string)$t['etiqueta'] : ('TEL-' . (int)$t['id'])) . '] ' .
                                    trim((string)($t['marca'] ?? '') . ' ' . (string)($t['modelo'] ?? '')) .
                                    ' | IMEI: ' . ((string)($t['imei'] ?? '') !== '' ? (string)$t['imei'] : '-') .
                                    ' | SN: ' . ((string)($t['numero_serie'] ?? '') !== '' ? (string)$t['numero_serie'] : '-') .
                                    ' | Baja: ' . ((string)($t['fecha_baja'] ?? '') !== '' ? (string)$t['fecha_baja'] : '-')
                                );
                                ?>
                                <label class="form-check bd-live-item py-1 mb-0">
                                    <input class="form-check-input" type="checkbox" name="telefonos_ids[]" value="<?= (int)$t['id'] ?>">
                                    <span class="form-check-label bd-live-item-text" data-original="<?= htmlspecialchars($texto, ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($texto) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text" id="telefonosDisponiblesCount"></div>
                    <button type="submit" class="btn btn-outline-primary btn-sm mt-2" <?= $telefonosBajaDisponibles ? '' : 'disabled' ?>>
                        Anadir telefonos al lote pendiente
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Lote pendiente</strong>
        <?php if ($lotePendiente): ?>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark"><?= htmlspecialchars((string)$lotePendiente['codigo']) ?></span>
                <a href="bajas_definitivas_pendiente_reporte.php?id=<?= (int)$lotePendiente['id'] ?>" class="btn btn-sm btn-outline-info" target="_blank">
                    Imprimir Lote
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$lotePendiente): ?>
            <p class="text-muted mb-0">No existe lote pendiente. En cuanto anadas elementos en baja se abrira automaticamente uno nuevo.</p>
        <?php elseif (!$itemsPendientes): ?>
            <p class="text-muted mb-0">El lote pendiente esta vacio.</p>
        <?php else: ?>
            <label class="form-label mb-1">Buscar en lote pendiente</label>
            <input
                type="text"
                class="form-control form-control-sm js-table-filter mb-2"
                data-target-table="itemsPendientesTableBody"
                data-counter-id="itemsPendientesCount"
                placeholder="Tipo, etiqueta, descripcion, identificador..."
            >
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Etiqueta</th>
                            <th>Descripcion</th>
                            <th>Identificador</th>
                            <th>Fecha baja</th>
                            <th class="text-end">Accion</th>
                        </tr>
                    </thead>
                    <tbody id="itemsPendientesTableBody">
                        <?php foreach ($itemsPendientes as $it): ?>
                            <?php
                            $tipoTxt = $it['activo_tipo'] === 'equipo' ? 'Equipo' : 'Telefono';
                            $etiquetaTxt = (string)($it['activo_etiqueta'] ?? '-');
                            $descTxt = (string)($it['activo_descripcion'] ?? '-');
                            $idTxt = (string)($it['activo_identificador'] ?? '-');
                            $fechaTxt = (string)($it['fecha_baja_original'] ?? '-');
                            ?>
                            <tr class="bd-table-row">
                                <td><span class="bd-table-text" data-original="<?= htmlspecialchars($tipoTxt, ENT_QUOTES) ?>"><?= htmlspecialchars($tipoTxt) ?></span></td>
                                <td><span class="bd-table-text" data-original="<?= htmlspecialchars($etiquetaTxt, ENT_QUOTES) ?>"><?= htmlspecialchars($etiquetaTxt) ?></span></td>
                                <td><span class="bd-table-text" data-original="<?= htmlspecialchars($descTxt, ENT_QUOTES) ?>"><?= htmlspecialchars($descTxt) ?></span></td>
                                <td><span class="bd-table-text" data-original="<?= htmlspecialchars($idTxt, ENT_QUOTES) ?>"><?= htmlspecialchars($idTxt) ?></span></td>
                                <td><span class="bd-table-text" data-original="<?= htmlspecialchars($fechaTxt, ENT_QUOTES) ?>"><?= htmlspecialchars($fechaTxt) ?></span></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline" onsubmit="return confirm('Quitar este elemento del lote pendiente?');">
                                        <input type="hidden" name="accion" value="remove_item">
                                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text" id="itemsPendientesCount"></div>

            <hr>

            <form method="post" onsubmit="return confirm('Se confirmara el traslado al punto limpio y se pasaran los activos a BAJA DEFINITIVA. Continuar?');">
                <input type="hidden" name="accion" value="confirmar_lote">
                <input type="hidden" name="lote_id" value="<?= (int)$lotePendiente['id'] ?>">

                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Fecha y hora traslado</label>
                        <input type="datetime-local" class="form-control" name="fecha_confirmacion" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Punto limpio / gestor residuos</label>
                        <input type="text" class="form-control" name="punto_limpio" placeholder="Nombre del punto limpio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Transportado por</label>
                        <input type="text" class="form-control" name="transportado_por" placeholder="TIP o nombre">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-danger w-100">Confirmar baja definitiva</button>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="2" placeholder="Datos extra del traslado, albaran, etc."></textarea>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Historial de lotes confirmados</strong></div>
    <div class="card-body p-0">
        <?php if (!$lotesConfirmados): ?>
            <p class="text-muted p-3 mb-0">Todavia no hay lotes confirmados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Fecha confirmacion</th>
                            <th>Confirmado por</th>
                            <th>Punto limpio</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lotesConfirmados as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$l['codigo']) ?></td>
                                <td><?= htmlspecialchars((string)($l['confirmado_en'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($l['confirmado_por'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($l['punto_limpio'] ?? '-')) ?></td>
                                <td class="text-end">
                                    <?= (int)$l['total_items'] ?> (Eq: <?= (int)$l['total_equipos'] ?> / Tel: <?= (int)$l['total_telefonos'] ?>)
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="bajas_definitivas_reporte.php?id=<?= (int)$l['id'] ?>">
                                        Ver reporte
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.bd-live-list {
    max-height: 360px;
    overflow-y: auto;
    background: #fff;
}
.bd-live-item {
    border-bottom: 1px dashed #e5e7eb;
}
.bd-live-item:last-child {
    border-bottom: 0;
}
.bd-live-item mark,
.bd-table-text mark {
    padding: 0 0.1rem;
    background: #fff3a3;
}
</style>

<script>
(function () {
    function normalizeText(value) {
        return (value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function escapeHtml(value) {
        return (value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeRegex(value) {
        return (value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function applyHighlight(targetEl, sourceText, queryRaw) {
        if (!targetEl) {
            return;
        }
        const escapedSource = escapeHtml(sourceText);
        if (!queryRaw) {
            targetEl.innerHTML = escapedSource;
            return;
        }
        const re = new RegExp('(' + escapeRegex(queryRaw) + ')', 'ig');
        targetEl.innerHTML = escapedSource.replace(re, '<mark>$1</mark>');
    }

    function updateCounter(counterId, visibleCount, totalCount) {
        if (!counterId) {
            return;
        }
        const el = document.getElementById(counterId);
        if (!el) {
            return;
        }
        el.textContent = 'Mostrando ' + visibleCount + ' de ' + totalCount + ' elementos.';
    }

    function bindLiveListFilter(input) {
        const targetId = input.dataset.targetList || '';
        const counterId = input.dataset.counterId || '';
        const list = document.getElementById(targetId);
        if (!list) {
            return;
        }

        const items = Array.from(list.querySelectorAll('.bd-live-item'));
        const totalCount = items.length;

        function run() {
            const queryRaw = input.value.trim();
            const queryNorm = normalizeText(queryRaw);
            let visibleCount = 0;

            items.forEach(function (item) {
                const textEl = item.querySelector('.bd-live-item-text');
                const source = textEl ? (textEl.dataset.original || textEl.textContent || '') : '';
                const match = queryNorm === '' || normalizeText(source).includes(queryNorm);
                item.style.display = match ? '' : 'none';
                if (match) {
                    visibleCount++;
                    applyHighlight(textEl, source, queryRaw);
                }
            });

            updateCounter(counterId, visibleCount, totalCount);
        }

        input.addEventListener('input', run);
        run();
    }

    function bindTableFilter(input) {
        const bodyId = input.dataset.targetTable || '';
        const counterId = input.dataset.counterId || '';
        const body = document.getElementById(bodyId);
        if (!body) {
            return;
        }

        const rows = Array.from(body.querySelectorAll('.bd-table-row'));
        const totalCount = rows.length;

        function run() {
            const queryRaw = input.value.trim();
            const queryNorm = normalizeText(queryRaw);
            let visibleCount = 0;

            rows.forEach(function (row) {
                const textEls = Array.from(row.querySelectorAll('.bd-table-text'));
                const sourceParts = textEls.map(function (el) {
                    return el.dataset.original || el.textContent || '';
                });
                const searchable = normalizeText(sourceParts.join(' '));
                const match = queryNorm === '' || searchable.includes(queryNorm);
                row.style.display = match ? '' : 'none';
                if (match) {
                    visibleCount++;
                    textEls.forEach(function (el) {
                        const source = el.dataset.original || el.textContent || '';
                        applyHighlight(el, source, queryRaw);
                    });
                }
            });

            updateCounter(counterId, visibleCount, totalCount);
        }

        input.addEventListener('input', run);
        run();
    }

    document.querySelectorAll('.js-live-filter').forEach(bindLiveListFilter);
    document.querySelectorAll('.js-table-filter').forEach(bindTableFilter);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
