<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

// Cargar redes para el select
$redesStmt = $pdo->query("SELECT id, nombre, direccion_red FROM redes ORDER BY id ASC");
$redes = $redesStmt->fetchAll(PDO::FETCH_ASSOC);

// Monitores libres (no asignados todavía)
$monitoresStmt = $pdo->query("
    SELECT e.id, e.marca, e.modelo, e.numero_serie
    FROM equipos e
    LEFT JOIN pc_monitores pm ON pm.id_monitor = e.id
    WHERE e.tipo = 'MONITOR'
      AND pm.id_monitor IS NULL
    ORDER BY e.marca, e.modelo, e.numero_serie
");
$monitores = $monitoresStmt->fetchAll(PDO::FETCH_ASSOC);


$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo            = trim($_POST['tipo'] ?? '');
    $marca           = strtoupper(trim($_POST['marca'] ?? ''));
    $modelo          = strtoupper(trim($_POST['modelo'] ?? ''));
    $numero_serie    = strtoupper(trim($_POST['numero_serie'] ?? ''));
    $hostname        = strtoupper(trim($_POST['hostname'] ?? ''));
    $usuario_asignado= strtoupper(trim($_POST['usuario_asignado'] ?? ''));
    $departamento    = strtoupper(trim($_POST['departamento'] ?? ''));
    $ubicacion       = strtoupper(trim($_POST['ubicacion'] ?? ''));
    $fecha_compra    = $_POST['fecha_compra'] ?? null;
    $proveedor       = strtoupper(trim($_POST['proveedor'] ?? ''));
    $coste           = $_POST['coste'] ?? null;
    $estado          = trim($_POST['estado'] ?? 'En uso');
    $notas           = trim($_POST['notas'] ?? '');

    $ip              = trim($_POST['ip'] ?? '');
    $mac             = strtoupper(trim($_POST['mac'] ?? ''));
    $red_id          = $_POST['red_id'] ?? '';
    $monitoresSeleccionados = isset($_POST['monitores']) && is_array($_POST['monitores'])
    ? array_map('intval', $_POST['monitores'])
    : [];


    if ($tipo === '') {
        $errores[] = "El campo Tipo es obligatorio.";
    }


/* // Procesar imagen si se ha enviado
if (!empty($_FILES['imagen']['name'])) {
    $uploadDir = __DIR__ . '/uploads/equipos/';

    // Crear carpeta si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // Nombre de archivo limpio
    $nombreOriginal = basename($_FILES['imagen']['name']);
    $nombreLimpio = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nombreOriginal);
    $nombreFinal = time() . '_' . $nombreLimpio;

    $rutaRelativa = 'uploads/equipos/' . $nombreFinal;     // Lo que guardas en BD
    $rutaFisica   = $uploadDir . $nombreFinal;             // Ruta en el disco

    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaFisica)) {
        $imagenRuta = $rutaRelativa;
    } else {
        $errores[] = "No se pudo guardar la imagen del equipo.";
    }
} */

   
$imagenRuta = null;

// 1) Si ha seleccionado una imagen ya existente
if (!empty($_POST['imagen_existente'])) {
    $file = basename($_POST['imagen_existente']);
    $imagenRuta = 'uploads/equipos/' . $file;

// 2) Si no hay existente pero sube una nueva
} elseif (!empty($_FILES['imagen']['name'])) {
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
        $imagenRuta = $rutaRelativa;
    } else {
        $errores[] = "No se pudo guardar la imagen del equipo.";
    }
}



// Comprobar si el número de serie ya existe
if ($numero_serie !== '') {
    $stmtCheck = $pdo->prepare("SELECT id FROM equipos WHERE numero_serie = :ns");
    $stmtCheck->execute([':ns' => $numero_serie]);

    if ($stmtCheck->fetch()) {
        $errores[] = "El número de serie $numero_serie ya existe en otro equipo.";
    }
}
    // Si no hay errores, proceder a guardar

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $sqlEquipo = "INSERT INTO equipos 
                (tipo, marca, modelo, numero_serie, hostname, usuario_asignado, departamento, ubicacion, fecha_compra, proveedor, coste, estado, notas, imagen)
                VALUES 
                (:tipo, :marca, :modelo, :numero_serie, :hostname, :usuario_asignado, :departamento, :ubicacion, :fecha_compra, :proveedor, :coste, :estado, :notas, :imagen)";
            $stmtEq = $pdo->prepare($sqlEquipo);
            $stmtEq->execute([
                ':tipo'            => $tipo,
                ':marca'           => $marca,
                ':modelo'          => $modelo,
                ':numero_serie'    => $numero_serie,
                ':hostname'        => $hostname,
                ':usuario_asignado'=> $usuario_asignado,
                ':departamento'    => $departamento,
                ':ubicacion'       => $ubicacion,
                ':fecha_compra'    => $fecha_compra ?: null,
                ':proveedor'       => $proveedor,
                ':coste'           => $coste !== '' ? $coste : null,
                ':estado'          => $estado,
                ':notas'           => $notas,
                ':imagen'          => $imagenRuta,
            ]);

            $equipoId = (int)$pdo->lastInsertId();

            // Si es PC o PORTÁTIL, guardar monitores asociados
        if (in_array($tipo, ['PC','PORTÁTIL']) && !empty($monitoresSeleccionados)) {
            $sqlRel = "INSERT INTO pc_monitores (id_pc, id_monitor)
                    VALUES (:id_pc, :id_monitor)";
            $stmtRel = $pdo->prepare($sqlRel);

            foreach ($monitoresSeleccionados as $idMon) {
                if ($idMon > 0) {
                    $stmtRel->execute([
                        ':id_pc'     => $equipoId,
                        ':id_monitor'=> $idMon
                    ]);
                }
            }
        }


            if ($ip !== '' && $red_id !== '') {
                $sqlIp = "INSERT INTO ips_equipos (equipo_id, red_id, ip, mac, es_principal, notas)
                          VALUES (:equipo_id, :red_id, :ip, :mac, 1, NULL)";
                $stmtIp = $pdo->prepare($sqlIp);
                $stmtIp->execute([
                    ':equipo_id' => $equipoId,
                    ':red_id'    => $red_id,
                    ':ip'        => $ip,
                    ':mac'       => $mac,
                ]);
            }

            logActividad($pdo, 'CREAR_EQUIPO', 'Nuevo equipo creado: ID=' . $equipoId);

            $pdo->commit();
             // 🔴 Redirección ANTES de sacar NADA de HTML
            header('Location: index.php?msg=ok');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al guardar el equipo: " . $e->getMessage();
        }

        
        

    }
 
}
   // SOLO si NO hubo redirección, cargamos el HTML
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-3">Nuevo equipo</h1>

<?php if ($errores): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Carpeta FÍSICA donde guarda las imágenes de equipos
$uploadDirFs = __DIR__ . '/uploads/equipos/';

$imagenes_existentes = [];
if (is_dir($uploadDirFs)) {
    $imagenes_existentes = glob(
        $uploadDirFs . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}',
        GLOB_BRACE
    );
}
?>


<form method="post" class="row g-3" enctype="multipart/form-data">
    <div class="col-md-4">
        <label class="form-label">Tipo *</label>
        <select name="tipo" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <?php
            $tipos = ['PC', 'PORTÁTIL', 'MONITOR', 'IMPRESORA', 'ESCÁNER', 'SWITCH', 'ROUTER', 'MOVIL', 'TABLET', 'VIDEO', 'OTRO'];
            foreach ($tipos as $t):
            ?>
                <option value="<?= $t ?>" <?= (($_POST['tipo'] ?? '') === $t) ? 'selected' : '' ?>>
                    <?= $t ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-8" id="bloque-monitores" style="display:none;">
    <label class="form-label">Monitores libres para asociar</label>
        <select name="monitores[]" class="form-select" multiple size="5">
            <?php
            $postMonitores = isset($_POST['monitores']) && is_array($_POST['monitores'])
                ? array_map('intval', $_POST['monitores'])
                : [];
            foreach ($monitores as $m):
                $idMon = (int)$m['id'];
                $selected = in_array($idMon, $postMonitores) ? 'selected' : '';
            ?>
                <option value="<?= $idMon ?>" <?= $selected ?>>
                    <?= htmlspecialchars(trim(
                        ($m['marca'] ?? '') . ' ' .
                        ($m['modelo'] ?? '') .
                        ( $m['numero_serie'] ? ' [SN: '.$m['numero_serie'].']' : '' )
                    )) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="form-text text-muted">
            Mantén Ctrl (o Cmd en Mac) para seleccionar varios monitores.
        </small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Marca</label>
        <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($_POST['marca'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Modelo</label>
        <input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Número de serie</label>
        <input type="text" name="numero_serie" class="form-control" value="<?= htmlspecialchars($_POST['numero_serie'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Servicio</label>
        <select name="estado" class="form-select">
            <?php
            $hostnames = ['-----','Intranet', 'Internet', 'SITEL', 'VPN', 'Otro'];
            $hostname = $_POST['hostname'] ?? '';
            foreach ($hostnames as $hostname):
            ?>
                <option value="<?= $hostname ?>" <?= ($hostnames === $hostname) ? 'selected' : '' ?>>
                    <?= $hostname ?>
                </option>
            <?php endforeach; ?>
        </select>
        
    </div>
    <div class="col-md-4">
        <label class="form-label">Usuario asignado</label>
        <input type="text" name="usuario_asignado" class="form-control" value="<?= htmlspecialchars($_POST['usuario_asignado'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Departamento</label>
        <input type="text" name="departamento" class="form-control" value="<?= htmlspecialchars($_POST['departamento'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Ubicación</label>
        <input type="text" name="ubicacion" class="form-control" value="<?= htmlspecialchars($_POST['ubicacion'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha Alta</label>
        <input type="date" name="fecha_compra" class="form-control" value="<?= htmlspecialchars($_POST['fecha_compra'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Proveedor</label>
        <input type="text" name="proveedor" class="form-control" value="<?= htmlspecialchars($_POST['proveedor'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Coste (€)</label>
        <input type="number" step="0.01" name="coste" class="form-control" value="<?= htmlspecialchars($_POST['coste'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
            <?php
            $estados = ['Activo', 'Almacén', 'Averiado', 'Baja', 'Prestado'];
            $estadoSel = $_POST['estado'] ?? 'Activo';
            foreach ($estados as $est):
            ?>
                <option value="<?= $est ?>" <?= ($estadoSel === $est) ? 'selected' : '' ?>>
                    <?= $est ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
            
    
<div class="mb-3">
    <label class="form-label"><b>Imagenes existente en Base de Datos</b></label>
    <select name="imagen_existente" id="imagen_existente" class="form-control">
        <option value="">-- Seleccionar una imagen ya subida --</option>
        <?php foreach ($imagenes_existentes as $rutaFs): ?>
            <?php $file = basename($rutaFs); ?>
            <option value="<?= htmlspecialchars($file) ?>">
                <?= htmlspecialchars($file) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="mb-3">
    <img id="preview_img_nuevo"
         style="display:none;max-width:180px;border:1px solid #ccc;margin-top:8px;">
</div>

<div class="mb-3">
    <label for="imagen" class="form-label"><b>Subir imagen nueva para este equipo</b>(Suba una imagen si no hay ninguna disponible)</label>
    <input type="file" class="form-control" name="imagen" accept="image/*">
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const select  = document.getElementById('imagen_existente');
    const preview = document.getElementById('preview_img_nuevo');

    if (select) {
        select.addEventListener('change', function () {
            if (this.value) {
                // Ruta WEB, no uses __DIR__ aquí
                preview.src = 'uploads/equipos/' + this.value;
                preview.style.display = 'block';
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        });
    }
});
</script>



    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="3"><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
    </div>

    <hr class="mt-4">

    <h2 class="h5">Datos de red (opcional)</h2>

    <div class="col-md-4">
    <label class="form-label">Red</label>
    <select name="red_id" class="form-select" id="redSelect">
        <option value="">-- Sin red --</option>
        <?php foreach ($redes as $r): ?>
            <option value="<?= $r['id'] ?>" <?= (($_POST['red_id'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['direccion_red']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">IP principal</label>
    <select name="ip" id="ipSelect" class="form-select">
        <option value="">Selecciona una red primero</option>
        <?php
        // Si hubo error y el usuario ya había elegido una IP, la mostramos seleccionada
        $ipSelected = $_POST['ip'] ?? '';
        if ($ipSelected !== ''): ?>
            <option value="<?= htmlspecialchars($ipSelected) ?>" selected>
                <?= htmlspecialchars($ipSelected) ?>
            </option>
        <?php endif; ?>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">MAC</label>
    <input type="text" name="mac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF" value="<?= htmlspecialchars($_POST['mac'] ?? '') ?>">
</div>


    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-success">Guardar</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
<!-- JavaScript para mostrar/ocultar bloque de monitores -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect       = document.querySelector('select[name="tipo"]');
    const bloqueMonitores  = document.getElementById('bloque-monitores');

    function actualizarBloqueMonitores() {
        if (!tipoSelect) return;
        const valor = (tipoSelect.value || '').toUpperCase();
        if (valor === 'PC' || valor === 'PORTÁTIL') {
            bloqueMonitores.style.display = 'block';
        } else {
            bloqueMonitores.style.display = 'none';
        }
    }

    if (tipoSelect && bloqueMonitores) {
        tipoSelect.addEventListener('change', actualizarBloqueMonitores);
        actualizarBloqueMonitores(); // Estado inicial (por si hay errores en POST)
    }
});
</script>
<!-- JavaScript para cargar IPs libres según red seleccionada -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const redSelect = document.getElementById('redSelect');
    const ipSelect  = document.getElementById('ipSelect');
    const currentIp = <?= json_encode($_POST['ip'] ?? '') ?>;

    async function cargarIpsLibres(redId) {
        ipSelect.innerHTML = '<option value="">Cargando IPs...</option>';

        if (!redId) {
            ipSelect.innerHTML = '<option value="">Selecciona una red primero</option>';
            return;
        }

        try {
            const resp = await fetch('get_ips_libres.php?red_id=' + encodeURIComponent(redId));
            const data = await resp.json();

            ipSelect.innerHTML = '<option value="">-- Selecciona IP --</option>';

            if (!Array.isArray(data) || data.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No hay IPs libres en esta red';
                ipSelect.appendChild(opt);
                return;
            }

            data.forEach(ip => {
                const opt = document.createElement('option');
                opt.value = ip;
                opt.textContent = ip;
                if (ip === currentIp) {
                    opt.selected = true;
                }
                ipSelect.appendChild(opt);
            });
        } catch (e) {
            console.error(e);
            ipSelect.innerHTML = '<option value="">Error cargando IPs</option>';
        }
    }

    if (redSelect) {
        redSelect.addEventListener('change', function () {
            cargarIpsLibres(this.value);
        });

        // Si ya había una red seleccionada (por validación fallida), recargar IPs al entrar
        if (redSelect.value) {
            cargarIpsLibres(redSelect.value);
        }
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
