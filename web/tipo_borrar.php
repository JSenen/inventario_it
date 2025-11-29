<?php
require_once 'config.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de tipo no válido.");

// Aquí podrías comprobar si hay equipos usando ese tipo
$stmt = $pdo->prepare("DELETE FROM tipos_equipo WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_catalogos.php?tab=tipos");
exit;
