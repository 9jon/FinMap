<?php

require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado(true);

include '../config/conn.php';

header('Content-Type: application/json; charset=utf-8');

if (($_POST['todas'] ?? '') === '1') {
    $stmt = $conn->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_id = ? AND lida = 0");
    $stmt->bind_param("i", $usuario_id);
    $sucesso = $stmt->execute();
    $stmt->close();

    $contagem = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
    $contagem->bind_param("i", $usuario_id);
    $contagem->execute();
    $contagem->bind_result($naoLidas);
    $contagem->fetch();
    $contagem->close();

    echo json_encode([
        'sucesso' => $sucesso,
        'nao_lidas' => (int) $naoLidas
    ]);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Notificação inválida.'
    ]);
    exit;
}

$stmt = $conn->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $id, $usuario_id);
$sucesso = $stmt->execute();
$stmt->close();

$contagem = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
$contagem->bind_param("i", $usuario_id);
$contagem->execute();
$contagem->bind_result($naoLidas);
$contagem->fetch();
$contagem->close();

echo json_encode([
    'sucesso' => $sucesso,
    'nao_lidas' => (int) $naoLidas
]);