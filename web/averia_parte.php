<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID de avería no válido');
}

$sql = "
    SELECT 
        a.*,
        e.tipo,
        e.marca,
        e.modelo,
        e.numero_serie,
        e.hostname,
        e.usuario_asignado,
        e.departamento,
        e.ubicacion,
        e.imagen,
        ip.ip AS ip_principal
    FROM averias a
    JOIN equipos e ON e.id = a.equipo_id
    LEFT JOIN ips_equipos ip 
        ON ip.equipo_id = e.id AND ip.es_principal = 1
    WHERE a.id = :id
";


$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);

logActividad($pdo, 'VER_PARTE_AVERIA', 'Parte de avería visualizado: ID=' . $id);

$averia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$averia) {
    die('Avería no encontrada');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Parte de avería #<?= htmlspecialchars($averia['id']) ?></title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 10mm;
            }
        }
        .parte-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #ddd;
            padding: 20px 30px;
        }
        .titulo-parte {
            text-transform: uppercase;
            letter-spacing: 1px;
        }

            @page {
        size: A4;
        margin: 8mm;
    }

    .logo-empresa {
    width: 90px;
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
<body>

<div class="container mt-3 mb-5">
    <div class="no-print mb-3 d-flex justify-content-between">
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm">Volver al listado</a>
            <a href="equipo_editar.php?id=<?= (int)$averia['equipo_id'] ?>" class="btn btn-outline-secondary btn-sm">
                Ver equipo
            </a>
        </div>
        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>

    <div class="parte-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 titulo-parte mb-0">Parte de avería</h1>
            <div class="text-center">
       
        <img src="assets/logo_departamento.png" class="logo-empresa" alt="Logo">
    </div>
            <div class="text-end">
                <div><strong>Nº Parte:</strong> <?= sprintf('%06d', $averia['id']) ?></div>
                <div><strong>Fecha:</strong> <?= htmlspecialchars($averia['fecha_creacion']) ?></div>
            </div>
        </div>

        <hr>

        <h2 class="h6 mt-3">Datos de la avería</h2>
        <table class="table table-sm">
            <tbody>
                <tr>
                    <th style="width: 200px;">Tipo de avería</th>
                    <td><?= htmlspecialchars($averia['tipo_averia']) ?></td>
                </tr>
                <tr>
                    <th>Nº asunto empresa externa</th>
                    <td><?= htmlspecialchars($averia['num_asunto']) ?></td>
                </tr>
                <tr>
                    <th>Empresa externa</th>
                    <td><?= htmlspecialchars($averia['empresa_ext'] ?? '') ?></td>
                </tr>
                <tr>
                    <th>Descripción</th>
                    <td><?= nl2br(htmlspecialchars($averia['descripcion'] ?? '')) ?></td>
                </tr>
            </tbody>
        </table>

        <h2 class="h6 mt-4">Datos del equipo</h2>
        <table class="table table-sm">
            <tbody>
                <tr>
                    <th style="width: 200px;">ID equipo</th>
                    <td><?= (int)$averia['equipo_id'] ?></td>
                </tr>
                <?php if (!empty($averia['imagen'])): ?>
                <tr>
                    <th>Imagen</th>
                    <td>
                        <img src="<?= htmlspecialchars($averia['imagen']) ?>"
                            alt="Imagen del equipo"
                            style="max-width:180px; height:auto; border:1px solid #ccc; padding:4px;">
                    </td>
                </tr>
                <?php endif; ?>

        <tr>
            <th>Tipo</th>
            <td><?= htmlspecialchars($averia['tipo']) ?></td>
        </tr>
                <tr>
                    <th>Tipo / Marca / Modelo</th>
                    <td><?= htmlspecialchars($averia['tipo']) ?> / <?= htmlspecialchars($averia['marca']) ?> / <?= htmlspecialchars($averia['modelo']) ?></td>
                </tr>
                <tr>
                    <th>Nº de serie</th>
                    <td><?= htmlspecialchars($averia['numero_serie']) ?></td>
                </tr>
                <tr>
                    <th>Servicio</th>
                    <td><?= htmlspecialchars($averia['hostname']) ?></td>
                </tr>
                <tr>
                    <th>Usuario asignado</th>
                    <td><?= htmlspecialchars($averia['usuario_asignado']) ?></td>
                </tr>
                <tr>
                    <th>Departamento</th>
                    <td><?= htmlspecialchars($averia['departamento']) ?></td>
                </tr>
                <tr>
                    <th>Ubicación</th>
                    <td><?= htmlspecialchars($averia['ubicacion']) ?></td>
                </tr>
                <tr>
                    <th>IP principal</th>
                    <td><?= htmlspecialchars($averia['ip_principal'] ?? '') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="mt-5 row">
            <div class="col-6 text-center">
                <p><strong>Firma empresa</strong></p>
                <div style="height:60px;border-bottom:1px solid #000;"></div>
            </div>
            <div class="col-6 text-center">
                <p><strong>Firma técnico externo</strong></p>
                <div style="height:60px;border-bottom:1px solid #000;"></div>
            </div>
        </div>
    </div>
</div>

<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
