<?php
declare(strict_types=1);

session_start();
$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['csrf_conta'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    exit('Requisição inválida.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php', true, 302);
exit;
