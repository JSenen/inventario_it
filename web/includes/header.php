<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Informático</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap local -->
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css"> 
    <!-- <link rel="stylesheet" href="vendor/bootstrap/css/theme_wildwest.css"> -->
     <!-- CSS local -->
    <link rel="stylesheet" href="vendor/datatables/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="vendor/datatables/css/buttons.dataTables.min.css">


    <style>
    /* Overlay de puertas del saloon */
.saloon-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    background: radial-gradient(circle at 50% 10%, rgba(250, 230, 190, 0.35), rgba(0, 0, 0, 0.9));
    overflow: hidden;
}

/* Cada puerta ocupa media pantalla */
.saloon-door {
    flex: 1;
    height: 100%;
    background:
        linear-gradient(90deg, rgba(0,0,0,0.3), transparent 20%, transparent 80%, rgba(0,0,0,0.5)),
        repeating-linear-gradient(
            to bottom,
            #5b3a1b 0,
            #5b3a1b 12px,
            #6d4821 12px,
            #6d4821 24px
        );
    background-size: cover;
    box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
}

/* Puerta izquierda */
.saloon-door-left {
    border-right: 3px solid #c79a5d;
    transform-origin: left center;
    animation: doorLeftOpen 1.8s forwards ease-out;
}

/* Puerta derecha */
.saloon-door-right {
    border-left: 3px solid #c79a5d;
    transform-origin: right center;
    animation: doorRightOpen 1.8s forwards ease-out;
}

/* Desvanecimiento del overlay al final */
.saloon-overlay.hidden {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.6s ease-out;
}

/* Animaciones */
@keyframes doorLeftOpen {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-100%);
    }
}

@keyframes doorRightOpen {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(100%);
    }
}

.estado-activo {
    background-color: #28a745 !important;  /* verde */
    color: white !important;
    font-weight: bold;
    text-align: center;
}

.estado-averiado {
    background-color: #ffc107 !important;  /* amarillo */
    color: #000 !important;
    font-weight: bold;
    text-align: center;
}

.estado-baja {
    background-color: #dc3545 !important;  /* rojo */
    color: white !important;
    font-weight: bold;
    text-align: center;
}


</style>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!--
    <div style="padding:10px 0; text-align:left;">
    <img src="assets/logo_departamento.png" 
         alt="Logo del departamento" 
         style="height:60px;">
    </div> -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid d-flex align-items-center">
        <img src="assets/logo_departamento.png" 
             alt="Logo" 
             style="height:80px; margin-right:15px;">
        <a class="navbar-brand" href="index.php">Inventario IT</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav" aria-controls="navbarNav"
                aria-expanded="false" aria-label="Alternar navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Equipos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redes.php">Control de IPs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redes_gestion.php">Gestión de redes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="averias_list.php">Gestión de averías</a>
                </li>
                <?php if ($_SESSION['rol'] === 'admin'): ?>
    <a class="nav-link" href="actividad_logs.php">logs</a>
<?php endif; ?>
                <?php if (!empty($_SESSION['tip'])): ?>
    <span class="navbar-text me-3">
        <?= htmlspecialchars($_SESSION['tip']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>)
    </span>
    <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
<?php endif; ?>

            </ul>
        </div>
    </div>
</nav>

<div class="container mb-5">
