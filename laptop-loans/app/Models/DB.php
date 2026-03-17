<?php
namespace App\Models;
use PDO;

class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        global $CONFIG;
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $CONFIG['db']['host'], $CONFIG['db']['port'], $CONFIG['db']['name'], $CONFIG['db']['charset']);
            self::$pdo = new PDO($dsn, $CONFIG['db']['user'], $CONFIG['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}