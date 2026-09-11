<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/importador-transacoes.php';
date_default_timezone_set('America/Sao_Paulo');
function verificarCsv(bool $ok, string $mensagem): void {
    if (!$ok) throw new RuntimeException($mensagem);
}
function normalizarCsv(array $linhas): array {
    return array_map(static fn($linha) => importacaoNormalizarLinha($linha, ['despesa' => [], 'receita' => []], []), importacaoLinhasTabulares($linhas, 'CSV'));
}
$simples = normalizarCsv([['Despesa', 'Valor'], ['Aluguel', '1800.00']]);
verificarCsv(count($simples) === 1 && $simples[0]['descricao'] === 'Aluguel' && $simples[0]['valor'] === 1800.0, 'Reconhecer Despesa,Valor');
verificarCsv($simples[0]['data'] === date('Y-m-d'), 'Data ausente usa hoje');
$datadas = normalizarCsv([['Data', 'Despesa', 'Valor'], ['15/08/2026', 'Internet', '120'], ['2099-01-20', 'Aluguel', '1800']]);
verificarCsv($datadas[0]['data'] === '2026-08-15' && $datadas[1]['data'] === '2099-01-20', 'Preservar datas passadas e futuras');
$extrato = normalizarCsv([['Data', 'Descricao', 'Despesa', 'Valor'], ['11/09/2026', 'Mercado', '50', '999']]);
verificarCsv($extrato[0]['valor'] === 50.0, 'Preservar coluna monetária Despesa quando há descrição');
if (isset($argv[1])) {
    $linhas = importacaoLerCsv($argv[1]);
    foreach ($linhas as $linha) importacaoNormalizarLinha($linha, ['despesa' => [], 'receita' => []], []);
    echo count($linhas) . " linhas do arquivo validado com sucesso.\n";
}
echo "Testes de cabeçalhos e datas CSV passaram.\n";
