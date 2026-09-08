<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Execute: php tests/autenticacao.php [http://localhost/FinMap]
$baseUrl = rtrim($argv[1] ?? 'http://localhost/FinMap', '/');
$falhas = [];
$total = 0;
function verificar(bool $condicao, string $descricao): void
{
    global $falhas, $total;
    $total++;
    if (!$condicao) {
        $falhas[] = $descricao;
    }
}

$json = ['atualizar-saldo', 'atualizar-poupanca-config', 'atualizar-regras-revisao',
    'buscar-gastos-categoria', 'revisar-acao', 'marcar-notificacao', 'buscar-notificacao'];
$paginas = ['dashboard', 'config-renda', 'fonte-dados', 'orcamento-mensal',
    'metas-financeiras', 'alertas-preditivos', 'poupanca-invisivel',
    'revisar-lancamentos', 'criar-transacao', 'importar-transacoes'];
foreach (array_merge($json, $paginas) as $pagina) {
    foreach (['GET', 'POST'] as $metodo) {
        $contexto = stream_context_create(['http' => [
            'method' => $metodo, 'follow_location' => 0, 'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $corpo = file_get_contents($baseUrl . '/pages/' . $pagina . '.php', false, $contexto);
        $cabecalhos = $http_response_header ?? [];
        $status = $cabecalhos[0] ?? '';
        if (in_array($pagina, $json, true)) {
            $dados = json_decode($corpo ?: '', true);
            verificar(strpos($status, ' 401 ') !== false && ($dados['sucesso'] ?? null) === false,
                "$metodo $pagina deve retornar JSON 401 sem login");
        } else {
            verificar(strpos($status, ' 302 ') !== false
                && in_array('Location: ../login/login.php', $cabecalhos, true),
                "$metodo $pagina deve redirecionar ao login");
        }
    }
}

// Registros antigos e rotacionados nao devem ser disponibilizados pela web.
foreach (['_debug_criar_transacao.log', '_debug_criar_transacao.log.1'] as $log) {
    $contexto = stream_context_create(['http' => [
        'method' => 'HEAD', 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10,
    ]]);
    file_get_contents($baseUrl . '/pages/' . $log, false, $contexto);
    verificar(strpos($http_response_header[0] ?? '', ' 403 ') !== false,
        'Download de log deve ser bloqueado: ' . $log);
}

// Sessoes sinteticas em processos isolados; nao acessam contas nem o banco.
$helper = realpath(__DIR__ . '/../includes/autenticacao.php');
foreach ([
    [[], false],
    [['usuario_id' => 1], false],
    [['usuario_id' => 1, 'autenticado' => false], false],
    [['usuario_id' => 0, 'autenticado' => true], false],
    [['usuario_id' => -1, 'autenticado' => true], false],
    [['usuario_id' => 'invalido', 'autenticado' => true], false],
    [['usuario_id' => 42, 'autenticado' => true], true],
    [['usuario_id' => '73', 'autenticado' => true], true],
] as [$sessao, $permitida]) {
    $codigo = 'session_start(); $_SESSION = ' . var_export($sessao, true)
        . '; require ' . var_export($helper, true)
        . '; $id = exigirUsuarioAutenticado(true); session_destroy(); echo "permitido:" . $id;';
    $processo = proc_open([PHP_BINARY, '-d', 'session.save_path=' . sys_get_temp_dir(), '-r', $codigo], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $saida = stream_get_contents($pipes[1]);
    $erro = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $resultado = proc_close($processo);
    $esperado = $permitida ? 'permitido:' . $sessao['usuario_id'] : null;
    verificar($resultado === 0 && $erro === '' && ($permitida
        ? $saida === $esperado
        : (json_decode($saida, true)['sucesso'] ?? null) === false),
        'Validacao de sessao: ' . json_encode($sessao) . ' ' . $erro);
}

foreach ($falhas as $falha) {
    fwrite(STDERR, $falha . PHP_EOL);
}
echo ($total - count($falhas)) . '/' . $total . " verificacoes passaram." . PHP_EOL;
exit($falhas ? 1 : 0);
