<?php
namespace App\Models;
use PDO;
use App\Services\Audit;

class AuthService {
  public static function attempt(string $userOrEmail, string $password): ?array {
    global $CONFIG;
    $cfg = $CONFIG['auth']['helpdesk'];

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

    // contraseña (bcrypt)
    if (!password_verify($password, (string)$row[$f['password']])) {
       
        
        return null;
    } 

    // solo admins (opcional)
    if (($cfg['only_admins'] ?? false) && !empty($f['isadmin']) && (int)$row[$f['isadmin']] !== 1) {
      Audit::loginFailed($_POST['user'] ?? '');
      return null;
    }

    $_SESSION['user'] = [
      'id'       => $row[$f['id']],
      'username' => $row[$f['username']],
      'email'    => $row[$f['email']],
      'name'     => $row[$f['name']] ?: $row[$f['username']],
      'isadmin'  => !empty($f['isadmin']) ? (int)$row[$f['isadmin']] : 0,
    ];
    session_regenerate_id(true);
    Audit::loginSuccess($_SESSION['user']['username'] ?? '');
    return $_SESSION['user'];
  }

  public static function logout(): void {
    unset($_SESSION['user']);
    Audit::logout();
    session_regenerate_id(true);
  }
}
