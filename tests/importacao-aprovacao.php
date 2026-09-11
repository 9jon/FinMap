<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ob_start();
require __DIR__ . '/../config/conn.php';
ob_end_clean();
// Tabelas temporárias isolam todos os efeitos dos dados reais.
$conn->query('CREATE TEMPORARY TABLE usuarios (id INT PRIMARY KEY, saldo_total DECIMAL(12,2))');
$conn->query('CREATE TEMPORARY TABLE transacoes (
 id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT, importacao_id INT, categoria_id INT NULL,
 descricao VARCHAR(150), valor DECIMAL(12,2), tipo VARCHAR(20), origem VARCHAR(20), status VARCHAR(20),
 confianca_percentual INT, observacao_captura VARCHAR(255), data_transacao DATE, hash_importacao VARCHAR(64))');
$conn->query('INSERT INTO usuarios VALUES (1, 1000)');
$usuarioId = $usuario_id = 1;
$importacaoId = 1;
$extensao = 'csv';
$normalizadas = [];
foreach ([['despesa', date('Y-m-d'), 100], ['despesa', '2099-01-01', 200], ['receita', date('Y-m-d'), 300]] as $i => [$tipo, $data, $valor]) {
    $normalizadas[] = ['hash' => (string) $i, 'categoria_id' => null, 'descricao' => 'Teste',
        'valor' => $valor, 'tipo' => $tipo, 'confianca' => 50, 'observacao' => 'CSV', 'data' => $data];
}
function trecho(string $fonte, string $inicio, string $fim): string {
    $pos = strpos($fonte, $inicio);
    if ($pos === false || ($final = strpos($fonte, $fim, $pos)) === false) throw new RuntimeException('Trecho não encontrado');
    return substr($fonte, $pos, $final - $pos);
}
function conferir(bool $ok, string $mensagem): void {
    if (!$ok) throw new RuntimeException($mensagem);
}
function saldo(): float {
    global $conn;
    return (float) $conn->query('SELECT saldo_total FROM usuarios WHERE id = 1')->fetch_row()[0];
}
$importacao = file_get_contents(__DIR__ . '/../pages/importar-transacoes.php');
$executarImportacao = trecho($importacao, '    $stmtDuplicado =', '    $stmtAtualizar =');
eval($executarImportacao);
conferir(saldo() === 900.0 && $aprovadas === 1, 'Despesa CSV deve debitar automaticamente');
conferir($conn->query("SELECT COUNT(*) FROM transacoes WHERE status = 'pendente'")->fetch_row()[0] == 2, 'Receita e data futura permanecem pendentes');
conferir($conn->query("SELECT observacao_captura FROM transacoes WHERE id = 2")->fetch_row()[0] === 'Fatura agendada', 'Data futura agendada');
eval($executarImportacao);
conferir(saldo() === 900.0 && $duplicadas === 3, 'Reimportação não duplica débito');
$revisao = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../pages/revisar-acao.php'));
eval(trecho($revisao, 'function aplicarVariacaoNoSaldo', '$conn->begin_transaction();'));
$executarAcao = trecho($revisao, '$conn->begin_transaction();', "if (\$sucesso) {\n    \$conn->commit();");
function agir(string $acao, int $id, array $dados = []): void {
    global $conn, $usuario_id, $executarAcao;
    eval($executarAcao);
    conferir($sucesso, 'Ação deve ter sucesso');
    $conn->commit();
}
agir('editar', 1, ['descricao' => 'Editado', 'valor' => 150, 'data_transacao' => date('Y-m-d')]);
conferir(saldo() === 850.0, 'Editar aprovado ajusta saldo');
agir('excluir', 1);
conferir(saldo() === 1000.0, 'Excluir aprovado reverte saldo');
agir('excluir', 2);
conferir(saldo() === 1000.0, 'Excluir agendado não altera saldo');
agir('aprovar', 3);
agir('aprovar', 3);
conferir(saldo() === 1300.0, 'Aprovar duas vezes não duplica efeito');
agir('editar', 3, ['descricao' => 'Futuro', 'valor' => 350, 'data_transacao' => '2099-01-01']);
conferir(saldo() === 1000.0, 'Mover aprovado para futuro reverte saldo');
echo "Importação, duplicação, edição, agendamento e exclusão verificados em tabelas temporárias.\n";
