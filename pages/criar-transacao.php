<?php

require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado();
require_once '../config/conn.php';

function parseBRLParaFloat(string $valor): float
{
    $limpo = preg_replace('/[\s\x{00A0}]/u', '', $valor);
    $limpo = str_replace('R$', '', $limpo);
    $limpo = str_replace('.', '', $limpo);  
    $limpo = str_replace(',', '.', $limpo);  
    return (float) $limpo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    header('Location: dashboard.php');
    exit;
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

if (!in_array($tipo, $tiposValidos, true)) {
    header('Location: dashboard.php');
    exit;
}
if ($descricao === '') {
    header('Location: dashboard.php');
    exit;
}
if ($valor <= 0) {
    header('Location: dashboard.php');
    exit;
}

$dataValidada = DateTime::createFromFormat('Y-m-d', $dataTransacao);
if (!$dataValidada || $dataValidada->format('Y-m-d') !== $dataTransacao) {
    $dataTransacao = date('Y-m-d');
}

$agendado = $dataTransacao > date('Y-m-d');
$observacaoAgendamento = $agendado
    ? ($tipo === 'despesa' ? 'Fatura agendada' : 'Receita agendada')
    : null;
$statusTransacao = $agendado ? 'pendente' : 'aprovado';

if ($categoriaId !== null) {
    $stmtCheck = $conn->prepare("SELECT id FROM categorias WHERE id = ? AND usuario_id = ? AND tipo = ?");
    if ($stmtCheck) {
        $stmtCheck->bind_param("iis", $categoriaId, $usuario_id, $tipo);
        $stmtCheck->execute();
        $existe = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if (!$existe) {
            $categoriaId = null;
        }
    } else {
        $categoriaId = null;
    }
}

$stmt = $conn->prepare("
    INSERT INTO transacoes
        (usuario_id, descricao, valor, tipo, categoria_id, data_transacao, origem, status, observacao_captura)
    VALUES (?, ?, ?, ?, ?, ?, 'manual', ?, ?)
");

if (!$stmt) {
    header('Location: dashboard.php');
    exit;
}

$stmt->bind_param(
    "isdsisss",
    $usuario_id,
    $descricao,
    $valor,
    $tipo,
    $categoriaId,
    $dataTransacao,
    $statusTransacao,
    $observacaoAgendamento
);
$conn->begin_transaction();
$sucesso = $stmt->execute();

// Mantém o saldo total sincronizado com lançamentos aprovados.
if ($sucesso && !$agendado) {
    $variacaoSaldo = $tipo === 'receita' ? $valor : -$valor;
    $stmtSaldo = $conn->prepare(
        "UPDATE usuarios
         SET saldo_total = COALESCE(saldo_total, 0) + ?
         WHERE id = ?"
    );

    if ($stmtSaldo) {
        $stmtSaldo->bind_param("di", $variacaoSaldo, $usuario_id);
        $sucesso = $stmtSaldo->execute();
        $stmtSaldo->close();
    } else {
        $sucesso = false;
    }
}

if ($sucesso) {
    $conn->commit();
} else {
    $conn->rollback();
}

$stmt->close();

header('Location: dashboard.php');
exit;
