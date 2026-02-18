<?php
require_once 'config.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de sección no válido.");

// Aquí podrías comprobar si hay equipos usando esa sección
$stmt = $pdo->prepare("DELETE FROM secciones WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_catalogos.php?tab=secciones");
exit;
