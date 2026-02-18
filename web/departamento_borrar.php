<?php
require_once 'config.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID de departamento no válido.");

// Aquí podrías comprobar si hay equipos usando ese departamento
$stmt = $pdo->prepare("DELETE FROM departamentos WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_catalogos.php?tab=departamentos");
exit;
