<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'includes/logger.php';

// Obtener ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("ID no válido"); }

// Teléfono
$stmtTel = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
$stmtTel->execute([':id' => $id]);
$tel = $stmtTel->fetch(PDO::FETCH_ASSOC);
if (!$tel) { die("Teléfono no encontrado"); }

// SIM actual
$sqlSim = "
    SELECT s.*
    FROM telefono_sim ts
    JOIN sims s ON s.id = ts.sim_id
    WHERE ts.telefono_id = :id
      AND ts.fecha_liberacion IS NULL
    LIMIT 1
";
$stmtSim = $pdo->prepare($sqlSim);
$stmtSim->execute([':id' => $id]);
$sim = $stmtSim->fetch(PDO::FETCH_ASSOC);

// Si el teléfono actual proviene de una renovación, recuperar el teléfono retirado
$stmtRen = $pdo->prepare("
    SELECT r.id AS renovacion_id,
           r.fecha AS renovacion_fecha,
           told.id AS old_id,
           told.etiqueta AS old_etiqueta,
           told.marca AS old_marca,
           told.modelo AS old_modelo,
           told.imei AS old_imei,
           told.numero_serie AS old_numero_serie,
           told.estado AS old_estado,
           told.fecha_baja AS old_fecha_baja
    FROM renovaciones_telefonos r
    JOIN telefonos told ON told.id = r.tel_old_id
    WHERE r.tel_new_id = :id
    ORDER BY r.fecha DESC, r.id DESC
    LIMIT 1
");
$stmtRen->execute([':id' => $id]);
$renovacion = $stmtRen->fetch(PDO::FETCH_ASSOC);

// Guardar log
logActividad(
    $pdo,
    "IMPRESION_PARTE_TELEFONO",
    "Impresion parte telefono ID={$id}",
    ['modulo' => 'TELEFONOS', 'nivel' => 'INFO']
);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Parte de entrega - Teléfono</title>

    <!-- Bootstrap local -->
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">

    <style>
        .no-print { display: block; }
        @media print { .no-print { display: none !important; } }

        .parte-container {
            background: white;
            padding: 25px;
            margin: auto;
            max-width: 900px;
            border: 1px solid #ccc;
        }

        .titulo-parte {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .logo-empresa {
            width: 90px;
        }

        .firma-box {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            text-align: center;
        }
        /* Ajuste de página A4 */
    @page {
        size: A4;
        margin: 8mm;
    }

    /* Reducir tamaño general */
    body, table, td, th {
        font-size: 12px;
    }

    /* Ajustar contenedor */
    .parte-container {
        max-width: 750px; /* más estrecho */
        padding: 15px; 
    }

    /* Títulos y espaciados más compactos */
    .titulo-parte {
        font-size: 20px;
        margin-bottom: 10px;
    }

    h4 {
        font-size: 14px;
        margin-top: 14px;
        margin-bottom: 6px;
    }

    table th,
    table td {
        padding: 4px !important;
    }

    </style>
</head>

<body class="bg-light">

<div class="container mt-4 no-print">
    <a href="telefonos.php" class="btn btn-secondary">Volver al listado</a>
    <button onclick="window.print()" class="btn btn-primary">Imprimir / Guardar PDF</button>
</div>

<div class="parte-container mt-4">

    <div class="text-center">
       
        <img src="assets/logo_departamento.png" class="logo-empresa" style="width: 80px !important" alt="Logo">
    </div>

    <div class="titulo-parte">PARTE DE ENTREGA DE TELÉFONO MÓVIL</div>

    <!-- Datos del teléfono -->
    <h4>Datos del teléfono</h4>
    <table class="table table-bordered">
        <tr><th>Código</th><td><span style="font-weight: 900;"><?= htmlspecialchars($tel['etiqueta']) ?></span></td></tr>
        <tr><th>Marca</th><td><?= htmlspecialchars($tel['marca']) ?></td></tr>
        <tr><th>Modelo</th><td><?= htmlspecialchars($tel['modelo']) ?></td></tr>
        <tr><th>IMEI</th><td><?= htmlspecialchars($tel['imei']) ?></td></tr>
        <tr><th>Número de serie</th><td><?= htmlspecialchars($tel['numero_serie'] ?: '-') ?></td></tr>
        <tr><th>Estado</th><td><?= htmlspecialchars($tel['estado']) ?></td></tr>
        <tr><th>Fecha alta</th><td><?= htmlspecialchars($tel['fecha_alta']) ?></td></tr>
        <tr><th>SIM heredada</th><td><?= $sim ? 'Sí' : 'No' ?></td></tr>
    </table>

    <?php if ($renovacion): ?>
        <h4>Teléfono retirado en la renovación</h4>
        <table class="table table-bordered">
            <tr><th>Etiqueta</th><td><span style="font-weight: 900;"><?= htmlspecialchars($renovacion['old_etiqueta'] ?: '-') ?></span></td></tr>
            <tr><th>Marca / Modelo</th><td><?= htmlspecialchars(trim(($renovacion['old_marca'] ?? '') . ' ' . ($renovacion['old_modelo'] ?? ''))) ?></td></tr>
            <tr><th>IMEI</th><td><?= htmlspecialchars($renovacion['old_imei'] ?: '-') ?></td></tr>
            <tr><th>Número de serie</th><td><?= htmlspecialchars($renovacion['old_numero_serie'] ?: '-') ?></td></tr>
            <tr><th>Estado</th><td><?= htmlspecialchars($renovacion['old_estado'] ?: 'Baja') ?></td></tr>
            <tr><th>Fecha de baja</th><td><?= htmlspecialchars($renovacion['old_fecha_baja'] ?: '-') ?></td></tr>
            <tr><th>Fecha renovación</th><td><?= htmlspecialchars($renovacion['renovacion_fecha'] ?: '-') ?></td></tr>
        </table>
    <?php endif; ?>

    <!-- SIM -->
    <h4>Datos de la tarjeta SIM</h4>
    <?php if ($sim): ?>
        <table class="table table-bordered">
            <tr><th>Número</th><td><span style="font-weight: 900;"><?= htmlspecialchars($sim['numero']) ?></span></td></tr>
            <tr><th>Número Corto:</th><td><?= htmlspecialchars($sim['numero_corto'] ?? '-') ?></td></tr>
            <tr><th>Operador</th><td><?= htmlspecialchars($sim['operador']) ?></td></tr>
            <tr><th>ICCID</th><td><?= htmlspecialchars($sim['iccid']) ?></td></tr>
            <tr><th>PIN</th><td><?= htmlspecialchars($sim['pin'] ?: '-') ?></td></tr>
            <tr><th>PUK</th><td><?= htmlspecialchars($sim['puk'] ?: '-') ?></td></tr>
        </table>
    <?php else: ?>
        <p class="text-muted">No hay SIM asociada actualmente.</p>
    <?php endif; ?>

    <!-- Datos del receptor -->
    <h4>Datos del usuario receptor</h4>
    <table class="table table-bordered">
        <tr><th>Nombre / TIP</th><td><span style="font-weight: 900;"><?= htmlspecialchars($tel['usuario_asignado'] ?: '-') ?></span></td></tr>
        <tr><th>Departamento</th><td><span style="font-weight: 900;"><?= htmlspecialchars($tel['departamento'] ?: '-') ?></span></td></tr>
        <tr><th>Ubicación</th><td><span style="font-weight: 900;"><?= htmlspecialchars($tel['ubicacion'] ?: '-') ?></span> </td></tr>
        <tr><th>Sección</th><td><span style="font-weight: 900;"><?= htmlspecialchars($tel['seccion'] ?: '-') ?></span></td></tr>
        <tr><th>Fecha entrega</th><td><?= htmlspecialchars($tel['fecha_entrega'] ?: date('Y-m-d')) ?></td></tr>
    </table>

    <!-- Condiciones -->
    <h4>Condiciones de uso</h4>
    <p>
        El usuario receptor se compromete a custodiar y hacer un uso responsable del dispositivo móvil,
        notificar cualquier pérdida o daño, activar la geolocalizaciónrespetar las políticas de la empresa y devolver el equipo cuando le sea requerido.
    </p>

    <!-- Firmas -->
    <div class="row mt-5">
        <div class="col-6 text-center">
            <p><b>Firma Responsable GATI</b></p>
            <p><?php echo $_SESSION['tip']?></p>
            <div class="firma-box">TIP y firma</div>
        </div>

        <div class="col-6 text-center">
            <p><b>Firma Usuario Receptor</b></p>
            <p></p>
            <div class="firma-box">TIP y firma</div>
        </div>
    </div>

</div>

</body>
</html>
