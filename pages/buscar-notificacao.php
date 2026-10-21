<?php
require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado(true);
require_once __DIR__ . '/../config/conn.php';

header('Content-Type: application/json; charset=utf-8');

$contagem = $conn->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0');
$contagem->bind_param('i', $usuario_id);
$contagem->execute();
$contagem->bind_result($naoLidas);
$contagem->fetch();
$contagem->close();

$stmt = $conn->prepare('SELECT id, categoria, titulo, mensagem, lida, criado_em FROM notificacoes WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 30');
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$notificacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($notificacoes as &$notificacao) {
    $notificacao['id'] = (int) $notificacao['id'];
    $notificacao['lida'] = (int) $notificacao['lida'];
}
unset($notificacao);

echo json_encode([
    'sucesso' => true,
    'nao_lidas' => (int) $naoLidas,
    'notificacoes' => $notificacoes
], JSON_UNESCAPED_UNICODE);