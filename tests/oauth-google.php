<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = rtrim($argv[1] ?? 'http://localhost/FinMap', '/');
$falhas = [];
$total = 0;
function conferirGoogle(bool $resultado, string $mensagem): void
{
    global $falhas, $total;
    $total++;
    if (!$resultado) $falhas[] = $mensagem;
}
function requisitarGoogleLocal(string $caminho, string $cookie = ''): array
{
    global $base;
    $contexto = stream_context_create(['http' => [
        'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10,
        'header' => $cookie === '' ? '' : 'Cookie: ' . $cookie,
    ]]);
    $corpo = file_get_contents($base . $caminho, false, $contexto);
    $headers = $http_response_header ?? [];
    $location = '';
    foreach ($headers as $header) {
        if (stripos($header, 'Location: ') === 0) $location = substr($header, 10);
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $header, $m)) $cookie = $m[1];
    }
    return [$headers[0] ?? '', $location, $cookie, $corpo ?: ''];
}

foreach (['/config/oauth-google.local.php', '/config/oauth-google.local.php.bak'] as $path) {
    [$status] = requisitarGoogleLocal($path);
    conferirGoogle(strpos($status, ' 403 ') !== false, 'Bloqueio da configuracao local');
}
foreach (['/login/google-callback.php', '/config/google-callback.php'] as $path) {
    [$status, $destino] = requisitarGoogleLocal($path . '?code=teste&state=invalido');
    conferirGoogle(strpos($status, ' 302 ') !== false && $destino === '../login/login.php?erro=google_sessao',
        'Callback sem sessao deve voltar ao login, sem consultar Google ou banco');
}
foreach (['login.php', 'registro.php'] as $pagina) {
    [$status, , , $html] = requisitarGoogleLocal('/login/' . $pagina);
    conferirGoogle(strpos($status, ' 200 ') !== false && strpos($html, 'href="google.php"') !== false,
        'Botao Google deve iniciar o fluxo local: ' . $pagina);
}

// Nao segue o redirecionamento externo: nenhum codigo ou credencial e enviado ao Google.
[$status, $destino, $cookie] = requisitarGoogleLocal('/login/google.php');
parse_str(parse_url($destino, PHP_URL_QUERY) ?? '', $parametros);
if ($destino === 'login.php?erro=google_configuracao') {
    conferirGoogle(strpos($status, ' 302 ') !== false, 'Configuracao ausente deve falhar sem erro fatal');
} else {
    conferirGoogle(strpos($status, ' 302 ') !== false
        && parse_url($destino, PHP_URL_HOST) === 'accounts.google.com'
        && preg_match('/^[a-f0-9]{64}$/', $parametros['state'] ?? '') === 1
        && !isset($parametros['client_secret']), 'Inicio deve conter state e nunca o segredo');
    conferirGoogle(parse_url($parametros['redirect_uri'] ?? '', PHP_URL_PATH) === '/finmap/login/google-callback.php',
        'Retorno local configurado deve apontar para o callback existente');
    $retorno = '/login/google-callback.php?error=access_denied&state=' . urlencode($parametros['state'] ?? '');
    [$status, $destino] = requisitarGoogleLocal($retorno, $cookie);
    conferirGoogle(strpos($status, ' 302 ') !== false && $destino === '../login/login.php?erro=google_cancelado',
        'Cancelamento com state valido deve ser tratado');
    [, $destino] = requisitarGoogleLocal($retorno, $cookie);
    conferirGoogle($destino === '../login/login.php?erro=google_sessao', 'State nao pode ser reutilizado');
}
[, , , $html] = requisitarGoogleLocal('/login/login.php?erro=google_sessao');
conferirGoogle(strpos($html, 'Clique no botão Google novamente.') !== false, 'Erro deve ser mostrado na tela');
foreach ($falhas as $falha) fwrite(STDERR, $falha . PHP_EOL);
echo ($total - count($falhas)) . '/' . $total . " verificacoes OAuth passaram." . PHP_EOL;
exit($falhas ? 1 : 0);
