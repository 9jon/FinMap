<?php
// pages/criar-transacao.php
// Recebe o formulário de nova transação manual e grava no banco.
// Padrão Post/Redirect/Get, igual às outras telas do sistema.
//
// VERSÃO COM DIAGNÓSTICO: em vez de sempre redirecionar com o mesmo
// "erro=dados_invalidos" genérico (que não dizia QUAL validação
// falhou), agora cada motivo de falha tem seu próprio código de erro
// na URL. Isso é temporário pra descobrirmos exatamente onde está
// travando — depois que resolvermos, dá pra simplificar de volta.

session_start();
require_once '../config/conn.php';
require_once __DIR__ . '/../config/categorias-padrao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 1;
garantirCategoriasPadrao($conn, (int) $usuario_id);

function parseBRLParaFloat(string $valor): float
{
    $limpo = preg_replace('/[\s\x{00A0}]/u', '', $valor); // remove espaço comum e não separável
    $limpo = str_replace('R$', '', $limpo);
    $limpo = str_replace('.', '', $limpo);   // remove separador de milhar
    $limpo = str_replace(',', '.', $limpo);  // vírgula decimal -> ponto
    return (float) $limpo;
}

function falhar(string $motivo, array $extra = []): void
{
    $query = array_merge(['erro' => $motivo], $extra);
    header('Location: dashboard.php?' . http_build_query($query));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

// Se a conexão com o banco falhou lá no conn.php, não faz sentido seguir
if (!isset($conn) || $conn->connect_error) {
    error_log('criar-transacao.php: conexão com o banco indisponível');
    falhar('erro_conexao');
}

$tiposValidos = ['despesa', 'receita'];

$tipo = $_POST['tipo'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$valorPost = $_POST['valor'] ?? '';
$valor = parseBRLParaFloat($valorPost);

$categoriaIdRaw = $tipo === 'receita'
    ? ($_POST['categoria_id_receita'] ?? '')
    : ($_POST['categoria_id_despesa'] ?? '');
$categoriaId = ($categoriaIdRaw !== '' && $categoriaIdRaw !== null) ? (int) $categoriaIdRaw : null;

$dataTransacao = $_POST['data_transacao'] ?? date('Y-m-d');

// -----------------------------------------------------------------
// Validações, uma por uma, cada uma com seu próprio motivo de erro
// -----------------------------------------------------------------
if (!in_array($tipo, $tiposValidos, true)) {
    falhar('tipo_invalido');
}

if ($descricao === '') {
    falhar('descricao_vazia');
}

if ($valor <= 0) {
    // manda o valor bruto recebido (só pra debug, remova depois de resolver)
    falhar('valor_invalido', ['valor_recebido' => $valorPost]);
}

// Valida se a data enviada é uma data real (evita string maliciosa/quebrada)
$dataValidada = DateTime::createFromFormat('Y-m-d', $dataTransacao);
if (!$dataValidada || $dataValidada->format('Y-m-d') !== $dataTransacao) {
    $dataTransacao = date('Y-m-d');
}

// Se veio categoria_id, confirma que ela é do usuário e bate com o tipo escolhido
if ($categoriaId !== null) {
    $stmtCheck = $conn->prepare("SELECT id FROM categorias WHERE id = ? AND usuario_id = ? AND tipo = ?");
    if (!$stmtCheck) {
        error_log('criar-transacao.php: erro ao preparar SELECT categorias -> ' . $conn->error);
        falhar('erro_banco_categoria');
    }
    $stmtCheck->bind_param("iis", $categoriaId, $usuario_id, $tipo);
    $stmtCheck->execute();
    $existe = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$existe) {
        // Categoria não pertence ao usuário ou não bate com o tipo -> só ignora a categoria,
        // NÃO bloqueia a transação
        $categoriaId = null;
    }
}

$stmt = $conn->prepare("
    INSERT INTO transacoes (usuario_id, descricao, valor, tipo, categoria_id, data_transacao, origem, status)
    VALUES (?, ?, ?, ?, ?, ?, 'manual', 'pendente')
");

if (!$stmt) {
    error_log('criar-transacao.php: erro ao preparar INSERT -> ' . $conn->error);
    falhar('erro_banco_insert');
}

$stmt->bind_param("isdsis", $usuario_id, $descricao, $valor, $tipo, $categoriaId, $dataTransacao);
$sucesso = $stmt->execute();

if (!$sucesso) {
    error_log('criar-transacao.php: erro ao executar INSERT -> ' . $stmt->error);
    $stmt->close();
    falhar('erro_execucao', ['detalhe' => $stmt->error]);
}

$stmt->close();

header('Location: dashboard.php');
exit;
