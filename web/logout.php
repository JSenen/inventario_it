<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

if (!empty($_SESSION['tip'])) {
    logActividad(
        $pdo,
        'LOGOUT',
        'Cierre de sesión',
        ['modulo' => 'AUTH', 'nivel' => 'INFO']
    );
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
