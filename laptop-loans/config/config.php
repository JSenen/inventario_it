<?php

define('DEBUG_PDF', true);

$configFile = __DIR__ . '/config.local.php';
if (!file_exists($configFile)) $configFile = __DIR__ . '/config.example.php';
$CONFIG = require $configFile;



$CONFIG['auth'] = [
  'provider' => 'helpdesk',
  'helpdesk' => [
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
      'isadmin'  => 'isadmin',      // opcional, para roles
    ],

    'password_strategy' => 'password_hash', // bcrypt ($2y$)
    'only_admins' => false,                 // pon true solo permitir isadmin=1
  ],
];

//****************** LOGS ************************************************************* */
$CONFIG['log'] = [
  'dir'       => BASE_PATH . '/storage/logs', // carpeta de logs
  'min_level' => 'debug',                     // debug|info|warning|error
];


