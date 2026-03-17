<?php
declare(strict_types=1);
session_start();

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\PeopleController;
use App\Controllers\LaptopsController;
use App\Controllers\CoursesController;
use App\Controllers\HandoversController;
use App\Controllers\ReceiptsController;
use App\Controllers\ExportsController;
use App\Controllers\LocationsController;

/**
 * Si venimos desde inventario_it con sesión activa (TIP), creamos sesión local SSO.
 */
if (empty($_SESSION['user']) && !empty($_SESSION['tip'])) {
  $tip = trim((string)$_SESSION['tip']);
  if ($tip !== '') {
    $ssoUser = [
      'id'       => 'tip:' . $tip,
      'username' => $tip,
      'email'    => null,
      'name'     => $tip,
      'isadmin'  => 0,
      'source'   => 'inventario_it_sso',
    ];

    // Enriquecer con rol real de inventario_it si existe acceso a esa BD.
    try {
      global $CONFIG;
      $db = $CONFIG['db'] ?? [];
      $dsnInv = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string)($db['host'] ?? 'db'),
        (int)($db['port'] ?? 3306),
        'inventario_it',
        (string)($db['charset'] ?? 'utf8mb4')
      );
      $pdoInv = new PDO(
        $dsnInv,
        (string)($db['user'] ?? ''),
        (string)($db['pass'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
      );
      $stInv = $pdoInv->prepare("SELECT tip, rol FROM usuarios WHERE tip = ? LIMIT 1");
      $stInv->execute([$tip]);
      $invUser = $stInv->fetch();
      if ($invUser) {
        $ssoUser['name'] = (string)($invUser['tip'] ?? $tip);
        $ssoUser['isadmin'] = (($invUser['rol'] ?? 'usuario') === 'admin') ? 1 : 0;
      }
    } catch (\Throwable $e) {
      // No bloqueamos SSO si no se puede consultar inventario_it.
    }

    $_SESSION['user'] = $ssoUser;
  }
}

// Simple router (?r=controller/action)
$r = $_GET['r'] ?? 'dashboard/index';
list($controller, $action) = array_pad(explode('/', $r), 2, 'index');

// Rutas públicas (sin sesión)
$isPublicRoute = ($controller === 'auth' && in_array($action, ['login', 'logout'], true));
if (empty($_SESSION['user']) && !$isPublicRoute) {
  header('Location: ' . url('auth/login'));
  exit;
}

\App\Services\Logger::info('AUDIT visit', [
  'user_id'  => $_SESSION['user']['id'] ?? null,
  'username' => $_SESSION['user']['username'] ?? ($_SESSION['user']['name'] ?? 'anon'),
  'route'    => "$controller/$action",
  'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
]);


$map = [
  'auth'      => AuthController::class,
  'people'    => PeopleController::class,
  'laptops'   => LaptopsController::class,
  'courses'   => CoursesController::class,
  'handovers' => HandoversController::class,
  'dashboard' => HandoversController::class,
  'receipts'  => ReceiptsController::class,
  'exports'   => ExportsController::class,
  'locations' => LocationsController::class,
  'receipts' => \App\Controllers\ReceiptsController::class,

];

if (!isset($map[$controller])) { http_response_code(404); exit('404'); }

$ctrl = new $map[$controller]();
if (!method_exists($ctrl, $action)) { http_response_code(404); exit('404'); }

echo $ctrl->$action();

