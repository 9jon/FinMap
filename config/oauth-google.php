<?php
// Credenciais locais ficam fora do Git e bloqueadas para acesso pela web.
$arquivoLocalGoogle = __DIR__ . '/oauth-google.local.php';
if (is_file($arquivoLocalGoogle)) {
    require_once $arquivoLocalGoogle;
}
foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'] as $chaveGoogle) {
    if (!defined($chaveGoogle)) {
        define($chaveGoogle, getenv($chaveGoogle) ?: '');
    }
}

function googleOAuthConfigurado(): bool
{
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '' && GOOGLE_REDIRECT_URI !== '';
}
