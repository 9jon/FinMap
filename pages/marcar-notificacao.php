<?php

require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado(true);

include '../config/conn.php';

header('Content-Type: application/json; charset=utf-8');



$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {

    echo json_encode([
        'sucesso' => false,
        'erro' => 'Notificação inválida.'
    ]);

    exit;
}

$stmt = $conn->prepare("
    UPDATE notificacoes
    SET lida = 1
    WHERE id = ?
      AND usuario_id = ?
");

$stmt->bind_param(
    "ii",
    $id,
    $usuario_id
);

$sucesso = $stmt->execute();

$stmt->close();

echo json_encode([
    'sucesso' => $sucesso
]);