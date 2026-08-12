<?php
// pages/atualizar-regras-revisao.php
// Salva as regras de revisão (priorizar OCR, ocultar aprovados, limite de confiança)

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

$priorizarOcr = !empty($dados['priorizar_ocr_baixa_confianca']) ? 1 : 0;
$ocultarAprovados = !empty($dados['ocultar_aprovados']) ? 1 : 0;
$limiteConfianca = isset($dados['limite_confianca_percentual']) ? (int) $dados['limite_confianca_percentual'] : 80;

if ($limiteConfianca < 1 || $limiteConfianca > 100) {
    $limiteConfianca = 80;
}

$stmt = $conn->prepare("
    INSERT INTO revisao_regras
        (usuario_id, priorizar_ocr_baixa_confianca, ocultar_aprovados, limite_confianca_percentual)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        priorizar_ocr_baixa_confianca = VALUES(priorizar_ocr_baixa_confianca),
        ocultar_aprovados = VALUES(ocultar_aprovados),
        limite_confianca_percentual = VALUES(limite_confianca_percentual)
");
$stmt->bind_param("iiii", $usuario_id, $priorizarOcr, $ocultarAprovados, $limiteConfianca);

if ($stmt->execute()) {
    echo json_encode(['sucesso' => true]);
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar regras']);
}

$stmt->close();
$conn->close();