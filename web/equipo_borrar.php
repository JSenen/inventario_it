<?php
// equipo_borrar.php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Borramos el equipo. Las IPs asociadas se eliminan por la FK ON DELETE CASCADE.
$stmt = $pdo->prepare("DELETE FROM equipos WHERE id = :id");
$stmt->execute([':id' => $id]);

header('Location: index.php?msg=ok');
exit;
