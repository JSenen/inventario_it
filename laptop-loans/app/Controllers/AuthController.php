<?php
namespace App\Controllers;
use App\Models\AuthService;
use App\Services\Audit;


class AuthController {
  public function login() {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
      csrf_check();
      $u = trim($_POST['user'] ?? '');
      $p = (string)($_POST['pass'] ?? '');
      if (!$u || !$p || !AuthService::attempt($u,$p)) {
        $error = 'Credenciales inválidas';
        return view('auth/login', compact('error'));
      }
      header('Location: ' . url('dashboard/index')); exit;
    }
    return view('auth/login');
  }
  public function logout() {
    AuthService::logout();
    header('Location: ' . url('auth/login')); exit;
  }
}
