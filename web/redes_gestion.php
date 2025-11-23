<?php
// redes_gestion.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';


$errores = [];
$mensajes = [];

// --- GESTIÓN POST (crear / editar / borrar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
    $nombre        = strtoupper(trim($_POST['nombre'] ?? ''));
    $direccion_red = trim($_POST['direccion_red'] ?? '');
    $mascara       = trim($_POST['mascara'] ?? '24');

    if ($nombre === '' || $direccion_red === '') {
        $errores[] = "Nombre y dirección de red son obligatorios para crear una red.";
    } else {
        // Validar máscara simple: 16–30
        $mascaraInt = (int)$mascara;
        if ($mascaraInt < 16 || $mascaraInt > 30) {
            $errores[] = "La máscara CIDR debe estar entre 16 y 30.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO redes (nombre, direccion_red, mascara)
                    VALUES (:nombre, :direccion_red, :mascara)
                ");
                $stmt->execute([
                    ':nombre'        => $nombre,
                    ':direccion_red' => $direccion_red,
                    ':mascara'       => (string)$mascaraInt,
                ]);
                header('Location: redes_gestion.php?msg=creada');
                exit;
            } catch (Exception $e) {
                $errores[] = "Error al crear la red: " . $e->getMessage();
            }
        }
    }

    } elseif ($accion === 'editar') {
    $id            = (int)($_POST['id'] ?? 0);
    $nombre        = strtoupper(trim($_POST['nombre'] ?? ''));
    $direccion_red = trim($_POST['direccion_red'] ?? '');
    $mascara       = trim($_POST['mascara'] ?? '24');

    if ($id <= 0) {
        $errores[] = "ID de red no válido.";
    } elseif ($nombre === '' || $direccion_red === '') {
        $errores[] = "Nombre y dirección de red son obligatorios.";
    } else {
        $mascaraInt = (int)$mascara;
        if ($mascaraInt < 16 || $mascaraInt > 30) {
            $errores[] = "La máscara CIDR debe estar entre 16 y 30.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE redes
                    SET nombre = :nombre,
                        direccion_red = :direccion_red,
                        mascara = :mascara
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':nombre'        => $nombre,
                    ':direccion_red' => $direccion_red,
                    ':mascara'       => (string)$mascaraInt,
                    ':id'            => $id,
                ]);
                header('Location: redes_gestion.php?msg=editada');
                exit;
            } catch (Exception $e) {
                $errores[] = "Error al editar la red: " . $e->getMessage();
            }
        }
    }


    } elseif ($accion === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $errores[] = "ID de red no válido para borrar.";
        } else {
            try {
                // Comprobar si tiene IPs asociadas
                $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM ips_equipos WHERE red_id = :id");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalIps = (int)($row['total'] ?? 0);

                if ($totalIps > 0) {
                    $errores[] = "No se puede borrar la red porque tiene $totalIps IP(s) asociada(s).";
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM redes WHERE id = :id");
                    $stmtDel->execute([':id' => $id]);
                    header('Location: redes_gestion.php?msg=borrada');
                    exit;
                }
            } catch (Exception $e) {
                $errores[] = "Error al borrar la red: " . $e->getMessage();
            }
        }
    }
}

// --- MENSAJES POR GET (PRG pattern) ---
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'creada':
            $mensajes[] = "Red creada correctamente.";
            break;
        case 'editada':
            $mensajes[] = "Red editada correctamente.";
            break;
        case 'borrada':
            $mensajes[] = "Red borrada correctamente.";
            break;
    }
}

// --- DATOS PARA PINTAR LA PÁGINA ---

// ¿Estamos editando alguna red?
$editId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$editRed = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM redes WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editRed = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editRed) {
        $errores[] = "La red que intentas editar no existe.";
        $editId = 0;
    }
}

// Listado de redes
$stmtRedes = $pdo->query("SELECT id, nombre, direccion_red, mascara FROM redes ORDER BY id ASC");

$redes = $stmtRedes->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4">Gestión de redes</h1>

<?php if ($mensajes): ?>
    <?php foreach ($mensajes as $m): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($m) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($errores): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card">
            <div class="card-header">
                <?php if ($editRed): ?>
                    Editar red #<?= (int)$editRed['id'] ?>
                <?php else: ?>
                    Nueva red
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <?php if ($editRed): ?>
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= (int)$editRed['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="accion" value="crear">
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label">Nombre de la red *</label>
                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($editRed['nombre'] ?? '') ?>"
                            placeholder="Red OFICINA, RED ALMACÉN, etc."
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label">Dirección de red *</label>
                        <input
                            type="text"
                            name="direccion_red"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($editRed['direccion_red'] ?? '') ?>"
                            placeholder="Ej: 10.52.2.0/24"
                        >
                        <div class="form-text">
                            Se asume /24 para el cálculo de IPs libres (rango 1–254).
                        </div>
                    </div>
                    <div class="col-12">
    <label class="form-label">Dirección de red *</label>
    <input
        type="text"
        name="direccion_red"
        class="form-control"
        required
        value="<?= htmlspecialchars($editRed['direccion_red'] ?? '') ?>"
        placeholder="Ej: 192.168.3.0"
    >
    <div class="form-text">
        La máscara se indica en el campo siguiente (CIDR).
    </div>
</div>

<div class="col-12">
    <label class="form-label">Máscara (CIDR) *</label>
    <input
        type="number"
        name="mascara"
        class="form-control"
        min="16"
        max="30"
        value="<?= htmlspecialchars($editRed['mascara'] ?? '24') ?>"
        required
    >
    <div class="form-text">
        Ejemplos: 24 (=255.255.255.0), 23 (=255.255.254.0), etc.
    </div>
</div>


                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-success">
                            <?= $editRed ? 'Guardar cambios' : 'Crear red' ?>
                        </button>
                        <?php if ($editRed): ?>
                            <a href="redes_gestion.php" class="btn btn-secondary ms-2">Cancelar edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="card">
            <div class="card-header">
                Redes configuradas
            </div>
            <div class="card-body">
                <?php if (empty($redes)): ?>
                    <div class="alert alert-info mb-0">
                        No hay redes definidas todavía.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th>Nombre</th>
                                    <th>Dirección de red</th>
                                    <th style="width: 160px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redes as $r): ?>
                                    <tr>
                                        <td><?= (int)$r['id'] ?></td>
                                        <td><?= htmlspecialchars($r['nombre']) ?></td>
                                        <?php
$textoRed = $r['direccion_red'];
if (!empty($r['mascara'])) {
    $textoRed .= '/' . $r['mascara'];
}
?>
<td><code><?= htmlspecialchars($textoRed) ?></code></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="redes_gestion.php?editar=<?= (int)$r['id'] ?>"
                                                   class="btn btn-outline-secondary">
                                                    Editar
                                                </a>

                                                <form method="post" class="d-inline"
                                                      onsubmit="return confirm('¿Seguro que quieres borrar esta red? Solo se borrará si no tiene IPs asociadas.');">
                                                    <input type="hidden" name="accion" value="borrar">
                                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        Borrar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
