<?php
// pages/atualizar-poupanca-config.php
// Recebe o cenário e as categorias ativas via fetch() e salva no banco.

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

$cenario = $dados['cenario_selecionado'] ?? 'equilibrado';
$cenariosValidos = ['conservador', 'equilibrado', 'agressivo'];

if (!in_array($cenario, $cenariosValidos, true)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Cenário inválido']);
    exit;
}

$considerarCafe = !empty($dados['considerar_cafe']) ? 1 : 0;
$considerarImpulso = !empty($dados['considerar_impulso']) ? 1 : 0;
$considerarAssinatura = !empty($dados['considerar_assinatura']) ? 1 : 0;
$considerarTransporte = !empty($dados['considerar_transporte']) ? 1 : 0;

// Como usuario_id é UNIQUE em poupanca_configuracao, usamos
// "ON DUPLICATE KEY UPDATE" pra criar se não existir, ou atualizar se já existir.
$stmt = $conn->prepare("
    INSERT INTO poupanca_configuracao
        (usuario_id, considerar_cafe, considerar_impulso, considerar_assinatura, considerar_transporte, cenario_selecionado)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        considerar_cafe = VALUES(considerar_cafe),
        considerar_impulso = VALUES(considerar_impulso),
        considerar_assinatura = VALUES(considerar_assinatura),
        considerar_transporte = VALUES(considerar_transporte),
        cenario_selecionado = VALUES(cenario_selecionado)
");
$stmt->bind_param(
    "iiiiis",
    $usuario_id,
    $considerarCafe,
    $considerarImpulso,
    $considerarAssinatura,
    $considerarTransporte,
    $cenario
);

if ($stmt->execute()) {
    echo json_encode(['sucesso' => true]);
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar configuração']);
}

$stmt->close();
$conn->close();