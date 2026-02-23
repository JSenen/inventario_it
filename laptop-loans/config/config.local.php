<?php
// Copy this to config.php and edit credentials
return [
  'db' => [
    'host' => 'db',
    'port' => 3306,
    'name' => 'laptop_loans',
    'user' => 'inventario_user',
    'pass' => 'InventarioPass123!',
    'charset' => 'utf8mb4',
  ],
  'app' => [
    'name' => 'Gestor de Portátiles',
    'base_url' => '/laptop-loans/public',  // adjust if in subdir
    'app' => ['base_url' => '/laptop-loans/public']

  ],
];
