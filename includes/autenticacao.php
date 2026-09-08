<?php

function exigirUsuarioAutenticado(bool $respostaJson = false): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    // Sessoes antigas podiam ser criadas automaticamente pelas telas de teste.
    if ($usuarioId !== false && $usuarioId > 0 && ($_SESSION['autenticado'] ?? false) === true) {
        return $usuarioId;
    }

    $_SESSION = [];
    session_destroy();
    header('Cache-Control: no-store');

    if ($respostaJson) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => 'Faça login para continuar.'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ../login/login.php', true, 302);
    }
    exit;
}
