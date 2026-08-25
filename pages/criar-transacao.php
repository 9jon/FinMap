<?php
// pages/criar-transacao.php
// Recebe o formulário de nova transação manual e grava no banco.
// Padrão Post/Redirect/Get. Sem alertas na tela: se der certo, a
// transação simplesmente aparece nas listas do dashboard. Se der
// errado, o motivo vai pro log do servidor (error_log), não pra UI.

session_start();
require_once '../config/conn.php';

$usuario_id = $_SESSION['usuario_id'] ?? 1;

function parseBRLParaFloat(string $valor): float
{
    // Remove espaço comum e espaço "não separável" (U+00A0), que o
    // toLocaleString("pt-BR", {style:"currency"}) do JS costuma inserir
    // entre "R$" e o número.
    $limpo = preg_replace('/[\s\x{00A0}]/u', '', $valor);
    $limpo = str_replace('R$', '', $limpo);
    $limpo = str_replace('.', '', $limpo);   // remove separador de milhar
    $limpo = str_replace(',', '.', $limpo);  // vírgula decimal -> ponto
    return (float) $limpo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    error_log('criar-transacao.php: conexão com o banco indisponível');
    header('Location: dashboard.php');
    exit;
}

$tiposValidos = ['despesa', 'receita'];

$tipo = $_POST['tipo'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$valor = parseBRLParaFloat($_POST['valor'] ?? '');

// A categoria vem em campos separados por tipo (categoria_id_despesa /
// categoria_id_receita) pra evitar que o select escondido do outro tipo
// sobrescreva a escolha feita no select visível.
$categoriaIdRaw = $tipo === 'receita'
    ? ($_POST['categoria_id_receita'] ?? '')
    : ($_POST['categoria_id_despesa'] ?? '');
$categoriaId = ($categoriaIdRaw !== '' && $categoriaIdRaw !== null) ? (int) $categoriaIdRaw : null;

$dataTransacao = $_POST['data_transacao'] ?? date('Y-m-d');

// Validação server-side
if (!in_array($tipo, $tiposValidos, true) || $descricao === '' || $valor <= 0) {
    error_log("criar-transacao.php: validação falhou (tipo={$tipo}, descricao=" . var_export($descricao, true) . ", valor={$valor})");
    header('Location: dashboard.php');
    exit;
}

// Valida se a data enviada é uma data real (evita string maliciosa/quebrada)
$dataValidada = DateTime::createFromFormat('Y-m-d', $dataTransacao);
if (!$dataValidada || $dataValidada->format('Y-m-d') !== $dataTransacao) {
    $dataTransacao = date('Y-m-d');
}

// Se veio categoria_id, confirma que ela é do usuário e bate com o tipo escolhido
if ($categoriaId !== null) {
    $stmtCheck = $conn->prepare("SELECT id FROM categorias WHERE id = ? AND usuario_id = ? AND tipo = ?");
    if ($stmtCheck) {
        $stmtCheck->bind_param("iis", $categoriaId, $usuario_id, $tipo);
        $stmtCheck->execute();
        $existe = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if (!$existe) {
            $categoriaId = null; // categoria não é do usuário ou não bate com o tipo -> ignora, mas não bloqueia
        }
    } else {
        error_log('criar-transacao.php: erro ao preparar SELECT categorias -> ' . $conn->error);
        $categoriaId = null;
    }
}

$stmt = $conn->prepare("
    INSERT INTO transacoes (usuario_id, descricao, valor, tipo, categoria_id, data_transacao, origem, status)
    VALUES (?, ?, ?, ?, ?, ?, 'manual', 'aprovado')
");

if (!$stmt) {
    error_log('criar-transacao.php: erro ao preparar INSERT -> ' . $conn->error);
    header('Location: dashboard.php');
    exit;
}

$stmt->bind_param("isdsis", $usuario_id, $descricao, $valor, $tipo, $categoriaId, $dataTransacao);

if (!$stmt->execute()) {
    error_log('criar-transacao.php: erro ao executar INSERT -> ' . $stmt->error);
}

$stmt->close();

header('Location: dashboard.php');
exit;