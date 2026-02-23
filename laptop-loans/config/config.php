<?php

define('DEBUG_PDF', true);

$configFile = __DIR__ . '/config.local.php';
if (!file_exists($configFile)) $configFile = __DIR__ . '/config.example.php';
$CONFIG = require $configFile;

$CONFIG['auth'] = $CONFIG['auth'] ?? [];
$CONFIG['auth']['provider'] = $CONFIG['auth']['provider'] ?? 'inventario_it'; // inventario_it|helpdesk|auto

// Proveedor inventario_it (TIP + SHA3-256)
$dbCfg = $CONFIG['db'] ?? [];
$CONFIG['auth']['inventario_it'] = $CONFIG['auth']['inventario_it'] ?? [
  'dsn'  => sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    (string)($dbCfg['host'] ?? 'db'),
    (int)($dbCfg['port'] ?? 3306),
    'inventario_it',
    (string)($dbCfg['charset'] ?? 'utf8mb4')
  ),
  'user' => (string)($dbCfg['user'] ?? ''),
  'pass' => (string)($dbCfg['pass'] ?? ''),
  'table'  => 'usuarios',
  'fields' => [
    'id'       => 'id',
    'username' => 'tip',
    'password' => 'password_hash',
    'role'     => 'rol',
  ],
];

// Proveedor helpdesk (opcional)
$CONFIG['auth']['helpdesk'] = $CONFIG['auth']['helpdesk'] ?? [
  'dsn'  => 'mysql:host=127.0.0.1;dbname=Hesk;charset=utf8mb4',
  'user' => 'gati',
  'pass' => '3-Aminavana',
  'table'  => 'hesk_users',
  'fields' => [
    'id'       => 'id',
    'username' => 'user',
    'email'    => 'email',
    'password' => 'pass',
    'name'     => 'name',
    'isadmin'  => 'isadmin',
  ],
  'password_strategy' => 'password_hash',
  'only_admins' => false,
];

//****************** LOGS ************************************************************* */
$CONFIG['log'] = [
  'dir'       => BASE_PATH . '/storage/logs', // carpeta de logs
  'min_level' => 'debug',                     // debug|info|warning|error
];

