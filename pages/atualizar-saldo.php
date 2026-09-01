<?php


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
$novoSaldo = $dados['novo_saldo'] ?? null;


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