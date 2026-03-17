<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/asset_files.php';

ensureAssetFilesSchema($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de teléfono no válido.");
}

// Teléfono
$stmtTel = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
$stmtTel->execute([':id' => $id]);
$tel = $stmtTel->fetch(PDO::FETCH_ASSOC);
$adjuntosTelefono = listEntityAttachments($pdo, 'telefono', $id);

if (!$tel) {
    die("Teléfono no encontrado.");
}

$estadoTelefono = (string)($tel['estado'] ?? '');
$estadoKey = strtr(trim($estadoTelefono), [
    'Á' => 'A',
    'É' => 'E',
    'Í' => 'I',
    'Ó' => 'O',
    'Ú' => 'U',
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
]);
$estadoKey = strtolower($estadoKey);
$estadoClase = match ($estadoKey) {
    'activo'   => 'estado-activo',
    'averiado' => 'estado-averiado',
    'baja'     => 'estado-baja',
    'baja definitiva' => 'estado-baja-definitiva',
    'almacen'  => 'estado-almacen',
    'prestado' => 'estado-prestado',
    default    => '',
};

// SIM actual (si la hay)
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
$stmtSim->execute([':id' => $id]);
$simActual = $stmtSim->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>
            Teléfono móvil -
            <!-- <?php if (!empty($tel['etiqueta'])): ?>
                <span class="etiqueta-ok etiqueta-inline">[<?= htmlspecialchars($tel['etiqueta']) ?>]</span>
            <?php endif; ?> -->
            <?= htmlspecialchars($tel['marca'] . ' ' . $tel['modelo']) ?>
        </h2>
        <div>
            <a href="telefonos_editar.php?id=<?= (int)$id ?>" class="btn btn-warning">Editar</a>
            <a href="telefono_historial.php?id=<?= (int)$id ?>" class="btn btn-info">Historial SIM</a>
           
           <a href="telefono_parte.php?id=<?= (int)$id ?>" 
   class="btn btn-secondary" target="_blank">
   Parte de entrega
</a>
            <a href="telefono_renovar.php?id=<?= (int)$id ?>" class="btn btn-success">Renovar</a>
            <a href="telefonos.php" class="btn btn-secondary">Volver al listado</a>
        </div>
    </div>

    <div class="row">
        <!-- Datos principales -->
        <div class="col-md-8">
            <table class="table table-bordered">
                <tr><th>ID</th>              <td><?= (int)$tel['id'] ?></td></tr>
                <tr><th>Etiqueta</th>        <td><span class="<?= !empty($tel['etiqueta']) ? 'etiqueta-ok' : 'etiqueta-missing' ?>"><?= htmlspecialchars(!empty($tel['etiqueta']) ? $tel['etiqueta'] : '(sin etiqueta)') ?></span></td></tr>
                <tr><th>Marca</th>           <td><?= htmlspecialchars($tel['marca'] ?? '') ?></td></tr>
                <tr><th>Modelo</th>          <td><?= htmlspecialchars($tel['modelo'] ?? '') ?></td></tr>
                <tr><th>IMEI</th>            <td><?= htmlspecialchars($tel['imei'] ?? '') ?></td></tr>
                <tr><th>Número de serie</th> <td><?= htmlspecialchars($tel['numero_serie'] ?? '') ?></td></tr>
                <tr>
                    <th>Usuario asignado</th>
                    <td>
                        <?php $usuarioAsignado = trim((string)($tel['usuario_asignado'] ?? '')); ?>
                        <span class="dato-contacto-destacado<?= $usuarioAsignado === '' ? ' dato-contacto-destacado-vacio' : '' ?>">
                            <?= htmlspecialchars($usuarioAsignado !== '' ? $usuarioAsignado : '-') ?>
                        </span>
                        <?php if ($usuarioAsignado !== ''): ?>
                            <a class="btn btn-sm btn-outline-primary ms-2" href="usuario_asociado.php?tip=<?= urlencode($usuarioAsignado) ?>">
                                Ver todo por TIP
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><th>Departamento</th>    <td><?= htmlspecialchars($tel['departamento'] ?? '') ?></td></tr>
                <tr><th>Ubicación</th>       <td><?= htmlspecialchars($tel['ubicacion'] ?? '') ?></td></tr>
                <tr><th>Sección</th>         <td><?= htmlspecialchars($tel['seccion'] ?? '') ?></td></tr>
                <tr><th>Estado</th>          <td class="<?= $estadoClase ?>"><?= htmlspecialchars($estadoTelefono) ?></td></tr>
                <tr><th>Fecha alta</th>      <td><?= htmlspecialchars($tel['fecha_alta'] ?? '') ?></td></tr>
                <tr><th>Fecha baja</th>      <td><?= htmlspecialchars($tel['fecha_baja'] ?? '') ?></td></tr>
                <!-- <tr><th>Proveedor</th>       <td><?= htmlspecialchars($tel['proveedor']) ?></td></tr>
                <tr><th>Coste</th>           <td><?= htmlspecialchars($tel['coste']??'') ?> €</td></tr> -->
                <tr>
                    <th>Observaciones</th>
                    <td><?= nl2br(htmlspecialchars($tel['observaciones'])) ?></td>
                </tr>
            </table>
        </div>

        <!-- Bloque SIM actual + imagen -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header"><b>SIM actual </b>
                <span class="etiqueta-numero">
                    <?= htmlspecialchars($simActual['etiqueta'] ?? '') ?>
                </span>
            </div>
                <div class="card-body">
                    <?php if ($simActual): ?>
                        <p><b>Número:</b>
                            <a href="sims_ver.php?id=<?= (int)$simActual['id'] ?>" class="etiqueta-numero text-decoration-none">
                                <?= htmlspecialchars($simActual['numero']) ?>
                            </a>
                        </p>
                        <p><b>Operador:</b> <?= htmlspecialchars($simActual['operador']) ?></p>
                        <p><b>ICCID:</b> <?= htmlspecialchars($simActual['iccid']) ?></p>
                        <p><b>Estado SIM:</b> <?= htmlspecialchars($simActual['estado']) ?></p>
                        <a
                            href="sim_liberar.php?telefono_id=<?= (int)$id ?>&return=<?= urlencode('telefonos_ver.php?id=' . (int)$id) ?>"
                            class="btn btn-sm btn-outline-danger mb-2"
                            data-confirm-message="Se quitara la SIM actual de este telefono. Continuar?"
                        >
                            Quitar SIM de este telefono
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Este teléfono no tiene SIM asignada actualmente.</p>
                    <?php endif; ?>
                    <a href="telefono_historial.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">
                        Ver historial completo
                    </a>
                </div>
            </div>

            <?php if (!empty($tel['imagen']) && is_file(__DIR__ . '/' . ltrim((string)$tel['imagen'], '/'))): ?>
                <div class="card">
                    <div class="card-header"><b>Imagen</b></div>
                    <div class="card-body text-center">
                        <img src="<?= htmlspecialchars($tel['imagen']) ?>" 
                             alt="Imagen teléfono"
                             class="img-fluid"
                             style="max-height: 250px; object-fit: contain;">
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($adjuntosTelefono)): ?>
                <div class="card mt-3">
                    <div class="card-header"><b>Adjuntos</b></div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($adjuntosTelefono as $adjunto): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                    <a href="<?= htmlspecialchars($adjunto['file_path']) ?>" target="_blank" rel="noopener">
                                        <?= htmlspecialchars($adjunto['original_name']) ?>
                                    </a>
                                    <span class="text-muted small"><?= htmlspecialchars(formatAttachmentSize((int)($adjunto['file_size'] ?? 0))) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
