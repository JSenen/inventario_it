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



// Simple router (?r=controller/action)
$r = $_GET['r'] ?? 'dashboard/index';
list($controller, $action) = array_pad(explode('/', $r), 2, 'index');

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


