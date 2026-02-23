<?php
namespace App\Services;

class Audit {
  private static function who(): array {
    $u = $_SESSION['user'] ?? null;
    return [
      'user_id'   => $u['id'] ?? null,
      'username'  => $u['username'] ?? ($u['name'] ?? 'anon'),
      'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
      'route'     => $_GET['r'] ?? 'dashboard/index',
    ];
  }

  private static function write(string $action, array $extra=[]): void {
    $ctx = array_merge(self::who(), $extra);
    // Queda así en el fichero: [fecha] INFO  AUDIT action=... user=... route=...
    \App\Services\Logger::info('AUDIT ' . $action, $ctx);
  }

  // Helpers rápidos
  public static function loginSuccess(string $user){ self::write('login_success', ['login'=>$user]); }
  public static function loginFailed(string $user){ self::write('login_failed',  ['login'=>$user]); }
  public static function logout(){ self::write('logout'); }

  public static function create(string $entity, int $id, array $fields=[]){
    self::write('create', ['entity'=>$entity,'id'=>$id,'data'=>$fields]);
  }
  public static function update(string $entity, int $id, array $changes){
    self::write('update', ['entity'=>$entity,'id'=>$id,'changes'=>$changes]);
  }
  public static function delete(string $entity, int $id, array $was=[]){
    self::write('delete', ['entity'=>$entity,'id'=>$id,'was'=>$was]);
  }

  public static function entrega(int $handoverId, string $serie, int $personId, ?int $courseId){
    self::write('entrega', ['handover_id'=>$handoverId,'serie'=>$serie,'person_id'=>$personId,'course_id'=>$courseId]);
  }
  public static function devolucion(int $handoverId, string $serie, int $personId, ?int $courseId){
    self::write('devolucion', ['handover_id'=>$handoverId,'serie'=>$serie,'person_id'=>$personId,'course_id'=>$courseId]);
  }
}
