<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';
if (current_user()) audit('user.logout', 'user', (int) current_user()['id']);
$_SESSION = [];
if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']); }
session_destroy();
header('Location: ' . url('index.php'));
exit;
