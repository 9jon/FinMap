<?php
// pages/atualizar-saldo.php
// Recebe o novo saldo via fetch() (POST) e grava no banco.
// Responde em JSON pra o JavaScript saber se deu certo ou não.

session_start();
include '../config/conn.php';

header('Content-Type: application/json');

// Só aceita requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'] ?? 1; // depois vira só $_SESSION['usuario_id']

// Lê o corpo da requisição (vem como JSON do fetch)
$dados = json_decode(file_get_contents('php://input'), true);
$novoSaldo = $dados['novo_saldo'] ?? null;

// Validação básica
if ($novoSaldo === null || !is_numeric($novoSaldo)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Valor de saldo inválido']);
    exit;
}

$novoSaldo = floatval($novoSaldo);

$stmt = $conn->prepare("UPDATE usuarios SET saldo_total = ? WHERE id = ?");
$stmt->bind_param("di", $novoSaldo, $usuario_id);

if ($stmt->execute()) {
    echo json_encode([
        'sucesso' => true,
        'novo_saldo' => number_format($novoSaldo, 2, ',', '.')
    ]);
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao atualizar o saldo']);
}

$stmt->close();
$conn->close();