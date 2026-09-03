<?php
// pages/revisar-acao.php
// Recebe aprovar / rejeitar / editar de um lançamento via fetch()

session_start();
include '../config/conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'] ?? 1;
$dados = json_decode(file_get_contents('php://input'), true);

$acao = $dados['acao'] ?? '';
$id = isset($dados['id']) ? (int) $dados['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido']);
    exit;
}

// Busca ou cria uma categoria pelo nome (usado só na edição)
function encontrarOuCriarCategoria($conn, $usuario_id, $nome) {
    $stmt = $conn->prepare("SELECT id FROM categorias WHERE usuario_id = ? AND nome = ?");
    $stmt->bind_param("is", $usuario_id, $nome);
    $stmt->execute();
    $categoria = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($categoria) {
        return (int) $categoria['id'];
    }

    $stmt = $conn->prepare("INSERT INTO categorias (usuario_id, nome, tipo) VALUES (?, ?, 'despesa')");
    $stmt->bind_param("is", $usuario_id, $nome);
    $stmt->execute();
    $novoId = $stmt->insert_id;
    $stmt->close();

    return $novoId;
}

function aplicarVariacaoNoSaldo($conn, $usuario_id, $variacao) {
    if ((float) $variacao === 0.0) {
        return true;
    }

    $stmt = $conn->prepare(
        "UPDATE usuarios
         SET saldo_total = COALESCE(saldo_total, 0) + ?
         WHERE id = ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("di", $variacao, $usuario_id);
    $sucesso = $stmt->execute();
    $stmt->close();

    return $sucesso;
}

function valorComSinal($tipo, $valor) {
    return $tipo === 'receita' ? (float) $valor : -(float) $valor;
}

$conn->begin_transaction();

$stmt = $conn->prepare(
    "SELECT tipo, valor, status
     FROM transacoes
     WHERE id = ? AND usuario_id = ?
     FOR UPDATE"
);
$stmt->bind_param("ii", $id, $usuario_id);
$stmt->execute();
$transacaoAtual = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transacaoAtual) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'erro' => 'Lancamento nao encontrado']);
    exit;
}

switch ($acao) {
    case 'aprovar':
        $sucesso = true;

        if ($transacaoAtual['status'] !== 'aprovado') {
            $stmt = $conn->prepare("UPDATE transacoes SET status = 'aprovado', aprovado_em = NOW() WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $id, $usuario_id);
            $sucesso = $stmt->execute();
            $stmt->close();

            if ($sucesso) {
                $sucesso = aplicarVariacaoNoSaldo(
                    $conn,
                    $usuario_id,
                    valorComSinal($transacaoAtual['tipo'], $transacaoAtual['valor'])
                );
            }
        }
        break;

    case 'rejeitar':
        $stmt = $conn->prepare("UPDATE transacoes SET status = 'rejeitado' WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id, $usuario_id);
        $sucesso = $stmt->execute();
        $stmt->close();

        if ($sucesso && $transacaoAtual['status'] === 'aprovado') {
            $sucesso = aplicarVariacaoNoSaldo(
                $conn,
                $usuario_id,
                -valorComSinal($transacaoAtual['tipo'], $transacaoAtual['valor'])
            );
        }
        break;

    case 'editar':
        $descricao = trim($dados['descricao'] ?? '');
        $valor = isset($dados['valor']) ? (float) $dados['valor'] : null;
        $categoriaId = isset($dados['categoria_id']) && $dados['categoria_id'] !== null && $dados['categoria_id'] !== ''
            ? (int) $dados['categoria_id']
            : null;
        $dataTransacao = $dados['data_transacao'] ?? '';

        $dataValida = DateTime::createFromFormat('!Y-m-d', $dataTransacao);
        $errosData = DateTime::getLastErrors();
        $dataInvalida = !$dataValida
            || ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0));

        if ($descricao === '' || $valor === null || $valor <= 0 || $dataInvalida) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos para edição']);
            exit;
        }

        if ($categoriaId !== null) {
            $stmtCategoria = $conn->prepare(
                'SELECT id FROM categorias WHERE id = ? AND usuario_id = ? AND tipo = ? LIMIT 1'
            );
            $stmtCategoria->bind_param('iis', $categoriaId, $usuario_id, $transacaoAtual['tipo']);
            $stmtCategoria->execute();
            $categoriaValida = $stmtCategoria->get_result()->fetch_assoc();
            $stmtCategoria->close();

            if (!$categoriaValida) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['sucesso' => false, 'erro' => 'Categoria invÃ¡lida para este lanÃ§amento']);
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE transacoes SET descricao = ?, valor = ?, categoria_id = ?, data_transacao = ? WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("sdisii", $descricao, $valor, $categoriaId, $dataTransacao, $id, $usuario_id);
        $sucesso = $stmt->execute();
        $stmt->close();

        if ($sucesso && $transacaoAtual['status'] === 'aprovado') {
            $variacaoSaldo = valorComSinal($transacaoAtual['tipo'], $valor)
                - valorComSinal($transacaoAtual['tipo'], $transacaoAtual['valor']);
            $sucesso = aplicarVariacaoNoSaldo($conn, $usuario_id, $variacaoSaldo);
        }
        break;

    default:
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Ação desconhecida']);
        exit;
}

if ($sucesso) {
    $conn->commit();
    echo json_encode(['sucesso' => true]);
} else {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar a ação']);
}

$conn->close();
