<?php
// equipo_editar.php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}
$id_equipo = $id;
// Cargar equipo
$stmtEq = $pdo->prepare("SELECT * FROM equipos WHERE id = :id");
$stmtEq->execute([':id' => $id]);
$equipo = $stmtEq->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    echo '<div class="alert alert-danger">Equipo no encontrado.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
// Cargar posible avería abierta del equipo
$stmtAv = $pdo->prepare("SELECT * FROM averias WHERE equipo_id = :id AND estado = 'ABIERTA' ORDER BY fecha_creacion DESC LIMIT 1");
$stmtAv->execute([':id' => $id]);
$averia = $stmtAv->fetch(PDO::FETCH_ASSOC);

// Cargar IP principal (si existe)
$stmtIp = $pdo->prepare("SELECT * FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1 LIMIT 1");
$stmtIp->execute([':id' => $id]);
$ipRow = $stmtIp->fetch(PDO::FETCH_ASSOC);

// Cargar redes
$redesStmt = $pdo->query("SELECT id, nombre, direccion_red FROM redes ORDER BY id ASC");
$redes = $redesStmt->fetchAll(PDO::FETCH_ASSOC);

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Mantener la imagen actual por defecto
    $imagenRuta = $equipo['imagen'] ?? null;

    // Si se sube una nueva imagen, sustituirla
if (!empty($_FILES['imagen']['name'])) {
    $uploadDir = __DIR__ . '/uploads/equipos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $nombreOriginal = basename($_FILES['imagen']['name']);
    $nombreLimpio   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nombreOriginal);
    $nombreFinal    = time() . '_' . $nombreLimpio;

    $rutaRelativa = 'uploads/equipos/' . $nombreFinal;
    $rutaFisica   = $uploadDir . $nombreFinal;

    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaFisica)) {

        // Borrar la imagen anterior si existe
        if (!empty($imagenRuta) && file_exists(__DIR__ . '/' . $imagenRuta)) {
            @unlink(__DIR__ . '/' . $imagenRuta);
        }

        $imagenRuta = $rutaRelativa;
    } else {
        $errores[] = "No se pudo guardar la nueva imagen del equipo.";
    }
}

    // Campos principales
    $tipo             = strtoupper(trim($_POST['tipo'] ?? ''));
    $marca            = strtoupper(trim($_POST['marca'] ?? ''));
    $modelo           = strtoupper(trim($_POST['modelo'] ?? ''));
    $numero_serie     = strtoupper(trim($_POST['numero_serie'] ?? ''));
    $hostname         = strtoupper(trim($_POST['hostname'] ?? ''));
    $usuario_asignado = strtoupper(trim($_POST['usuario_asignado'] ?? ''));
    $departamento     = strtoupper(trim($_POST['departamento'] ?? ''));
    $ubicacion        = strtoupper(trim($_POST['ubicacion'] ?? ''));
    $fecha_compra     = $_POST['fecha_compra'] ?? null;
    $proveedor        = strtoupper(trim($_POST['proveedor'] ?? ''));
    $coste            = $_POST['coste'] ?? null;
    $estado           = trim($_POST['estado'] ?? 'En uso');
    $notas            = strtoupper(trim($_POST['notas'] ?? ''));

    // Red / IP
    $ip     = strtoupper(trim($_POST['ip'] ?? ''));
    $mac    = strtoupper(trim($_POST['mac'] ?? ''));
    $red_id = $_POST['red_id'] ?? '';

    // Avería
    $tipo_averia  = strtoupper(trim($_POST['tipo_averia'] ?? ''));
    $num_asunto   = strtoupper(trim($_POST['num_asunto'] ?? ''));
    $desc_averia  = trim($_POST['desc_averia'] ?? '');
    $empresa_ext  = strtoupper(trim($_POST['empresa_ext'] ?? ''));

    $estadoEsAveriado = (strcasecmp($estado, 'Averiado') === 0);

    if ($tipo === '') {
        $errores[] = "El campo Tipo es obligatorio.";
    }

    if ($estadoEsAveriado) {
        if ($tipo_averia === '') {
            $errores[] = "El tipo de avería es obligatorio cuando el equipo está en estado AVERIADO.";
        }
        if ($num_asunto === '') {
            $errores[] = "El número de asunto es obligatorio cuando el equipo está en estado AVERIADO.";
        }
    }

   // Si el estado pasa a BAJA por primera vez → guardar fecha_baja
if ($estado === 'Baja') {

    $sql_check = "SELECT fecha_baja FROM equipos WHERE id = :id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id' => $id_equipo]);
    $check = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // Solo insertar fecha_baja si está vacía (evita sobrescritura)
    if (empty($check['fecha_baja'])) {
        $sql_baja = "UPDATE equipos SET fecha_baja = NOW() WHERE id = :id";
        $stmt_baja = $pdo->prepare($sql_baja);
        $stmt_baja->execute([':id' => $id_equipo]);
    }
}

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // Actualizar equipo
            $sqlEquipo = "UPDATE equipos SET
                tipo = :tipo,
                marca = :marca,
                modelo = :modelo,
                numero_serie = :numero_serie,
                hostname = :hostname,
                usuario_asignado = :usuario_asignado,
                departamento = :departamento,
                ubicacion = :ubicacion,
                fecha_compra = :fecha_compra,
                proveedor = :proveedor,
                coste = :coste,
                estado = :estado,
                notas = :notas,
                imagen = :imagen
              WHERE id = :id";

            $stmtUp = $pdo->prepare($sqlEquipo);
            $stmtUp->execute([
                ':tipo'             => $tipo,
                ':marca'            => $marca,
                ':modelo'           => $modelo,
                ':numero_serie'     => $numero_serie,
                ':hostname'         => $hostname,
                ':usuario_asignado' => $usuario_asignado,
                ':departamento'     => $departamento,
                ':ubicacion'        => $ubicacion,
                ':fecha_compra'     => $fecha_compra ?: null,
                ':proveedor'        => $proveedor,
                ':coste'            => $coste !== '' ? $coste : null,
                ':estado'           => $estado,
                ':notas'            => $notas,
                ':imagen'           => $imagenRuta,   
                ':id'               => $id,
            ]);

            // Gestionar IP principal
            if ($ip === '' || $red_id === '') {
                if ($ipRow) {
                    $delIp = $pdo->prepare("DELETE FROM ips_equipos WHERE id = :id");
                    $delIp->execute([':id' => $ipRow['id']]);
                }
            } else {
                if ($ipRow) {
                    $updIp = $pdo->prepare("UPDATE ips_equipos
                        SET ip = :ip, mac = :mac, red_id = :red_id
                        WHERE id = :id");
                    $updIp->execute([
                        ':ip'     => $ip,
                        ':mac'    => $mac,
                        ':red_id' => $red_id,
                        ':id'     => $ipRow['id'],
                    ]);
                } else {
                    $insIp = $pdo->prepare("INSERT INTO ips_equipos
                        (equipo_id, red_id, ip, mac, es_principal, notas)
                        VALUES (:equipo_id, :red_id, :ip, :mac, 1, NULL)");
                    $insIp->execute([
                        ':equipo_id' => $id,
                        ':red_id'    => $red_id,
                        ':ip'        => $ip,
                        ':mac'       => $mac,
                    ]);
                }
            }

            // Gestionar AVERÍA
            $averiaId = null;

            if ($estadoEsAveriado) {
                if ($averia) {
                    // Actualizar avería existente
                    $stmtAvUp = $pdo->prepare("
                        UPDATE averias
                        SET tipo_averia = :tipo_averia,
                            num_asunto  = :num_asunto,
                            descripcion = :descripcion,
                            empresa_ext = :empresa_ext
                        WHERE id = :id
                    ");
                    $stmtAvUp->execute([
                        ':tipo_averia' => $tipo_averia,
                        ':num_asunto'  => $num_asunto,
                        ':descripcion' => $desc_averia,
                        ':empresa_ext' => $empresa_ext,
                        ':id'          => $averia['id'],
                    ]);
                    $averiaId = (int)$averia['id'];
                } else {
                    // Crear nueva avería
                    $stmtAvIns = $pdo->prepare("
                        INSERT INTO averias (equipo_id, tipo_averia, num_asunto, descripcion, empresa_ext)
                        VALUES (:equipo_id, :tipo_averia, :num_asunto, :descripcion, :empresa_ext)
                    ");
                    $stmtAvIns->execute([
                        ':equipo_id'   => $id,
                        ':tipo_averia' => $tipo_averia,
                        ':num_asunto'  => $num_asunto,
                        ':descripcion' => $desc_averia,
                        ':empresa_ext' => $empresa_ext,
                    ]);
                    $averiaId = (int)$pdo->lastInsertId();
                }
            } else {
                // Opcional: cerrar avería si ya no está averiado
                /*
                if ($averia) {
                    $stmtCerrar = $pdo->prepare("UPDATE averias SET estado = 'CERRADA' WHERE id = :id");
                    $stmtCerrar->execute([':id' => $averia['id']]);
                }
                */
            }

            $pdo->commit();

            // 🔴 Redirección según haya avería o no
            if ($averiaId) {
                header('Location: averia_parte.php?id=' . $averiaId);
            } else {
                header('Location: index.php?msg=ok');
            }
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al actualizar el equipo: " . $e->getMessage();
        }
    }

    // Si hay errores, mantenemos lo que se ha puesto en el formulario
    $equipo = array_merge($equipo, [
        'tipo'             => $tipo,
        'marca'            => $marca,
        'modelo'           => $modelo,
        'numero_serie'     => $numero_serie,
        'hostname'         => $hostname,
        'usuario_asignado' => $usuario_asignado,
        'departamento'     => $departamento,
        'ubicacion'        => $ubicacion,
        'fecha_compra'     => $fecha_compra,
        'proveedor'        => $proveedor,
        'coste'            => $coste,
        'estado'           => $estado,
        'notas'            => $notas,
    ]);

    $ipRow = [
        'ip'     => $ip,
        'mac'    => $mac,
        'red_id' => $red_id,
    ];

    $averia = [
        'tipo_averia' => $tipo_averia,
        'num_asunto'  => $num_asunto,
        'descripcion' => $desc_averia,
        'empresa_ext' => $empresa_ext,
    ];
}


// Valores actuales para el formulario
$ip_val  = $ipRow['ip']  ?? '';
$mac_val = $ipRow['mac'] ?? '';
$red_sel = $ipRow['red_id'] ?? '';

require_once __DIR__ . '/includes/header.php'; 
?>


<h1 class="h3 mb-3">Editar equipo #<?= htmlspecialchars($equipo['id']) ?></h1>

<?php if ($errores): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="row g-3" enctype="multipart/form-data">
    <div class="col-md-4">
        <label class="form-label">Tipo *</label>
        <select name="tipo" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <?php
            $tipos = ['PC', 'Portátil', 'Monitor', 'Impresora', 'Switch', 'Router', 'Móvil', 'Tablet', 'Otro'];
            foreach ($tipos as $t):
            ?>
                <option value="<?= $t ?>" <?= ($equipo['tipo'] === $t) ? 'selected' : '' ?>>
                    <?= $t ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Marca</label>
        <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($equipo['marca'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Modelo</label>
        <input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars($equipo['modelo'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Número de serie</label>
        <input type="text" name="numero_serie" class="form-control" value="<?= htmlspecialchars($equipo['numero_serie'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Servicio</label>
        <select name="hostname" class="form-select">
            <?php
            $hostnames = ['Intranet', 'Internet', 'VPN', 'Ninguno', 'Otro'];
            foreach ($hostnames as $hostname):
            ?>
                <option value="<?= $hostname ?>" <?= (($equipo['hostname'] ?? '') === $hostname) ? 'selected' : '' ?>>
                    <?= $hostname ?>
                </option>
            <?php endforeach; ?>
        </select>
       
    </div>
    <div class="col-md-4">
        <label class="form-label">Usuario asignado</label>
        <input type="text" name="usuario_asignado" class="form-control" value="<?= htmlspecialchars($equipo['usuario_asignado'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Departamento</label>
        <input type="text" name="departamento" class="form-control" value="<?= htmlspecialchars($equipo['departamento'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Ubicación</label>
        <input type="text" name="ubicacion" class="form-control" value="<?= htmlspecialchars($equipo['ubicacion'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha Alta</label>
        <input type="date" name="fecha_compra" class="form-control" value="<?= htmlspecialchars($equipo['fecha_compra'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Proveedor</label>
        <input type="text" name="proveedor" class="form-control" value="<?= htmlspecialchars($equipo['proveedor'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Coste (€)</label>
        <input type="number" step="0.01" name="coste" class="form-control" value="<?= htmlspecialchars($equipo['coste'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
            <?php
            $estados = ['Activo', 'Almacén', 'Averiado', 'Baja', 'Prestado'];
            foreach ($estados as $est):
            ?>
                <option value="<?= $est ?>" <?= (($equipo['estado'] ?? '') === $est) ? 'selected' : '' ?>>
                    <?= $est ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="imagen" class="form-label">Imagen del equipo</label>
            <?php if (!empty($equipo['imagen'])): ?>
                <div class="mb-3">
                    <label class="form-label">Imagen actual:</label><br>
                    <img src="<?= htmlspecialchars($equipo['imagen']) ?>" 
                        alt="Imagen del equipo"
                        style="max-width:180px; height:auto; border:1px solid #ccc; padding:4px;">
                </div>
            <?php endif; ?>

        <input type="file" class="form-control" name="imagen" accept="image/*">
    </div>


    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="3"><?= htmlspecialchars($equipo['notas'] ?? '') ?></textarea>
    </div>

    <hr class="mt-4">

    <!-- Sección de averías -->
     <?php
$tipo_averia_val = $_POST['tipo_averia'] ?? ($averia['tipo_averia'] ?? '');
$num_asunto_val  = $_POST['num_asunto']  ?? ($averia['num_asunto']  ?? '');
$desc_averia_val = $_POST['desc_averia'] ?? ($averia['descripcion'] ?? '');
$empresa_ext_val = $_POST['empresa_ext'] ?? ($averia['empresa_ext'] ?? '');
$mostrarAveria   = (strcasecmp($equipo['estado'] ?? '', 'Averiado') === 0);
?>

<hr class="mt-4">
<h2 class="h5">Datos de avería</h2>
<p class="text-muted">
    Rellenar estos campos si el estado del equipo pasa a  <strong>Averiado</strong>.
</p>

<div id="bloqueAveria" style="<?= $mostrarAveria ? '' : 'display:none;' ?>">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Tipo de avería *</label>
            <input type="text" name="tipo_averia" class="form-control"
                   value="<?= htmlspecialchars($tipo_averia_val) ?>"
                   placeholder="Ej: No enciende, Pantalla rota, Disco defectuoso...">
        </div>
        <div class="col-md-4">
            <label class="form-label">Nº de asunto (empresa externa) *</label>
            <input type="text" name="num_asunto" class="form-control"
                   value="<?= htmlspecialchars($num_asunto_val) ?>"
                   placeholder="Referencia del ticket de la empresa externa">
        </div>
        <div class="col-md-4">
            <label class="form-label">Empresa externa</label>
            <input type="text" name="empresa_ext" class="form-control"
                   value="<?= htmlspecialchars($empresa_ext_val) ?>"
                   placeholder="Nombre de la empresa de soporte">
        </div>
        <div class="col-12">
            <label class="form-label">Descripción de la avería</label>
            <textarea name="desc_averia" class="form-control" rows="3"
                      placeholder="Describe brevemente el problema, pruebas realizadas, etc."><?= htmlspecialchars($desc_averia_val) ?></textarea>
        </div>
    </div>
</div>


    <h2 class="h5">Datos de red (opcional)</h2>

<div class="col-md-4">
    <label class="form-label">Red</label>
    <select name="red_id" class="form-select" id="redSelect">
        <option value="">-- Sin red --</option>
        <?php foreach ($redes as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($red_sel == $r['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['direccion_red']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">IP principal</label>
    <select name="ip" id="ipSelect" class="form-select">
        <?php if ($ip_val): ?>
            <option value="<?= htmlspecialchars($ip_val) ?>" selected>
                <?= htmlspecialchars($ip_val) ?>
            </option>
        <?php else: ?>
            <option value="">Selecciona una red primero</option>
        <?php endif; ?>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">MAC</label>
    <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($mac_val) ?>">
</div>


    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-success">Guardar cambios</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const redSelect = document.getElementById('redSelect');
    const ipSelect  = document.getElementById('ipSelect');
    const currentIp = <?= json_encode($ip_val) ?>;
    const equipoId  = <?= (int)$equipo['id'] ?>;

    async function cargarIpsLibres(redId) {
        if (!redId) {
            ipSelect.innerHTML = '<option value="">Selecciona una red primero</option>';
            return;
        }

        ipSelect.innerHTML = '<option value="">Cargando IPs...</option>';

        try {
            const url = 'get_ips_libres.php?red_id=' + encodeURIComponent(redId)
                      + '&equipo_id=' + encodeURIComponent(equipoId);

            const resp = await fetch(url);
            const data = await resp.json();

            ipSelect.innerHTML = '<option value="">-- Selecciona IP --</option>';

            if (!Array.isArray(data) || data.length === 0) {
                const opt = document.createElement('option');
                opt.value = currentIp || '';
                opt.textContent = currentIp ? currentIp + ' (actual, única disponible)' : 'No hay IPs libres en esta red';
                opt.selected = !!currentIp;
                ipSelect.appendChild(opt);
                return;
            }

            // Añadir todas las IPs libres
            data.forEach(ip => {
                const opt = document.createElement('option');
                opt.value = ip;
                opt.textContent = ip;
                // si coincide con la IP actual, la dejamos seleccionada
                if (ip === currentIp) {
                    opt.selected = true;
                }
                ipSelect.appendChild(opt);
            });

            // Si la IP actual no está en la lista (por cualquier motivo), la añadimos al final
            if (currentIp && !data.includes(currentIp)) {
                const opt = document.createElement('option');
                opt.value = currentIp;
                opt.textContent = currentIp + ' (actual)';
                opt.selected = true;
                ipSelect.appendChild(opt);
            }

        } catch (e) {
            console.error(e);
            ipSelect.innerHTML = '<option value="">Error cargando IPs</option>';
        }
    }

    if (redSelect) {
        redSelect.addEventListener('change', function () {
            cargarIpsLibres(this.value);
        });

        // Si ya hay red seleccionada al entrar (editar), cargamos IPs de esa red
        if (redSelect.value) {
            cargarIpsLibres(redSelect.value);
        }
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const estadoSelect = document.querySelector('select[name="estado"]');
    const bloqueAveria = document.getElementById('bloqueAveria');

    if (estadoSelect && bloqueAveria) {
        function toggleAveria() {
            const val = estadoSelect.value.toLowerCase();
            if (val === 'averiado') {
                bloqueAveria.style.display = '';
            } else {
                bloqueAveria.style.display = 'none';
            }
        }

        estadoSelect.addEventListener('change', toggleAveria);
        toggleAveria();
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
