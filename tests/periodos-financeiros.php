<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ob_start();
require __DIR__ . '/../config/conn.php';
ob_end_clean();

// Estas tabelas existem apenas nesta conexao e ocultam as reais durante o teste.
// Se a criacao falhar, a excecao encerra o teste antes de qualquer INSERT.
$conn->query('CREATE TEMPORARY TABLE transacoes (
    id INT PRIMARY KEY, usuario_id INT, descricao VARCHAR(150), valor DECIMAL(12,2),
    tipo VARCHAR(20), categoria_id INT NULL, data_transacao DATE, origem VARCHAR(20),
    status VARCHAR(20), atualizado_em TIMESTAMP)');
$conn->query("CREATE TEMPORARY TABLE categorias (
    id INT PRIMARY KEY, usuario_id INT, nome VARCHAR(100), tipo VARCHAR(20),
    icone VARCHAR(50) DEFAULT 'tag', cor VARCHAR(30) DEFAULT 'green', ativo_no_orcamento TINYINT DEFAULT 1)");
$conn->query("SET timestamp = UNIX_TIMESTAMP('2026-09-08 12:00:00')");
$conn->query("INSERT INTO categorias (id, usuario_id, nome, tipo) VALUES (1, 1, 'Teste', 'despesa')");
$conn->query("INSERT INTO transacoes
    (id, usuario_id, descricao, valor, tipo, categoria_id, data_transacao, origem, status, atualizado_em) VALUES
    (1, 1, 'Importado agosto', 100, 'despesa', 1, '2026-08-31', 'importacao', 'aprovado', '2026-09-08'),
    (2, 1, 'Importado setembro', 50, 'despesa', 1, '2026-09-01', 'importacao', 'aprovado', '2026-09-08'),
    (3, 1, 'Manual setembro', 25, 'despesa', NULL, '2026-09-02', 'manual', 'aprovado', '2026-09-08'),
    (4, 1, 'Receita setembro', 200, 'receita', NULL, '2026-09-01', 'importacao', 'aprovado', '2026-09-08'),
    (5, 1, 'Receita agosto', 300, 'receita', NULL, '2026-08-01', 'manual', 'aprovado', '2026-09-08'),
    (6, 1, 'Pendente', 999, 'despesa', 1, '2026-09-02', 'importacao', 'pendente', '2026-09-08'),
    (7, 2, 'Outro usuario', 999, 'despesa', NULL, '2026-09-02', 'manual', 'aprovado', '2026-09-08'),
    (8, 1, 'Proximo mes', 999, 'despesa', 1, '2026-10-01', 'manual', 'aprovado', '2026-09-08')");

// Executa as consultas presentes nas telas, sem executar as paginas nem seus efeitos.
$consultas = [];
foreach (['dashboard', 'orcamento-mensal', 'poupanca-invisivel', 'alertas-preditivos'] as $pagina) {
    $fonte = file_get_contents(__DIR__ . '/../pages/' . $pagina . '.php');
    foreach (token_get_all($fonte) as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        $sql = stripcslashes(substr($token[1], 1, -1));
        if (strpos($sql, 'SELECT') === false || strpos($sql, 'transacoes') === false
            || strpos($sql, 'DATE_FORMAT') === false) continue;
        $consultas[$pagina . ':' . count($consultas)] = $sql;
    }
}
$total = 0;
function conferirPeriodo(bool $ok, string $mensagem): void
{
    global $total;
    $total++;
    if (!$ok) throw new RuntimeException($mensagem);
}
function consultarPeriodo(string $sql): array
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $id = 1;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // A descricao editada pode mudar; a selecao do periodo e os valores nao.
    foreach ($linhas as &$linha) unset($linha['descricao']);
    return $linhas;
}
conferirPeriodo(count($consultas) === 6, 'Cobertura das seis consultas mensais');
$antes = [];
foreach ($consultas as $nome => $sql) $antes[$nome] = consultarPeriodo($sql);
$mensal = reset($antes)[0];
conferirPeriodo((float)$mensal['despesas_atual'] === 75.0, 'Despesas de setembro');
conferirPeriodo((float)$mensal['despesas_anterior'] === 100.0, 'Importacao antiga fica em agosto');
conferirPeriodo((float)$mensal['receitas_atual'] === 200.0, 'Receitas de setembro');
conferirPeriodo((float)$mensal['receitas_anterior'] === 300.0, 'Receitas de agosto');

$conn->query("UPDATE transacoes SET descricao = 'Descricao editada', atualizado_em = '2026-10-03' WHERE id IN (1, 2, 4)");
foreach ($consultas as $nome => $sql) {
    conferirPeriodo(consultarPeriodo($sql) === $antes[$nome], 'Editar nao pode mudar o periodo: ' . $nome);
}

// As mesmas regras do endpoint, exercitadas contra uma consulta fixa.
$consultaCategoria = "SELECT COALESCE(SUM(t.valor), 0) AS total
    FROM transacoes t
    WHERE t.usuario_id = ? AND t.tipo = 'despesa' AND t.status = 'aprovado'
      AND %s";
$condicoes = [
    "t.data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND t.data_transacao <= CURDATE()",
    "t.data_transacao >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND t.data_transacao < DATE_FORMAT(CURDATE(), '%Y-%m-01')",
    "t.data_transacao >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01') AND t.data_transacao <= CURDATE()",
    "t.data_transacao >= '2026-09-01' AND t.data_transacao <= '2026-09-02'",
];
conferirPeriodo(count($condicoes) === 4, 'Cobertura dos quatro periodos de categoria');
$conn->query('DELETE FROM transacoes WHERE id = 8');
foreach ($condicoes as $indice => $condicao) {
    $sql = sprintf($consultaCategoria, $condicao);
    $linhas = consultarPeriodo($sql);
    $esperado = [75.0, 100.0, 175.0, 75.0][$indice];
    conferirPeriodo((float)array_sum(array_column($linhas, 'total')) === $esperado,
        'Filtro de categoria deve respeitar data financeira: ' . $indice);
}

// Alterar explicitamente a data financeira deve mudar o mes, ao contrario da descricao.
$conn->query("UPDATE transacoes SET data_transacao = '2026-09-03' WHERE id = 1");
$mensal = consultarPeriodo(reset($consultas))[0];
conferirPeriodo((float)$mensal['despesas_atual'] === 175.0 && (float)$mensal['despesas_anterior'] === 0.0,
    'Alterar a data do lancamento deve recalcular os meses');
$conn->close();
echo $total . " verificacoes de periodos passaram; apenas tabelas temporarias foram usadas." . PHP_EOL;
