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
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
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
            </ul>
        </div>
    </div>
</nav>

<div class="container mb-5">
