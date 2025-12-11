<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';


$id_origen = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_origen = (int)($_POST['id_origen'] ?? 0);
}

if ($id_origen <= 0) {
    die('ID de equipo no válido');
}

// Equipo que se va a renovar
$stmtOrigen = $pdo->prepare("SELECT * FROM equipos WHERE id = :id");
$stmtOrigen->execute([':id' => $id_origen]);
$equipo_origen = $stmtOrigen->fetch(PDO::FETCH_ASSOC);

if (!$equipo_origen) {
    die('Equipo original no encontrado');
}

// IP principal del equipo origen (si existe)
$stmtIpOrigen = $pdo->prepare("SELECT * FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1 LIMIT 1");
$stmtIpOrigen->execute([':id' => $id_origen]);
$ip_origen = $stmtIpOrigen->fetch(PDO::FETCH_ASSOC);

// Monitores asociados al equipo origen (solo aplica a PC/PORTÁTIL)
$monitores_origen = [];
if (in_array($equipo_origen['tipo'], ['PC','PORTÁTIL'])) {
    $stmtMon = $pdo->prepare("
        SELECT e.*
        FROM pc_monitores pm
        JOIN equipos e ON e.id = pm.id_monitor
        WHERE pm.id_pc = :id_pc
        ORDER BY e.marca, e.modelo, e.numero_serie
    ");
    $stmtMon->execute([':id_pc' => $id_origen]);
    $monitores_origen = $stmtMon->fetchAll(PDO::FETCH_ASSOC);
}

// Candidatos a ser el nuevo equipo (excluye el actual y los dados de baja)
$stmtCandidatos = $pdo->prepare("
    SELECT id, etiqueta, numero_serie, marca, modelo, estado, tipo
    FROM equipos
    WHERE id <> :id AND (estado IS NULL OR estado <> 'Baja')
    ORDER BY etiqueta ASC, id ASC
");
$stmtCandidatos->execute([':id' => $id_origen]);
$candidatos = $stmtCandidatos->fetchAll(PDO::FETCH_ASSOC);

// Monitores libres (sin asignación)
$stmtMonLibres = $pdo->query("
    SELECT e.*
    FROM equipos e
    LEFT JOIN pc_monitores pm ON pm.id_monitor = e.id
    WHERE e.tipo = 'MONITOR'
      AND pm.id_monitor IS NULL
      AND (e.estado IS NULL OR e.estado <> 'Baja')
    ORDER BY e.marca, e.modelo, e.numero_serie
");
$monitores_libres = $stmtMonLibres->fetchAll(PDO::FETCH_ASSOC);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS renovaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipo_old_id INT NOT NULL,
        equipo_new_id INT NOT NULL,
        ip_move TINYINT(1) NOT NULL DEFAULT 0,
        mon_accion VARCHAR(20),
        mon_nuevo_id INT DEFAULT NULL,
        estado_old VARCHAR(20),
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        firma_token VARCHAR(64),
        firma_path VARCHAR(255),
        firmado TINYINT(1) DEFAULT 0,
        firmado_fecha DATETIME NULL,
        KEY idx_token (firma_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$nuevo_equipo_id  = 0;
$mantener_ip      = false;
$monitores_accion = 'trasladar';
$monitor_libre_id = 0;
$estado_viejo     = 'Almacén';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_equipo_id   = (int)($_POST['nuevo_equipo_id'] ?? 0);
    $mantener_ip       = ($_POST['mantener_ip'] ?? '') === 'si';
    $monitores_accion  = $_POST['monitores_accion'] ?? 'trasladar';
    $monitor_libre_id  = (int)($_POST['monitor_libre_id'] ?? 0);
    $estado_viejo      = $_POST['estado_viejo'] ?? 'Almacén';

    // Validaciones básicas
    if ($nuevo_equipo_id <= 0) {
        $errores[] = "Debes seleccionar el equipo que lo sustituirá.";
    } elseif ($nuevo_equipo_id === $id_origen) {
        $errores[] = "El equipo nuevo debe ser diferente al equipo que se renueva.";
    }
    if (!in_array($estado_viejo, ['Almacén','Baja'], true)) {
        $errores[] = "El estado del equipo renovado solo puede ser Almacén o Baja.";
    }
    if (!in_array($monitores_accion, ['trasladar','baja','almacen'], true)) {
        $errores[] = "Acción de monitores no válida.";
    }

    // Datos del equipo nuevo
    $stmtNuevo = $pdo->prepare("SELECT * FROM equipos WHERE id = :id");
    $stmtNuevo->execute([':id' => $nuevo_equipo_id]);
    $equipo_nuevo = $stmtNuevo->fetch(PDO::FETCH_ASSOC);
    if (!$equipo_nuevo) {
        $errores[] = "El equipo nuevo seleccionado no existe.";
    }

    // Si se quiere mantener la IP, verificar que el equipo nuevo no tenga ya principal
    if ($mantener_ip) {
        $stmtIpNuevo = $pdo->prepare("SELECT COUNT(*) FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1");
        $stmtIpNuevo->execute([':id' => $nuevo_equipo_id]);
        $yaTieneIp = (int)$stmtIpNuevo->fetchColumn();
        if ($yaTieneIp > 0) {
            $errores[] = "El equipo nuevo ya tiene una IP principal. Elige 'sin IP' o libera su IP antes de renovar.";
        }
        if (!$ip_origen) {
            $errores[] = "El equipo original no tiene IP principal para trasladar.";
        }
    }

    // Validar monitor libre seleccionado
    if ($monitor_libre_id > 0) {
        $stmtCheckMon = $pdo->prepare("
            SELECT COUNT(*) FROM pc_monitores WHERE id_monitor = :id
        ");
        $stmtCheckMon->execute([':id' => $monitor_libre_id]);
        if ((int)$stmtCheckMon->fetchColumn() > 0) {
            $errores[] = "El monitor seleccionado ya está asignado.";
        }
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // 1) Trasladar IP principal si procede
            if ($mantener_ip && $ip_origen) {
                $stmtMoveIp = $pdo->prepare("
                    UPDATE ips_equipos
                    SET equipo_id = :nuevo
                    WHERE equipo_id = :origen AND es_principal = 1
                ");
                $stmtMoveIp->execute([
                    ':nuevo'  => $nuevo_equipo_id,
                    ':origen' => $id_origen,
                ]);
            }

            // 2) Gestionar monitores del equipo origen (solo si era PC/PORTÁTIL)
            if (!empty($monitores_origen) && in_array($equipo_origen['tipo'], ['PC','PORTÁTIL'], true)) {
                $idsMon = array_column($monitores_origen, 'id');
                $placeholders = implode(',', array_fill(0, count($idsMon), '?'));

                if ($monitores_accion === 'trasladar') {
                    $stmtUpd = $pdo->prepare("UPDATE pc_monitores SET id_pc = :nuevo WHERE id_pc = :origen");
                    $stmtUpd->execute([':nuevo' => $nuevo_equipo_id, ':origen' => $id_origen]);
                } elseif ($monitores_accion === 'baja') {
                    $stmtDel = $pdo->prepare("DELETE FROM pc_monitores WHERE id_pc = :origen");
                    $stmtDel->execute([':origen' => $id_origen]);
                    $stmtBaja = $pdo->prepare("UPDATE equipos SET estado = 'Baja' WHERE id IN ($placeholders)");
                    $stmtBaja->execute($idsMon);
                } elseif ($monitores_accion === 'almacen') {
                    $stmtDel = $pdo->prepare("DELETE FROM pc_monitores WHERE id_pc = :origen");
                    $stmtDel->execute([':origen' => $id_origen]);
                    $stmtAlm = $pdo->prepare("UPDATE equipos SET estado = 'Almacén' WHERE id IN ($placeholders)");
                    $stmtAlm->execute($idsMon);
                }
            }

            // 3) Asignar monitor libre opcional al equipo nuevo
            if ($monitor_libre_id > 0) {
                $stmtInsMon = $pdo->prepare("INSERT INTO pc_monitores (id_pc, id_monitor) VALUES (:pc, :mon)");
                $stmtInsMon->execute([
                    ':pc'  => $nuevo_equipo_id,
                    ':mon' => $monitor_libre_id,
                ]);
            }

            // 4) Mantener ubicación/departamento/sección en el nuevo equipo (hereda del origen)
            $stmtUbic = $pdo->prepare("
                UPDATE equipos
                SET ubicacion = :ubicacion,
                    departamento = :departamento,
                    seccion_id = :seccion_id
                WHERE id = :id
            ");
            $stmtUbic->execute([
                ':ubicacion'   => $equipo_origen['ubicacion'],
                ':departamento'=> $equipo_origen['departamento'],
                ':seccion_id'  => $equipo_origen['seccion_id'],
                ':id'          => $nuevo_equipo_id,
            ]);

            // 5) Marcar estado final del equipo renovado
            $stmtEstadoViejo = $pdo->prepare("UPDATE equipos SET estado = :estado WHERE id = :id");
            $stmtEstadoViejo->execute([
                ':estado' => $estado_viejo,
                ':id'     => $id_origen,
            ]);

            // 6) Guardar registro de renovación para recibo y firma
            $firmaToken = bin2hex(random_bytes(32));
            $stmtRen = $pdo->prepare("
                INSERT INTO renovaciones
                (equipo_old_id, equipo_new_id, ip_move, mon_accion, mon_nuevo_id, estado_old, firma_token)
                VALUES (:old, :new, :ip, :mon, :mon_new, :estado_old, :token)
            ");
            $stmtRen->execute([
                ':old'        => $id_origen,
                ':new'        => $nuevo_equipo_id,
                ':ip'         => $mantener_ip ? 1 : 0,
                ':mon'        => $monitores_accion,
                ':mon_new'    => $monitor_libre_id ?: null,
                ':estado_old' => $estado_viejo,
                ':token'      => $firmaToken,
            ]);
            $renovacionId = (int)$pdo->lastInsertId();

            $pdo->commit();

            logActividad($pdo, 'RENOVAR_EQUIPO', "Renovación: viejo ID={$id_origen}, nuevo ID={$nuevo_equipo_id}");

            header('Location: recibo_renovacion.php?id=' . $renovacionId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "No se pudo completar la renovación: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2>Renovar equipo <?= htmlspecialchars($equipo_origen['etiqueta'] ?? $equipo_origen['id']) ?></h2>
    <p class="text-muted">El equipo renovado mantendrá ubicación, departamento y sección del original.</p>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="id_origen" value="<?= (int)$id_origen ?>">

        <div class="col-md-6">
            <label class="form-label"><strong>Equipo que lo sustituye</strong></label>
            <input type="text" id="filtro_equipo" class="form-control mb-2" placeholder="Buscar por etiqueta, número de serie o modelo">
            <select name="nuevo_equipo_id" id="nuevo_equipo_id" class="form-select" required>
                <option value="">-- Selecciona etiqueta o Nº de serie --</option>
                <?php foreach ($candidatos as $c): ?>
                    <?php
                        $texto = trim(
                            ($c['etiqueta'] ? '['.$c['etiqueta'].'] ' : '') .
                            ($c['marca'] ?? '') . ' ' .
                            ($c['modelo'] ?? '') .
                            ($c['numero_serie'] ? ' SN:'.$c['numero_serie'] : '')
                        );
                        $busqueda = strtolower(
                            ($c['etiqueta'] ?? '') . ' ' .
                            ($c['numero_serie'] ?? '') . ' ' .
                            ($c['marca'] ?? '') . ' ' .
                            ($c['modelo'] ?? '')
                        );
                    ?>
                    <option
                        value="<?= (int)$c['id'] ?>"
                        data-etiqueta="<?= htmlspecialchars($c['etiqueta'] ?? '') ?>"
                        data-sn="<?= htmlspecialchars($c['numero_serie'] ?? '') ?>"
                        data-marca="<?= htmlspecialchars($c['marca'] ?? '') ?>"
                        data-modelo="<?= htmlspecialchars($c['modelo'] ?? '') ?>"
                        data-tipo="<?= htmlspecialchars($c['tipo'] ?? '') ?>"
                        data-estado="<?= htmlspecialchars($c['estado'] ?? 'N/A') ?>"
                        data-search="<?= htmlspecialchars($busqueda) ?>"
                        <?= (isset($nuevo_equipo_id) && $nuevo_equipo_id == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($texto) ?> (<?= htmlspecialchars($c['tipo']) ?> - <?= htmlspecialchars($c['estado'] ?? 'N/A') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Busca por etiqueta o número de serie y revisa el resumen.</small>

            <div class="card mt-2" id="resumen-nuevo" style="display:none;">
                <div class="card-body p-2">
                    <div><strong>Etiqueta:</strong> <span id="r-etiqueta">-</span></div>
                    <div><strong>Tipo / Estado:</strong> <span id="r-tipo">-</span> · <span id="r-estado">-</span></div>
                    <div><strong>Marca / Modelo:</strong> <span id="r-marca">-</span> <span id="r-modelo">-</span></div>
                    <div><strong>Serie:</strong> <span id="r-sn">-</span></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label"><strong>IP principal</strong></label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mantener_ip" id="ip_si" value="si" <?= $mantener_ip ? 'checked' : '' ?> <?= !empty($ip_origen) ? '' : 'disabled' ?>>
                <label class="form-check-label" for="ip_si">Trasladar la IP del equipo renovado al nuevo</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mantener_ip" id="ip_no" value="no" <?= $mantener_ip ? '' : 'checked' ?>>
                <label class="form-check-label" for="ip_no">El nuevo se queda sin IP (no se mueve la IP actual)</label>
            </div>
            <?php if (!$ip_origen): ?>
                <small class="text-muted">El equipo original no tiene IP principal.</small>
            <?php endif; ?>
        </div>

        <?php if (in_array($equipo_origen['tipo'], ['PC','PORTÁTIL'], true)): ?>
        <div id="bloque-monitores-opciones" style="display:none;">
            <div class="col-md-6">
                <label class="form-label"><strong>Monitores actuales (<?= count($monitores_origen) ?>)</strong></label>
                <?php if ($monitores_origen): ?>
                    <ul class="mb-2">
                        <?php foreach ($monitores_origen as $m): ?>
                            <li><?= htmlspecialchars(($m['marca'] ?? '').' '.($m['modelo'] ?? '').($m['numero_serie'] ? ' SN:'.$m['numero_serie'] : '')) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No hay monitores asociados.</p>
                <?php endif; ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="monitores_accion" id="mon_trasladar" value="trasladar" <?= $monitores_accion === 'trasladar' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mon_trasladar">Pasar monitores al nuevo equipo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="monitores_accion" id="mon_almacen" value="almacen" <?= $monitores_accion === 'almacen' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mon_almacen">Mandar monitores a Almacén</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="monitores_accion" id="mon_baja" value="baja" <?= $monitores_accion === 'baja' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mon_baja">Dar de baja los monitores</label>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Vincular monitor libre al nuevo</strong> (opcional)</label>
                <select name="monitor_libre_id" class="form-select">
                    <option value="0">-- Sin monitor adicional --</option>
                    <?php foreach ($monitores_libres as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ($monitor_libre_id ?? 0) == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($m['marca'] ?? '').' '.($m['modelo'] ?? '').($m['numero_serie'] ? ' SN:'.$m['numero_serie'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Solo se muestran monitores libres (sin asignación).</small>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-4">
            <label class="form-label"><strong>Estado del equipo renovado</strong></label>
            <select name="estado_viejo" class="form-select">
                <option value="Almacén" <?= ($estado_viejo ?? '') === 'Almacén' ? 'selected' : '' ?>>Almacén</option>
                <option value="Baja" <?= ($estado_viejo ?? '') === 'Baja' ? 'selected' : '' ?>>Baja</option>
            </select>
            <small class="text-muted">El nuevo heredará ubicación/departamento/sección del original.</small>
        </div>

        <div class="col-12 mt-3">
            <a href="equipo_ver.php?id=<?= (int)$id_origen ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">Confirmar renovación</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filtro = document.getElementById('filtro_equipo');
    const select = document.getElementById('nuevo_equipo_id');
    const resumen = document.getElementById('resumen-nuevo');
    const bloqueMonitores = document.getElementById('bloque-monitores-opciones');
    const campos = {
        etiqueta: document.getElementById('r-etiqueta'),
        tipo: document.getElementById('r-tipo'),
        estado: document.getElementById('r-estado'),
        marca: document.getElementById('r-marca'),
        modelo: document.getElementById('r-modelo'),
        sn: document.getElementById('r-sn'),
    };

    function mostrarResumen() {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) {
            resumen.style.display = 'none';
            return;
        }
        campos.etiqueta.textContent = opt.dataset.etiqueta || '(sin etiqueta)';
        campos.tipo.textContent     = opt.dataset.tipo || '-';
        campos.estado.textContent   = opt.dataset.estado || '-';
        campos.marca.textContent    = opt.dataset.marca || '';
        campos.modelo.textContent   = opt.dataset.modelo || '';
        campos.sn.textContent       = opt.dataset.sn || '-';
        resumen.style.display = 'block';
        if (bloqueMonitores) {
            bloqueMonitores.style.display = 'flex';
            bloqueMonitores.classList.add('row', 'g-3');
        }
    }

    function filtrarOpciones() {
        const term = (filtro.value || '').toLowerCase().trim();
        let primeraVisible = null;
        Array.from(select.options).forEach(opt => {
            if (!opt.value) return;
            const texto = opt.dataset.search || opt.textContent.toLowerCase();
            const visible = term === '' || texto.includes(term);
            opt.hidden = !visible;
            if (visible && !primeraVisible) {
                primeraVisible = opt;
            }
        });
    }

    if (filtro && select) {
        filtro.addEventListener('input', filtrarOpciones);
        select.addEventListener('change', mostrarResumen);
        filtrarOpciones();
        mostrarResumen();
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
