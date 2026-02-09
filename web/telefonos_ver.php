<?php
require_once 'auth.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de teléfono no válido.");
}

// Teléfono
$stmtTel = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
$stmtTel->execute([':id' => $id]);
$tel = $stmtTel->fetch(PDO::FETCH_ASSOC);

if (!$tel) {
    die("Teléfono no encontrado.");
}

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
        <h2>Teléfono móvil - <?= htmlspecialchars(($tel['etiqueta'] ? '['.$tel['etiqueta'].'] ' : '').$tel['marca'] . ' ' . $tel['modelo']) ?></h2>
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
                <tr><th>Etiqueta</th>        <td><?= htmlspecialchars($tel['etiqueta'] ?? '') ?></td></tr>
                <tr><th>Marca</th>           <td><?= htmlspecialchars($tel['marca'] ?? '') ?></td></tr>
                <tr><th>Modelo</th>          <td><?= htmlspecialchars($tel['modelo'] ?? '') ?></td></tr>
                <tr><th>IMEI</th>            <td><?= htmlspecialchars($tel['imei'] ?? '') ?></td></tr>
                <tr><th>Número de serie</th> <td><?= htmlspecialchars($tel['numero_serie'] ?? '') ?></td></tr>
                <tr><th>Usuario asignado</th><td><?= htmlspecialchars($tel['usuario_asignado'] ?? '') ?></td></tr>
                <tr><th>Departamento</th>    <td><?= htmlspecialchars($tel['departamento'] ?? '') ?></td></tr>
                <tr><th>Ubicación</th>       <td><?= htmlspecialchars($tel['ubicacion'] ?? '') ?></td></tr>
                <tr><th>Sección</th>         <td><?= htmlspecialchars($tel['seccion'] ?? '') ?></td></tr>
                <tr><th>Estado</th>          <td><?= htmlspecialchars($tel['estado'] ?? '') ?></td></tr>
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
                <div class="card-header"><b>SIM actual</b></div>
                <div class="card-body">
                    <?php if ($simActual): ?>
                        <p><b>Número:</b> <span class="etiqueta-numero"><?= htmlspecialchars($simActual['numero']) ?></span></p>
                        <p><b>Operador:</b> <?= htmlspecialchars($simActual['operador']) ?></p>
                        <p><b>ICCID:</b> <?= htmlspecialchars($simActual['iccid']) ?></p>
                        <p><b>Estado SIM:</b> <?= htmlspecialchars($simActual['estado']) ?></p>
                    <?php else: ?>
                        <p class="text-muted">Este teléfono no tiene SIM asignada actualmente.</p>
                    <?php endif; ?>
                    <a href="telefono_historial.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">
                        Ver historial completo
                    </a>
                </div>
            </div>

            <?php if (!empty($tel['imagen'])): ?>
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
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
