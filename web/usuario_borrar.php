<?php
require_once 'config.php';
require_once 'includes/header.php';
// require_once 'auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de usuario no válido.");
}

// Opción: impedir que te borres a ti mismo o que borres el último admin, etc.
// if ($_SESSION['usuario_id'] == $id) { die("No puedes borrarte a ti mismo."); }

$stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_catalogos.php?tab=usuarios");
exit;
