<?php
// pages/criar-transacao.php
// Recebe o formulário de nova transação manual e grava no banco.
// Padrão Post/Redirect/Get, igual às outras telas do sistema.

session_start();
require_once '../config/conn.php';

$usuario_id = $_SESSION['usuario_id'] ?? 1;

function parseBRLParaFloat(string $valor): float
{
    $limpo = str_replace(['R$', ' '], '', $valor);
    $limpo = str_replace('.', '', $limpo);   // remove separador de milhar
    $limpo = str_replace(',', '.', $limpo);  // vírgula decimal -> ponto
    return (float) $limpo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$tiposValidos = ['despesa', 'receita'];

$tipo = $_POST['tipo'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$valor = parseBRLParaFloat($_POST['valor'] ?? '');
$categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
$dataTransacao = $_POST['data_transacao'] ?? date('Y-m-d');

// Validação server-side (a real, que vale de verdade)
if (!in_array($tipo, $tiposValidos, true) || $descricao === '' || $valor <= 0) {
    header('Location: dashboard.php?erro=dados_invalidos');
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
    $stmtCheck->bind_param("iis", $categoriaId, $usuario_id, $tipo);
    $stmtCheck->execute();
    $existe = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$existe) {
        $categoriaId = null;
    }
}

$stmt = $conn->prepare("
    INSERT INTO transacoes (usuario_id, descricao, valor, tipo, categoria_id, data_transacao, origem, status)
    VALUES (?, ?, ?, ?, ?, ?, 'manual', 'aprovado')
");
$stmt->bind_param("isdsis", $usuario_id, $descricao, $valor, $tipo, $categoriaId, $dataTransacao);
$stmt->execute();
$stmt->close();

header('Location: dashboard.php');
exit;