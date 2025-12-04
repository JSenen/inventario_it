<?php

// Ruta absoluta a la carpeta raíz del proyecto (web/)
define('BASE_PATH', dirname(__FILE__));

// Rutas comunes
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH',   BASE_PATH . '/assets');
define('UPLOADS_PATH',  BASE_PATH . '/uploads');

// Configuración de la base de datos
$host = "db"; // nombre del servicio docker
$dbname = "inventario_it";
$user = "inventario_user";
$pass = "InventarioPass123!";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
