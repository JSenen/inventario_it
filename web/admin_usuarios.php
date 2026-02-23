<?php
require_once 'config.php';
require_once 'includes/header.php';

// Solo admin
// if ($_SESSION['rol'] !== 'admin') { die("Acceso restringido"); }

$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Gestión de usuarios</h2>

    <div class="d-flex justify-content-between my-3">
        <h4>Usuarios registrados</h4>
        <a href="usuario_nuevo.php" class="btn btn-primary btn-sm">Añadir usuario</a>
    </div>

    <table class="table table-striped table-sm" id="tablaUsuarios">
        <thead>
        <tr>
            <th>ID</th>
            <th>TIP</th>
            <th>Rol</th>
            <th>Creado en</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['tip']) ?></td>
                <td><?= htmlspecialchars($u['rol']) ?></td>
                <td><?= $u['creado_en'] ?></td>
                <td>
                    <a href="usuario_editar.php?id=<?= $u['id'] ?>"
                       class="btn btn-warning btn-sm">Editar</a>

                    <a href="usuario_borrar.php?id=<?= $u['id'] ?>"
                       data-confirm-message="¿Seguro que quieres borrar este usuario?"
                       class="btn btn-danger btn-sm">Borrar</a>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
