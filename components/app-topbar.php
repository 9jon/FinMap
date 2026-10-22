<?php
$avatarTopoFoto = (string)($avatarTopoFoto ?? ($usuario['foto_perfil'] ?? ''));
$avatarTopoIniciais = (string)($avatarTopoIniciais ?? ($iniciais ?? $_SESSION['avatar_iniciais'] ?? 'US'));
if ($avatarTopoIniciais === '') {
    $avatarTopoIniciais = 'US';
}

$stmtNotificacoesTopo = $conn->prepare(
    'SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0'
);
$stmtNotificacoesTopo->bind_param('i', $usuario_id);
$stmtNotificacoesTopo->execute();
$stmtNotificacoesTopo->bind_result($notificacoesNaoLidasTopo);
$stmtNotificacoesTopo->fetch();
$stmtNotificacoesTopo->close();
?>
<header class="topbar">
  <div class="topbar-left">
    <a class="brand" href="dashboard.php" aria-label="FinMap, visão geral">
      <span class="brand-mark"><img src="../assets/img/finmap-icone-branco.png" alt=""></span>
      <span class="brand-name">FinMap</span>
    </a>
  </div>

  <?php require __DIR__ . '/finmap-app-nav.php'; ?>

  <div class="topbar-right">
    <button class="ai-btn" id="openAiModal" type="button" aria-label="Abrir Assistente IA" title="Assistente IA">
      <i class="bi bi-stars" aria-hidden="true"></i>
      <span>Assistente IA</span>
    </button>

    <a class="icon-plain" id="globalSettingsLink" href="dashboard.php?abrir=configuracoes" aria-label="Configurações" title="Configurações">
      <i class="bi bi-gear" aria-hidden="true"></i>
    </a>

    <button class="icon-plain notification-btn" id="openNotificationsPanel" type="button" aria-label="Notificações" title="Notificações">
      <i class="bi bi-bell" aria-hidden="true"></i>
      <?php if ((int)$notificacoesNaoLidasTopo > 0): ?>
        <span class="notification-dot" aria-hidden="true"></span>
      <?php endif; ?>
    </button>

    <a class="profile-avatar" id="globalProfileLink" href="dashboard.php?abrir=perfil" aria-label="Abrir meu perfil" title="Meu perfil">
      <?php if ($avatarTopoFoto !== ''): ?>
        <img src="../<?= htmlspecialchars($avatarTopoFoto, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php else: ?>
        <?= htmlspecialchars($avatarTopoIniciais, ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </a>
  </div>
</header>
