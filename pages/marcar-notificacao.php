<?php

session_start();

include '../config/conn.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {

    echo json_encode([
        'sucesso' => false,
        'erro' => 'Usuário não autenticado.'
    ]);

    exit;
}

$usuario_id = (int) $_SESSION['usuario_id'];

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