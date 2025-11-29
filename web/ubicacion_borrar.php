<?php
require_once 'config.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de ubicación no válido.");

// Aquí podrías comprobar si hay equipos usando esa ubicación
$stmt = $pdo->prepare("DELETE FROM ubicaciones WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_catalogos.php?tab=ubicaciones");
exit;
