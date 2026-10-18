<?php
$finmapCurrentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$finmapNavItems = [
    ['label' => 'Visão geral', 'icon' => 'bi-grid-1x2', 'tone' => 'overview', 'href' => 'dashboard.php', 'page' => 'dashboard.php'],
    ['label' => 'Orçamento', 'icon' => 'bi-calendar2-week', 'tone' => 'budget', 'href' => 'orcamento-mensal.php', 'page' => 'orcamento-mensal.php'],
    ['label' => 'Metas', 'icon' => 'bi-bullseye', 'tone' => 'goals', 'href' => 'metas-financeiras.php', 'page' => 'metas-financeiras.php'],
    ['label' => 'Poupança', 'icon' => 'bi-piggy-bank', 'tone' => 'savings', 'href' => 'poupanca-invisivel.php', 'page' => 'poupanca-invisivel.php'],
    ['label' => 'Alertas', 'icon' => 'bi-bell', 'tone' => 'alerts', 'href' => 'alertas-preditivos.php', 'page' => 'alertas-preditivos.php'],
    ['label' => 'Lançamentos', 'icon' => 'bi-list-check', 'tone' => 'entries', 'href' => 'revisar-lancamentos.php', 'page' => 'revisar-lancamentos.php'],
];
?>
<nav class="finmap-app-nav" aria-label="Telas principais">
  <?php foreach ($finmapNavItems as $finmapNavItem): ?>
    <?php $finmapIsCurrent = $finmapCurrentPage === $finmapNavItem['page']; ?>
    <a
      class="finmap-app-nav__link finmap-app-nav__link--<?= htmlspecialchars($finmapNavItem['tone'], ENT_QUOTES, 'UTF-8') ?><?= $finmapIsCurrent ? ' is-current' : '' ?>"
      href="<?= htmlspecialchars($finmapNavItem['href'], ENT_QUOTES, 'UTF-8') ?>"
      <?= $finmapIsCurrent ? 'aria-current="page"' : '' ?>
    >
      <i class="bi <?= htmlspecialchars($finmapNavItem['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($finmapNavItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<script src="../assets/js/mobile-bottom-nav.js?v=1" defer></script>
