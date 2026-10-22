<?php
$stmtAvatarTopo = $conn->prepare('SELECT avatar_iniciais, foto_perfil FROM usuarios WHERE id = ? LIMIT 1');
$stmtAvatarTopo->bind_param('i', $usuario_id);
$stmtAvatarTopo->execute();
$dadosAvatarTopo = $stmtAvatarTopo->get_result()->fetch_assoc() ?: [];
$stmtAvatarTopo->close();

$avatarTopoFoto = trim((string)($dadosAvatarTopo['foto_perfil'] ?? ''));
$avatarTopoIniciais = trim((string)($dadosAvatarTopo['avatar_iniciais'] ?? $_SESSION['avatar_iniciais'] ?? 'US'));
if ($avatarTopoIniciais === '') {
    $avatarTopoIniciais = 'US';
}
