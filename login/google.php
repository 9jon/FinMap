<?php
session_start();
require_once __DIR__ . '/../config/oauth-google.php';
header('Cache-Control: no-store');

if (!googleOAuthConfigurado()) {
    header('Location: login.php?erro=google_configuracao');
    exit;
}

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(32));
$_SESSION['google_oauth_expira'] = time() + 600;
$parametros = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'state' => $_SESSION['google_oauth_state'],
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parametros));
exit;
