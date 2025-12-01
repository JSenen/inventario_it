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
.estado-almacen {
    background-color: #959797ff !important;  /* azul */
    color: white !important;
    font-weight: bold;
    text-align: center;

}
.estado-prestado {
    background-color: #17a2b8 !important;  /* cyan */
    color: white !important;
    font-weight: bold;
    text-align: center;
}

.custom-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 220px;
    display: none;
    padding: 0.25rem 0;
    z-index: 1050;
}

.custom-dropdown-menu .dropdown-item {
    display: block;
    width: 100%;
    padding: 0.25rem 1.0rem;
    clear: both;
    text-align: left;
    white-space: nowrap;
    text-decoration: none;
    background-color: transparent;
}

.custom-dropdown-menu .dropdown-item:hover {
    background-color: rgba(255,255,255,0.1);
}

/* Clase que muestra el menú */
.custom-dropdown-menu.show {
    display: block;
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

        <!-- Bootstrap 5: data-bs-toggle / data-bs-target -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Alternar navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Menú principal -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Equipos -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php">👨🏽‍💻 Equipos</a>
                </li>

                <!-- Redes (submenu) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                        🛜 Redes
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="redes.php">Control IPs</a></li>
                        <li><a class="dropdown-item" href="redes_gestion.php">Gestion Redes</a></li>
                        <li><a class="dropdown-item" href="buscar_ip.php">Buscar IP</a></li> 

                    </ul>
                </li>

                 <!-- Moviles / SIMS -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                     📱 Móviles / SIMS
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="telefonos.php">Teléfonos</a></li>
                        <li><a class="dropdown-item" href="sims.php">Tarjetas SIM</a></li>
                       
                    </ul>
                </li>

                <!-- Material / Fungibles -->
                <li class="nav-item">
                    <a class="nav-link" href="materiales.php">
                        📦 Material / Fungibles
                    </a>
                </li>

                <!-- Averías -->
                <li class="nav-item">
                    <a class="nav-link" href="averias_list.php">⚠️ Gestión de averías</a>
                </li>
                <!-- Administración (submenu) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                        Administración
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="admin_catalogos.php?tab=usuarios">Usuarios</a></li>
                        <li><a class="dropdown-item" href="admin_catalogos.php?tab=Puestos">Puestos</a></li>
                         <li><a class="dropdown-item" href="admin_catalogos.php?tab=ubicaciones">Ubicaciones</a></li>
                        <li><a class="dropdown-item" href="admin_catalogos.php?tab=departamentos">Departamentos</a></li>
                        <li><a class="dropdown-item" href="admin_catalogos.php?tab=tipos">Tipos de equipo</a></li>
                        <li><a class="dropdown-item" href="admin_catalogos.php?tab=servicio">Servicio</a></li>
                        <li><a class="dropdown-item" href="admin_catalogos.php?tab=secciones">Secciones</a></li>
                    </ul>
                </li>

                <!-- Logs solo para admin -->   
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="actividad_logs.php">📝 Logs</a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Info de usuario + botón Salir -->
            <?php if (!empty($_SESSION['tip'])): ?>
                <span class="navbar-text me-3">
                    <?= htmlspecialchars($_SESSION['tip']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>)
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    Salir
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>



<div class="container mb-5">
