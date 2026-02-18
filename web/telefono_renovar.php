<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

$id_origen = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_origen = (int)($_POST['id_origen'] ?? 0);
}

if ($id_origen <= 0) {
    die('ID de teléfono no válido');
}

// Teléfono que se va a renovar
$stmtOrigen = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
$stmtOrigen->execute([':id' => $id_origen]);
$telefono_origen = $stmtOrigen->fetch(PDO::FETCH_ASSOC);

if (!$telefono_origen) {
    die('Teléfono original no encontrado');
}

// SIM actual del teléfono origen
$sqlSimActual = "
    SELECT ts.id AS rel_id, s.*
    FROM telefono_sim ts
    JOIN sims s ON ts.sim_id = s.id
    WHERE ts.telefono_id = :id
      AND ts.fecha_liberacion IS NULL
    ORDER BY ts.fecha_asignacion DESC
    LIMIT 1
";
$stmtSim = $pdo->prepare($sqlSimActual);
$stmtSim->execute([':id' => $id_origen]);
$sim_origen = $stmtSim->fetch(PDO::FETCH_ASSOC);

// Candidatos a ser el nuevo teléfono (excluye el actual y los dados de baja)
$stmtCandidatos = $pdo->prepare("
    SELECT id, etiqueta, marca, modelo, imei, numero_serie, estado, usuario_asignado
    FROM telefonos
    WHERE id <> :id AND (estado IS NULL OR estado <> 'Baja')
    ORDER BY marca ASC, modelo ASC
");
$stmtCandidatos->execute([':id' => $id_origen]);
$candidatos = $stmtCandidatos->fetchAll(PDO::FETCH_ASSOC);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS renovaciones_telefonos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tel_old_id INT NOT NULL,
        tel_new_id INT NOT NULL,
        sim_movida TINYINT(1) NOT NULL DEFAULT 0,
        estado_old VARCHAR(20),
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        firma_token VARCHAR(64),
        firma_path VARCHAR(255),
        firmado TINYINT(1) DEFAULT 0,
        firmado_fecha DATETIME NULL,
        KEY idx_token (firma_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$nuevo_tel_id = 0;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_tel_id = (int)($_POST['nuevo_tel_id'] ?? 0);

    if ($nuevo_tel_id <= 0) {
        $errores[] = "Debes seleccionar el teléfono que lo sustituirá.";
    } elseif ($nuevo_tel_id === $id_origen) {
        $errores[] = "El teléfono nuevo debe ser diferente al que se renueva.";
    }

    // Datos del teléfono nuevo
    $stmtNuevo = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
    $stmtNuevo->execute([':id' => $nuevo_tel_id]);
    $telefono_nuevo = $stmtNuevo->fetch(PDO::FETCH_ASSOC);
    if (!$telefono_nuevo) {
        $errores[] = "El teléfono nuevo seleccionado no existe.";
    }

    // Validar SIM disponible en el nuevo (debe estar libre)
    $stmtSimNuevo = $pdo->prepare("
        SELECT COUNT(*)
        FROM telefono_sim ts
        WHERE ts.telefono_id = :id
          AND ts.fecha_liberacion IS NULL
    ");
    $stmtSimNuevo->execute([':id' => $nuevo_tel_id]);
    $simActivaNuevo = (int)$stmtSimNuevo->fetchColumn();
    if ($simActivaNuevo > 0) {
        $errores[] = "El teléfono nuevo ya tiene una SIM asignada. Libérala antes de renovar.";
    }

    if (!$sim_origen) {
        $errores[] = "El teléfono original no tiene SIM asignada para trasladar.";
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // 1) Liberar relación SIM del teléfono origen
            $stmtClose = $pdo->prepare("
                UPDATE telefono_sim
                   SET fecha_liberacion = NOW()
                 WHERE telefono_id = :tel
                   AND fecha_liberacion IS NULL
            ");
            $stmtClose->execute([':tel' => $id_origen]);

            // 2) Asignar la misma SIM al teléfono nuevo
            $stmtRel = $pdo->prepare("
                INSERT INTO telefono_sim (telefono_id, sim_id)
                VALUES (:tel, :sim)
            ");
            $stmtRel->execute([
                ':tel' => $nuevo_tel_id,
                ':sim' => $sim_origen['id']
            ]);

            // Aseguramos estado de la SIM
            $stmtUpdSim = $pdo->prepare("UPDATE sims SET estado = 'Asignada' WHERE id = :sim");
            $stmtUpdSim->execute([':sim' => $sim_origen['id']]);

            // 3) Heredar datos principales al teléfono nuevo
            $stmtUpdTel = $pdo->prepare("
                UPDATE telefonos
                   SET usuario_asignado = :usuario,
                       departamento = :depart,
                       ubicacion = :ubic,
                       seccion = :seccion,
                       estado = 'Activo',
                       fecha_baja = NULL
                 WHERE id = :id
            ");
            $stmtUpdTel->execute([
                ':usuario' => $telefono_origen['usuario_asignado'],
                ':depart'  => $telefono_origen['departamento'],
                ':ubic'    => $telefono_origen['ubicacion'],
                ':seccion' => $telefono_origen['seccion'],
                ':id'      => $nuevo_tel_id,
            ]);

            // 4) Marcar teléfono renovado como Baja
            $stmtEstado = $pdo->prepare("
                UPDATE telefonos
                   SET estado = 'Baja', fecha_baja = CURDATE()
                 WHERE id = :id
            ");
            $stmtEstado->execute([':id' => $id_origen]);

            // 5) Guardar registro de renovación (para recibo y firma)
            $firmaToken = bin2hex(random_bytes(32));
            $stmtRen = $pdo->prepare("
                INSERT INTO renovaciones_telefonos
                    (tel_old_id, tel_new_id, sim_movida, estado_old, firma_token)
                VALUES (:old, :new, 1, 'Baja', :token)
            ");
            $stmtRen->execute([
                ':old'   => $id_origen,
                ':new'   => $nuevo_tel_id,
                ':token' => $firmaToken,
            ]);
            $renovacionId = (int)$pdo->lastInsertId();

            $pdo->commit();

            logActividad($pdo, 'RENOVAR_TELEFONO', "Renovación: viejo ID={$id_origen}, nuevo ID={$nuevo_tel_id}");

            header('Location: recibo_renovacion_telefono.php?id=' . $renovacionId);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = "No se pudo completar la renovación: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2>Renovar teléfono <?= htmlspecialchars($telefono_origen['marca'] . ' ' . $telefono_origen['modelo']) ?></h2>
    <p class="text-muted mb-1">El teléfono renovado pasará a <strong>Baja</strong> y el nuevo heredará su SIM, usuario y ubicaciones, quedando en estado <strong>Activo</strong>.</p>
    <?php if (!$sim_origen): ?>
        <div class="alert alert-warning">Este teléfono no tiene SIM asignada actualmente. No es posible trasladar la SIM.</div>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><strong>Teléfono renovado</strong></div>
                <div class="card-body">
                    <div><b>Marca / Modelo:</b> <?= htmlspecialchars($telefono_origen['marca'] . ' ' . $telefono_origen['modelo']) ?></div>
                    <div><b>Etiqueta:</b> <?= htmlspecialchars($telefono_origen['etiqueta'] ?: '-') ?></div>
                    <div><b>IMEI:</b> <?= htmlspecialchars($telefono_origen['imei']) ?></div>
                    <div><b>Nº Serie:</b> <?= htmlspecialchars($telefono_origen['numero_serie'] ?: '-') ?></div>
                    <div><b>Usuario:</b> <span class="dato-contacto-destacado<?= trim((string)($telefono_origen['usuario_asignado'] ?? '')) === '' ? ' dato-contacto-destacado-vacio' : '' ?>"><?= htmlspecialchars($telefono_origen['usuario_asignado'] ?: '-') ?></span></div>
                    <div><b>Departamento / Ubicación / Sección:</b><br>
                        <?= htmlspecialchars($telefono_origen['departamento'] ?: '-') ?> /
                        <?= htmlspecialchars($telefono_origen['ubicacion'] ?: '-') ?> /
                        <?= htmlspecialchars($telefono_origen['seccion'] ?: '-') ?>
                    </div>
                    <?php if ($sim_origen): ?>
                        <div class="mt-2">
                            <b>SIM actual:</b><br>
                            Nº <?= htmlspecialchars($sim_origen['numero']) ?> · <?= htmlspecialchars($sim_origen['operador']) ?><br>
                            ICCID: <?= htmlspecialchars($sim_origen['iccid']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-info h-100">
                <ul class="mb-0">
                    <li>El nuevo teléfono debe estar libre de SIM para poder heredarla.</li>
                    <li>Se mantendrán usuario, departamento, ubicación y sección del teléfono renovado.</li>
                    <li>El teléfono renovado quedará en estado <b>Baja</b>.</li>
                    <li>Se generará un recibo de renovación con opción de firma.</li>
                </ul>
            </div>
        </div>
    </div>

    <form method="post" class="row g-3">
        <input type="hidden" name="id_origen" value="<?= (int)$id_origen ?>">

        <div class="col-md-6">
            <label class="form-label"><strong>Teléfono que lo sustituye</strong></label>
            <input type="text" id="filtro_tel" class="form-control mb-2" placeholder="Buscar por marca, modelo o IMEI">
            <select name="nuevo_tel_id" id="nuevo_tel_id" class="form-select" required>
                <option value="">-- Selecciona el nuevo teléfono --</option>
                <?php foreach ($candidatos as $c): ?>
                    <?php
                        $texto = trim(
                            ($c['etiqueta'] ? '['.$c['etiqueta'].'] ' : '[Sin etiqueta] ') .
                            ($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? '')
                        );
                        $claseOpt = $c['etiqueta'] ? 'etiqueta-ok' : 'etiqueta-missing';
                        $busqueda = strtolower(
                            ($c['etiqueta'] ?? '') . ' ' .
                            ($c['marca'] ?? '') . ' ' .
                            ($c['modelo'] ?? '') . ' ' .
                            ($c['imei'] ?? '') . ' ' .
                            ($c['numero_serie'] ?? '')
                        );
                    ?>
                    <option
                        value="<?= (int)$c['id'] ?>"
                        data-etiqueta="<?= htmlspecialchars($c['etiqueta'] ?? '') ?>"
                        data-marca="<?= htmlspecialchars($c['marca'] ?? '') ?>"
                        data-modelo="<?= htmlspecialchars($c['modelo'] ?? '') ?>"
                        data-imei="<?= htmlspecialchars($c['imei'] ?? '') ?>"
                        data-sn="<?= htmlspecialchars($c['numero_serie'] ?? '') ?>"
                        data-estado="<?= htmlspecialchars($c['estado'] ?? 'N/A') ?>"
                        data-usuario="<?= htmlspecialchars($c['usuario_asignado'] ?? '') ?>"
                        data-search="<?= htmlspecialchars($busqueda) ?>"
                        class="<?= $claseOpt ?>"
                        <?= ($nuevo_tel_id == $c['id']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($texto) ?> (IMEI: <?= htmlspecialchars($c['imei']) ?> - <?= htmlspecialchars($c['estado'] ?? 'N/A') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Escribe para filtrar por modelo o IMEI.</small>

            <div class="card mt-2" id="resumen-nuevo" style="display:none;">
                <div class="card-body p-2">
                    <div><strong>Etiqueta:</strong> <span id="r-etiqueta" class="etiqueta-missing">(sin etiqueta)</span></div>
                    <div><strong>Marca / Modelo:</strong> <span id="r-modelo">-</span></div>
                    <div><strong>IMEI:</strong> <span id="r-imei">-</span></div>
                    <div><strong>N.º serie:</strong> <span id="r-sn">-</span></div>
                    <div><strong>Estado:</strong> <span id="r-estado">-</span></div>
                    <div><strong>Usuario:</strong> <span id="r-usuario" class="dato-contacto-destacado dato-contacto-destacado-vacio">-</span></div>
                </div>
            </div>
        </div>

        <div class="col-12 mt-3">
            <a href="telefonos_ver.php?id=<?= (int)$id_origen ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success" <?= !$sim_origen ? 'disabled' : '' ?>>Confirmar renovación</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filtro = document.getElementById('filtro_tel');
    const select = document.getElementById('nuevo_tel_id');
    const resumen = document.getElementById('resumen-nuevo');
    const campos = {
        etiqueta: document.getElementById('r-etiqueta'),
        modelo: document.getElementById('r-modelo'),
        imei: document.getElementById('r-imei'),
        sn: document.getElementById('r-sn'),
        estado: document.getElementById('r-estado'),
        usuario: document.getElementById('r-usuario'),
    };

    function mostrarResumen() {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) {
            resumen.style.display = 'none';
            return;
        }
        campos.etiqueta.textContent = opt.dataset.etiqueta || '(sin etiqueta)';
        campos.etiqueta.classList.toggle('etiqueta-ok', !!opt.dataset.etiqueta);
        campos.etiqueta.classList.toggle('etiqueta-missing', !opt.dataset.etiqueta);
        campos.modelo.textContent = (opt.dataset.marca || '') + ' ' + (opt.dataset.modelo || '');
        campos.imei.textContent   = opt.dataset.imei || '-';
        campos.sn.textContent     = opt.dataset.sn || '-';
        campos.estado.textContent = opt.dataset.estado || '-';
        campos.usuario.textContent = opt.dataset.usuario || '-';
        campos.usuario.classList.toggle('dato-contacto-destacado-vacio', !opt.dataset.usuario);
        resumen.style.display = 'block';
    }

    function filtrarOpciones() {
        const term = (filtro.value || '').toLowerCase().trim();
        Array.from(select.options).forEach(opt => {
            if (!opt.value) return;
            const texto = opt.dataset.search || opt.textContent.toLowerCase();
            const visible = term === '' || texto.includes(term);
            opt.hidden = !visible;
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
