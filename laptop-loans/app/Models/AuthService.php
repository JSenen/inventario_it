<?php
namespace App\Models;
use PDO;
use App\Services\Audit;
use App\Services\Logger;

class AuthService {
  public static function attempt(string $userOrEmail, string $password): ?array {
    global $CONFIG;
    $auth = $CONFIG['auth'] ?? [];
    $provider = (string)($auth['provider'] ?? 'inventario_it');

    $user = null;
    if ($provider === 'inventario_it') {
      $user = self::attemptInventarioIt($userOrEmail, $password);
    } elseif ($provider === 'helpdesk') {
      $user = self::attemptHelpdesk($userOrEmail, $password);
    } else { // auto
      $user = self::attemptInventarioIt($userOrEmail, $password);
      if ($user === null) {
        $user = self::attemptHelpdesk($userOrEmail, $password);
      }
    }

    if (!$user) {
      Audit::loginFailed($_POST['user'] ?? $userOrEmail);
      return null;
    }

    $_SESSION['user'] = $user;
    session_regenerate_id(true);
    Audit::loginSuccess($_SESSION['user']['username'] ?? '');
    return $_SESSION['user'];
  }

  public static function logout(): void {
    unset($_SESSION['user']);
    Audit::logout();
    session_regenerate_id(true);
  }

  private static function attemptInventarioIt(string $userOrEmail, string $password): ?array
  {
    global $CONFIG;
    $cfg = $CONFIG['auth']['inventario_it'] ?? null;
    if (!is_array($cfg)) {
      return null;
    }

    try {
      $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      $t = $cfg['table'];
      $f = $cfg['fields'];

      $sql = "SELECT * FROM {$t} WHERE {$f['username']} = ? LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([trim($userOrEmail)]);
      $row = $st->fetch();
      if (!$row) {
        return null;
      }

      $hash = hash('sha3-256', $password);
      if (!hash_equals((string)$row[$f['password']], $hash)) {
        return null;
      }

      $roleField = (string)($f['role'] ?? 'rol');
      $role = (string)($row[$roleField] ?? 'usuario');

      return [
        'id'       => $row[$f['id']] ?? null,
        'username' => (string)($row[$f['username']] ?? $userOrEmail),
        'email'    => null,
        'name'     => (string)($row[$f['username']] ?? $userOrEmail),
        'isadmin'  => $role === 'admin' ? 1 : 0,
      ];
    } catch (\Throwable $e) {
      Logger::warning('Auth inventario_it no disponible: {msg}', ['msg' => $e->getMessage()]);
      return null;
    }
  }

  private static function attemptHelpdesk(string $userOrEmail, string $password): ?array
  {
    global $CONFIG;
    $cfg = $CONFIG['auth']['helpdesk'] ?? null;
    if (!is_array($cfg)) {
      return null;
    }

    try {
      $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);

      $t = $cfg['table'];
      $f = $cfg['fields'];

      $sql = "SELECT * FROM {$t} WHERE {$f['username']}=? OR {$f['email']}=? LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([$userOrEmail, $userOrEmail]);
      $row = $st->fetch();
      if (!$row) return null;

      if (!password_verify($password, (string)$row[$f['password']])) {
        return null;
      }

      if (($cfg['only_admins'] ?? false) && !empty($f['isadmin']) && (int)$row[$f['isadmin']] !== 1) {
        return null;
      }

      return [
        'id'       => $row[$f['id']],
        'username' => $row[$f['username']],
        'email'    => $row[$f['email']],
        'name'     => $row[$f['name']] ?: $row[$f['username']],
        'isadmin'  => !empty($f['isadmin']) ? (int)$row[$f['isadmin']] : 0,
      ];
    } catch (\Throwable $e) {
      Logger::warning('Auth helpdesk no disponible: {msg}', ['msg' => $e->getMessage()]);
      return null;
    }
  }
}
