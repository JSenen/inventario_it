<?php
require_once 'config.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de servicio no válido.");

// Aquí podrías comprobar si hay equipos usando ese tipo
$stmt = $pdo->prepare("DELETE FROM tipos_servicio WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_catalogos.php?tab=servicios");
exit;
