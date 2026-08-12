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

switch ($acao) {
    case 'aprovar':
        $stmt = $conn->prepare("UPDATE transacoes SET status = 'aprovado' WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id, $usuario_id);
        $sucesso = $stmt->execute();
        $stmt->close();
        break;

    case 'rejeitar':
        $stmt = $conn->prepare("UPDATE transacoes SET status = 'rejeitado' WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id, $usuario_id);
        $sucesso = $stmt->execute();
        $stmt->close();
        break;

    case 'editar':
        $descricao = trim($dados['descricao'] ?? '');
        $valor = isset($dados['valor']) ? (float) $dados['valor'] : null;
        $categoriaNome = trim($dados['categoria'] ?? '');

        if ($descricao === '' || $valor === null || $valor <= 0 || $categoriaNome === '') {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos para edição']);
            exit;
        }

        $categoriaId = encontrarOuCriarCategoria($conn, $usuario_id, $categoriaNome);

        $stmt = $conn->prepare("UPDATE transacoes SET descricao = ?, valor = ?, categoria_id = ? WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("sdiii", $descricao, $valor, $categoriaId, $id, $usuario_id);
        $sucesso = $stmt->execute();
        $stmt->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Ação desconhecida']);
        exit;
}

if ($sucesso) {
    echo json_encode(['sucesso' => true]);
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar a ação']);
}

$conn->close();