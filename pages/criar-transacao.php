<?php

session_start();
require_once '../config/conn.php';

$logPath = __DIR__ . '/_debug_criar_transacao.log';

function debugLog(string $logPath, string $msg): void
{
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logPath, $linha, FILE_APPEND | LOCK_EX);
}

debugLog($logPath, '--- nova requisição ---');
debugLog($logPath, 'POST bruto: ' . var_export($_POST, true));

$usuario_id = $_SESSION['usuario_id'] ?? 1;

function parseBRLParaFloat(string $valor): float
{
    $limpo = preg_replace('/[\s\x{00A0}]/u', '', $valor);
    $limpo = str_replace('R$', '', $limpo);
    $limpo = str_replace('.', '', $limpo);  
    $limpo = str_replace(',', '.', $limpo);  
    return (float) $limpo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog($logPath, 'Método não é POST, redirecionando sem fazer nada.');
    header('Location: dashboard.php');
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    debugLog($logPath, 'ERRO: conexão com o banco indisponível: ' . ($conn->connect_error ?? 'desconhecido'));
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

debugLog($logPath, "Valores lidos: tipo={$tipo} | descricao=" . var_export($descricao, true)
    . " | valor_bruto=" . var_export($valorPost, true) . " | valor_convertido={$valor}"
    . " | categoriaIdRaw=" . var_export($categoriaIdRaw, true) . " | categoriaId=" . var_export($categoriaId, true)
    . " | data={$dataTransacao}");


if (!in_array($tipo, $tiposValidos, true)) {
    debugLog($logPath, "FALHOU: tipo inválido -> '{$tipo}'");
    header('Location: dashboard.php');
    exit;
}
if ($descricao === '') {
    debugLog($logPath, 'FALHOU: descrição vazia');
    header('Location: dashboard.php');
    exit;
}
if ($valor <= 0) {
    debugLog($logPath, "FALHOU: valor <= 0 (valor_bruto=" . var_export($valorPost, true) . ", convertido={$valor})");
    header('Location: dashboard.php');
    exit;
}


$dataValidada = DateTime::createFromFormat('Y-m-d', $dataTransacao);
if (!$dataValidada || $dataValidada->format('Y-m-d') !== $dataTransacao) {
    debugLog($logPath, "Data '{$dataTransacao}' inválida, usando hoje.");
    $dataTransacao = date('Y-m-d');
}


if ($categoriaId !== null) {
    $stmtCheck = $conn->prepare("SELECT id FROM categorias WHERE id = ? AND usuario_id = ? AND tipo = ?");
    if ($stmtCheck) {
        $stmtCheck->bind_param("iis", $categoriaId, $usuario_id, $tipo);
        $stmtCheck->execute();
        $existe = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if (!$existe) {
            debugLog($logPath, "Categoria {$categoriaId} não pertence ao usuário {$usuario_id} com tipo '{$tipo}' -> zerando categoria.");
            $categoriaId = null;
        } else {
            debugLog($logPath, "Categoria {$categoriaId} confirmada para usuário {$usuario_id} e tipo '{$tipo}'.");
        }
    } else {
        debugLog($logPath, 'ERRO ao preparar SELECT categorias -> ' . $conn->error);
        $categoriaId = null;
    }
}

$stmt = $conn->prepare("
    INSERT INTO transacoes (usuario_id, descricao, valor, tipo, categoria_id, data_transacao, origem, status)
    VALUES (?, ?, ?, ?, ?, ?, 'manual', 'aprovado')
");

if (!$stmt) {
    debugLog($logPath, 'ERRO ao preparar INSERT -> ' . $conn->error);
    header('Location: dashboard.php');
    exit;
}

$stmt->bind_param("isdsis", $usuario_id, $descricao, $valor, $tipo, $categoriaId, $dataTransacao);
$conn->begin_transaction();
$sucesso = $stmt->execute();

// Mantém o saldo total sincronizado com lançamentos aprovados.
if ($sucesso) {
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
        debugLog($logPath, 'ERRO ao preparar atualizacao do saldo -> ' . $conn->error);
    }
}

if ($sucesso) {
    debugLog($logPath, "SUCESSO: transação inserida com id " . $stmt->insert_id . " (tipo={$tipo}, categoria_id=" . var_export($categoriaId, true) . ")");
} else {
    debugLog($logPath, 'ERRO ao executar INSERT -> ' . $stmt->error);
}

if ($sucesso) {
    $conn->commit();
} else {
    $conn->rollback();
}

$stmt->close();

header('Location: dashboard.php');
exit;
