<?php
namespace App\Services;

class Logger {
  private static string $dir;
  private static string $min = 'debug';
  private static array $prio = ['debug'=>0,'info'=>1,'warning'=>2,'error'=>3];

  public static function init(string $dir, string $minLevel='debug'): void {
    self::$dir = rtrim($dir, '/\\');
    self::$min = strtolower($minLevel);
    if (!is_dir(self::$dir)) { @mkdir(self::$dir, 0775, true); }
  }

  public static function currentFile(): string {
    return self::$dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
  }

  public static function log(string $level, string $msg, array $ctx=[]): void {
    $level = strtolower($level);
    if (self::$prio[$level] < self::$prio[self::$min]) return;

    // interpolación {clave}
    foreach ($ctx as $k=>$v) {
      if (is_scalar($v)) { $msg = str_replace('{'.$k.'}', (string)$v, $msg); }
    }
    $line = sprintf(
      "[%s] %-7s %s %s\n",
      date('Y-m-d H:i:s'),
      strtoupper($level),
      $msg,
      $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents(self::currentFile(), $line, FILE_APPEND);
  }

  public static function debug($m, array $c=[]){ self::log('debug',$m,$c); }
  public static function info($m, array $c=[]){ self::log('info',$m,$c); }
  public static function warning($m, array $c=[]){ self::log('warning',$m,$c); }
  public static function error($m, array $c=[]){ self::log('error',$m,$c); }
}
