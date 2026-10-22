<?php
require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado();
require_once __DIR__ . '/../config/conn.php';

require_once __DIR__ . '/../includes/avatar-usuario.php';

function parseBRLParaFloat(string $valor): float
{

    $limpo = preg_replace('/[\s\x{00A0}]/u', '', $valor);
    $limpo = str_replace('R$', '', $limpo);
    $limpo = str_replace('.', '', $limpo);
    $limpo = str_replace(',', '.', $limpo);
    return (float) $limpo;
}



$erroRenda = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {

    $acao = $_POST['acao'];

    if ($acao === 'atualizar_renda') {
        $novaRenda = parseBRLParaFloat($_POST['renda'] ?? '');

        if ($novaRenda <= 0) {
            $erroRenda = 'Valor de renda inválido: "' . htmlspecialchars($_POST['renda'] ?? '') . '" não foi reconhecido como um número.';
        } else {

            $sqlCheck = "SELECT id FROM configuracoes_renda WHERE usuario_id = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->bind_param("i", $usuario_id);
            $stmtCheck->execute();
            $existe = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if (!$existe) {
                $erroRenda = 'Você ainda não tem uma configuração de renda salva. Configure sua renda primeiro na tela "Configuração de Renda".';
            } else {
                $sqlUpdate = "UPDATE configuracoes_renda SET renda_mensal = ? WHERE usuario_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("di", $novaRenda, $usuario_id);
                $stmtUpdate->execute();
                $linhasAfetadas = $stmtUpdate->affected_rows;
                $stmtUpdate->close();

            }
        }
    }

    if ($erroRenda === '') {
        header('Location: orcamento-mensal.php');
        exit;
    }
}


$sqlRenda = "SELECT renda_mensal FROM configuracoes_renda WHERE usuario_id = ?";
$stmtRenda = $conn->prepare($sqlRenda);
$stmtRenda->bind_param("i", $usuario_id);
$stmtRenda->execute();
$rendaRow = $stmtRenda->get_result()->fetch_assoc();
$stmtRenda->close();
$renda = $rendaRow ? (float)$rendaRow['renda_mensal'] : 0.0;





// Totais seguem a data do lancamento, independentemente da importacao ou edicao.
$sqlCategorias = "
    SELECT c.id, c.nome, c.icone, c.cor,
           COALESCE(SUM(t.valor), 0) AS gasto_real
    FROM categorias c
    LEFT JOIN transacoes t
        ON t.categoria_id = c.id
       AND t.usuario_id = c.usuario_id
       AND t.tipo = 'despesa'
       AND t.status = 'aprovado'
       AND t.data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND t.data_transacao < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
    WHERE c.usuario_id = ? AND c.tipo = 'despesa'
    GROUP BY c.id, c.nome, c.icone, c.cor
    ORDER BY gasto_real DESC
";
$stmtCategorias = $conn->prepare($sqlCategorias);
$stmtCategorias->bind_param("i", $usuario_id);
$stmtCategorias->execute();
$categorias = $stmtCategorias->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCategorias->close();

$stmtSemCategoria = $conn->prepare("
    SELECT COALESCE(SUM(valor), 0) AS gasto_sem_categoria
    FROM transacoes
    WHERE usuario_id = ?
      AND tipo = 'despesa'
      AND status = 'aprovado'
      AND categoria_id IS NULL
      AND data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND data_transacao < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
");
$stmtSemCategoria->bind_param("i", $usuario_id);
$stmtSemCategoria->execute();
$gastoSemCategoria = (float) ($stmtSemCategoria->get_result()->fetch_assoc()['gasto_sem_categoria'] ?? 0);
$stmtSemCategoria->close();


$categoriasCalculadas = [];
$comprometido = 0.0;
foreach ($categorias as $cat) {
    $valor = (float)($cat['gasto_real'] ?? 0);
    $comprometido += $valor;
    $categoriasCalculadas[] = [
        'id' => (int)$cat['id'],
        'nome' => $cat['nome'],
        'icone' => $cat['icone'],
        'cor' => $cat['cor'],
        'valor' => $valor,
    ];
}
if ($gastoSemCategoria > 0) {
    $comprometido += $gastoSemCategoria;
    $categoriasCalculadas[] = [
        'id' => 0, 'nome' => 'Sem categoria', 'icone' => 'question-circle',
        'cor' => 'orange', 'valor' => $gastoSemCategoria,
    ];
}
$saldo = $renda - $comprometido;
$percentual = $renda > 0 ? ($comprometido / $renda) * 100 : 0;
if ($renda <= 0) {
    $status = [
        'label' => 'Renda não configurada',
        'texto' => 'Informe sua renda mensal para comparar com as despesas.',
        'sugestao' => 'Cadastre sua renda para começar a acompanhar o saldo.',
        'cor' => '#64748b',
    ];
} elseif ($saldo < 0) {
    $status = [
        'label' => 'Despesas acima da renda',
        'texto' => 'As despesas aprovadas já passaram da renda mensal informada.',
        'sugestao' => 'Revise os lançamentos e as próximas despesas do mês.',
        'cor' => '#ef4444',
    ];
} elseif (abs($saldo) < 0.005) {
    $status = [
        'label' => 'Renda totalmente utilizada',
        'texto' => 'As despesas aprovadas já alcançaram a renda mensal informada.',
        'sugestao' => 'Evite contar com saldo livre até registrar uma nova receita.',
        'cor' => '#f59e0b',
    ];
} else {
    $status = [
        'label' => 'Ainda há saldo',
        'texto' => 'Este é o valor que sobra da renda depois das despesas aprovadas.',
        'sugestao' => 'Acompanhe as próximas despesas para preservar esse saldo.',
        'cor' => '#24bb45',
    ];
}
$categoriaMaisPesada = 'Nenhuma despesa registrada';
$maiorValor = 0.0;
foreach ($categoriasCalculadas as $categoria) {
    if ($categoria['valor'] > $maiorValor) {
        $maiorValor = $categoria['valor'];
        $categoriaMaisPesada = $categoria['nome'];
    }
}

$paletaGrafico = [
    'green' => '#22c55e', 'blue' => '#3b82f6', 'orange' => '#f97316',
    'red' => '#ef4444', 'purple' => '#8b5cf6', 'yellow' => '#eab308',
    'pink' => '#ec4899', 'teal' => '#14b8a6', 'indigo' => '#6366f1',
];
$dadosGraficoCategorias = [];
$totalGrafico = array_sum(array_map(static fn($categoria) => (float)$categoria['valor'], $categoriasCalculadas));
foreach ($categoriasCalculadas as $categoria) {
    if ($categoria['valor'] <= 0) continue;
    $dadosGraficoCategorias[] = [
        'id' => (int)$categoria['id'],
        'nome' => (string)$categoria['nome'],
        'valor' => (float)$categoria['valor'],
        'percentual' => $totalGrafico > 0 ? ((float)$categoria['valor'] / $totalGrafico) * 100 : 0,
        'cor' => $paletaGrafico[$categoria['cor']] ?? '#64748b',
    ];
}
function brl(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Orçamento Mensal - FinMap</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/orcamento-mensal.css?v=17">

  <link rel="stylesheet" href="../assets/css/page-entrance.css?v=2">
  <link rel="stylesheet" href="../assets/css/app-topbar.css?v=13">
</head>
<body>

  <?php require __DIR__ . '/../components/app-topbar.php'; ?>

  <main class="monthly-budget-page">
    <div class="back-navigation">
      <a href="dashboard.php" class="back-btn">
        <i class="bi bi-chevron-left"></i>
        <span>Voltar</span>
      </a>
    </div>

    <section class="budget-dashboard">
      <section class="budget-dashboard__top">
        <article class="budget-hero-card">
          <?php if ($erroRenda): ?>
            <div class="alert alert-danger py-2 mb-3">
              <?= $erroRenda  ?>
            </div>
          <?php endif; ?>

          <div class="budget-hero-card__header">
            <h2>Quanto da renda já foi gasta</h2>

          <p>Compare sua renda mensal com as despesas aprovadas neste mês.</p>
          </div>

          <div class="budget-hero-card__stats">
            <article class="budget-kpi budget-kpi--main">
              <span>Renda mensal</span>
              <strong id="incomeValue"><?= brl($renda) ?></strong>
              <small>Base usada para acompanhar as despesas aprovadas.</small>
            </article>

            <div class="budget-kpi-stack">
              <article class="budget-kpi budget-kpi--expenses">
                <span>Despesas aprovadas no mês</span>
                <strong id="committedValue"><?= brl($comprometido) ?></strong>
              </article>

              <article class="budget-kpi budget-kpi--remaining <?= $saldo < 0 ? 'budget-kpi--negative' : '' ?>">
                <span>Saldo após despesas</span>
                <strong id="remainingValue" class="<?= $saldo < 0 ? 'text-danger' : '' ?>"><?= brl($saldo) ?></strong>
              </article>
            </div>
          </div>
          <div class="budget-hero-card__actions">
            <button class="budget-btn budget-btn--primary" id="openIncomeModal" type="button">
              <i class="bi bi-cash-coin"></i>
              Ajustar renda
            </button>

            
          </div>

          <?php if ($renda <= 0): ?>
            <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">
              Nenhuma renda cadastrada ainda. <a href="config-renda.php">Configure sua renda primeiro</a>.
            </p>
          <?php endif; ?>
        </article>

        <article class="budget-visual-card">
          <div class="budget-visual-card__header">
            <div>
              <h3>Despesas por categoria</h3>
              <p>Selecione uma categoria para ver sua participação no total.</p>
            </div>

            <button class="panel-action-btn" id="openDetailsModal" type="button">
              <i class="bi bi-bar-chart-line"></i>
              Detalhes
            </button>
          </div>

          <div class="budget-pie-layout">
  <div class="budget-pie-wrap">
    <svg class="budget-pie" viewBox="0 0 220 220" role="img" aria-label="Distribuição das despesas por categoria">
      <circle class="budget-pie__track" cx="110" cy="110" r="76"></circle>
      <?php $circunferencia = 2 * M_PI * 76; $offsetGrafico = 0; ?>
      <?php foreach ($dadosGraficoCategorias as $indice => $item): $comprimento = ($item['percentual'] / 100) * $circunferencia; ?>
        <circle class="budget-pie__slice" data-pie-index="<?= $indice ?>" data-pie-name="<?= htmlspecialchars($item['nome'], ENT_QUOTES) ?>" data-pie-amount="<?= htmlspecialchars(brl($item['valor']), ENT_QUOTES) ?>" data-pie-share="<?= number_format($item['percentual'], 1, ',', '.') ?>% das despesas" cx="110" cy="110" r="76"
          stroke="<?= htmlspecialchars($item['cor']) ?>"
          stroke-dasharray="<?= number_format($comprimento, 3, '.', '') ?> <?= number_format($circunferencia, 3, '.', '') ?>"
          stroke-dashoffset="-<?= number_format($offsetGrafico, 3, '.', '') ?>"
          tabindex="0" role="button"
          aria-label="<?= htmlspecialchars($item['nome']) ?>: <?= brl($item['valor']) ?>, <?= number_format($item['percentual'], 1, ',', '.') ?>% das despesas"></circle>
        <?php $offsetGrafico += $comprimento; endforeach; ?>
    </svg>
    <div class="budget-pie__center" aria-live="polite">
      <span id="pieCenterLabel">Total gasto</span>
      <strong id="pieCenterAmount"><?= brl($comprometido) ?></strong>
      <small id="pieCenterPercent"><?= $totalGrafico > 0 ? '100% das despesas' : 'Neste mês' ?></small>
    </div>
  </div>

  <div class="budget-pie__legend" aria-label="Categorias de despesa">
    <?php if (!$dadosGraficoCategorias): ?>
      <p class="budget-pie__empty">Sem despesas aprovadas por categoria neste mês.</p>
    <?php else: foreach ($dadosGraficoCategorias as $indice => $item): ?>
      <button class="budget-pie__legend-item" type="button" data-pie-index="<?= $indice ?>"
        aria-label="Ver <?= htmlspecialchars($item['nome']) ?>: <?= brl($item['valor']) ?>">
        <span class="budget-pie__legend-dot" style="--slice-color: <?= htmlspecialchars($item['cor']) ?>"></span>
        <span class="budget-pie__legend-name"><?= htmlspecialchars($item['nome']) ?></span>
        <span class="budget-pie__legend-value"><?= brl($item['valor']) ?></span>
        <small><?= number_format($item['percentual'], 1, ',', '.') ?>%</small>
      </button>
    <?php endforeach; endif; ?>
  </div>
</div>
<div class="budget-pie-footer">
  <div class="budget-pie-footer__usage">
    <div class="budget-pie-footer__usage-heading">
      <span><i class="bi bi-wallet2" aria-hidden="true"></i> Renda usada</span>
      <strong><?= round($percentual) ?>%</strong>
    </div>
    <div class="budget-pie-footer__track" role="progressbar" aria-label="Percentual da renda usado com despesas" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= number_format(min(max($percentual, 0), 100), 1, '.', '') ?>" aria-valuetext="<?= round($percentual) ?>% da renda usada">
      <span class="budget-pie-footer__fill<?= $percentual >= 100 ? ' budget-pie-footer__fill--over' : '' ?>" style="width: <?= number_format(min(max($percentual, 0), 100), 2, '.', '') ?>%"></span>
    </div>
  </div>
  <span class="budget-pie-footer__separator"></span>
  <div class="budget-pie-footer__balance">
    <span>Saldo</span>
    <strong class="<?= $saldo < 0 ? 'text-danger' : '' ?>"><?= brl($saldo) ?></strong>
  </div>
</div>
</article>
      </section>

      

      <section class="budget-dashboard__bottom budget-dashboard__bottom--balanced">
        <section class="budget-panel budget-panel--equal">
          <div class="budget-panel__header">
            <div>
              <h3>Detalhamento</h3>
            </div>

            
          </div>

          <div class="budget-category-list">
            <?php if (empty($categoriasCalculadas)): ?>
              <p class="text-muted px-3 py-4 mb-0">Nenhuma categoria de despesa cadastrada ainda.</p>
            <?php endif; ?>

            <?php foreach ($categoriasCalculadas as $c):
              $catPercent = $renda > 0 ? ($c['valor'] / $renda) * 100 : 0;
            ?>
              <article class="budget-category-item">
                <div class="budget-category-item__top">
                  <div class="budget-category-item__left">
                    <div class="budget-category-item__icon budget-category-item__icon--<?= htmlspecialchars($c['cor']) ?>">
                      <i class="bi bi-<?= htmlspecialchars($c['icone']) ?>"></i>
                    </div>
                    <div>
                      <h4><?= htmlspecialchars($c['nome']) ?></h4>
                    </div>
                  </div>

                  <div class="budget-category-item__right">
                    <strong><?= brl($c['valor']) ?></strong>
                    <span><?= number_format($catPercent, 1, ',', '.') ?>% da renda</span>
                  </div>
                </div>

                <div class="budget-category-item__bar">
                  <div class="budget-category-item__fill budget-category-item__fill--<?= htmlspecialchars($c['cor']) ?>" style="width: <?= number_format($catPercent, 1, '.', '') ?>%;"></div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="budget-panel budget-panel--equal">
          <div class="budget-panel__header">
            <div>
              <h3>Resumo do mês</h3>
            </div>

            
          </div>

          <div class="budget-reading-card budget-reading-card--expanded" style="--reading-color: <?= htmlspecialchars($status['cor']) ?>">
  <div class="budget-summary-hero">
    <div class="budget-summary-hero__icon"><i class="bi bi-activity"></i></div>
    <div><span>Situação do mês</span><strong><?= htmlspecialchars($status['label']) ?></strong></div>
    <i class="bi bi-arrow-up-right budget-summary-hero__arrow"></i>
  </div>
  <button class="budget-summary-feature" type="button" <?= $maiorValor > 0 ? 'data-pie-select="0"' : 'disabled' ?>>
    <span class="budget-summary-feature__icon"><i class="bi bi-trophy"></i></span>
    <span class="budget-summary-feature__text"><small>Maior gasto</small><strong><?= htmlspecialchars($categoriaMaisPesada) ?></strong></span>
    <strong class="budget-summary-feature__amount"><?= $maiorValor > 0 ? brl($maiorValor) : '—' ?></strong>
  </button>
  <div class="budget-summary-next">
    <span class="budget-summary-next__icon"><i class="bi bi-lightbulb"></i></span>
    <div><small>Próximo passo</small><p><?= htmlspecialchars($status['sugestao']) ?></p></div>
  </div>
</div>
        </section>
      </section>
    </section>
  </main>

  <!-- MODAL RENDA -->
  <div class="budget-modal-overlay" id="incomeModal">
    <div class="budget-modal budget-modal--medium">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-cash-coin"></i>
          </div>
          <div>
            <h3>Ajustar renda mensal</h3>
            <p>Atualize a base usada para calcular o comprometimento do seu orçamento.</p>
          </div>
        </div>

        <button class="budget-modal__close" data-close-budget-modal="incomeModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form method="post" action="orcamento-mensal.php">
        <input type="hidden" name="acao" value="atualizar_renda">

        <div class="budget-modal__body">
          <div class="budget-form-card">
            <label class="budget-field budget-field--money">
              <span>Nova renda mensal</span>
              <input name="renda" id="incomeInput" class="finmap-money-input" type="text" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00" value="<?= brl($renda) ?>">
            </label>

            <button class="budget-btn budget-btn--primary budget-btn--full" type="submit">
              Salvar renda
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL DETALHES (só leitura) -->
  <div class="budget-modal-overlay" id="detailsModal">
    <div class="budget-modal">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-bar-chart-line"></i>
          </div>
          <div>
            <h3>Detalhes do orçamento</h3>
            <p>Entenda melhor como sua renda está distribuída no mês.</p>
          </div>
        </div>

        <button class="budget-modal__close" data-close-budget-modal="detailsModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="budget-modal__body">
        <div class="details-grid">
          <article class="details-box">
            <span>Renda mensal</span>
            <strong><?= brl($renda) ?></strong>
            <p>Valor base usado como referência do orçamento atual.</p>
          </article>

          <article class="details-box">
            <span>Despesas aprovadas no mês</span>
            <strong><?= brl($comprometido) ?></strong>
            <p>Todas as despesas aprovadas neste mês, inclusive as sem categoria.</p>
          </article>

          <article class="details-box">
            <span>Renda usada</span>
            <strong><?= round($percentual) ?>%</strong>
            <p>Percentual da sua renda já absorvido por despesas no período.</p>
          </article>

          <article class="details-box">
            <span>Saldo após despesas</span>
            <strong><?= brl($saldo) ?></strong>
            <p>Diferença entre renda mensal e despesas reais.</p>
          </article>
        </div>
      </div>
    </div>
  </div>

  <!-- PAINEL DE NOTIFICAÇÕES -->
  <div class="notif-overlay" id="notifOverlay"></div>

  <aside class="notif-panel" id="notifPanel" aria-label="Painel de notificações">
    <div class="notif-panel__header">
      <div>
        <h3>Notificações</h3>
        <p>Alertas, metas e movimentações importantes</p>
      </div>

      <button class="notif-panel__close" id="closeNotificationsPanel" type="button" aria-label="Fechar notificações">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="notif-panel__tabs">
      <button class="notif-tab active" type="button">Todos</button>
      <button class="notif-tab" type="button">Alertas</button>
      <button class="notif-tab" type="button">Metas</button>
      <button class="notif-tab" type="button">Gastos</button>
    </div>

    <div class="notif-panel__list">
      <article class="notif-card notif-card--danger">
        <div class="notif-card__icon">
          <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="notif-card__content">
          <h4>Resumo do orçamento</h4>
          <p><?= htmlspecialchars($status['texto']) ?></p>
          <span>Agora mesmo</span>
        </div>
      </article>
    </div>
  </aside>

  <!-- MODAL IA -->
  <?php require __DIR__ . '/../components/ai-assistant.php'; ?>

  <script>
    const body = document.body;

    function hasClassSafe(element, className) {
      return element && element.classList.contains(className);
    }

    function lockBody() { body.style.overflow = "hidden"; }

    function unlockBody() {
      const hasOpenModal =
        document.querySelector(".budget-modal-overlay.active") ||
        hasClassSafe(document.getElementById("notifPanel"), "active") ||
        hasClassSafe(document.getElementById("aiModal"), "active");
      body.style.overflow = hasOpenModal ? "hidden" : "";
    }

    function openBudgetModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.add("active");
      lockBody();
    }

    function closeBudgetModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.remove("active");
      unlockBody();
    }

    const budgetModalMap = {
      openIncomeModal: "incomeModal",
      openDetailsModal: "detailsModal"
    };

    Object.entries(budgetModalMap).forEach(([buttonId, modalId]) => {
      const button = document.getElementById(buttonId);
      if (!button) return;
      button.addEventListener("click", () => openBudgetModal(modalId));
    });

    document.querySelectorAll("[data-close-budget-modal]").forEach((button) => {
      button.addEventListener("click", () => {
        closeBudgetModal(button.getAttribute("data-close-budget-modal"));
      });
    });

    document.querySelectorAll(".budget-modal-overlay").forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
          overlay.classList.remove("active");
          unlockBody();
        }
      });
    });


    function formatCurrencyInput(value) {
      const digits = String(value || "").replace(/\D/g, "");
      if (!digits) return "R$ 0,00";
      let formatado = (Number(digits) / 100).toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      return "R$ " + formatado;
    }

    const incomeInput = document.getElementById("incomeInput");
    if (incomeInput) {
      incomeInput.addEventListener("input", function () {
        this.value = formatCurrencyInput(this.value);
      });
    }

    function openNotifications() {
      document.getElementById("notifPanel")?.classList.add("active");
      document.getElementById("notifOverlay")?.classList.add("active");
      lockBody();
    }

    function closeNotifications() {
      document.getElementById("notifPanel")?.classList.remove("active");
      document.getElementById("notifOverlay")?.classList.remove("active");
      unlockBody();
    }

    document.getElementById("openNotificationsPanel").addEventListener("click", openNotifications);
    document.getElementById("closeNotificationsPanel").addEventListener("click", closeNotifications);
    document.getElementById("notifOverlay").addEventListener("click", closeNotifications);

    function openAiModal() {
      document.getElementById("aiModal")?.classList.add("active");
      lockBody();
    }
    function closeAiModal() {
      document.getElementById("aiModal")?.classList.remove("active");
      unlockBody();
    }

    document.getElementById("openAiModal").addEventListener("click", openAiModal);
    document.getElementById("closeAiModal").addEventListener("click", closeAiModal);
    document.getElementById("aiModal").addEventListener("click", (e) => {
      if (e.target.id === "aiModal") closeAiModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        document.querySelectorAll(".budget-modal-overlay.active").forEach((modal) => {
          modal.classList.remove("active");
        });
        closeNotifications();
        closeAiModal();
        unlockBody();
      }
    });
    const pieSlices = [...document.querySelectorAll(".budget-pie__slice")];
    const pieTargets = [...document.querySelectorAll(".budget-pie__legend-item, .budget-pie__slice")];
    const pieLabel = document.getElementById("pieCenterLabel");
    const pieAmount = document.getElementById("pieCenterAmount");
    const piePercent = document.getElementById("pieCenterPercent");
    let selectedPieIndex = null;

    function showPieCategory(index) {
      const slice = pieSlices.find((item) => Number(item.dataset.pieIndex) === Number(index));
      if (!slice) return;
      pieLabel.textContent = slice.dataset.pieName;
      pieAmount.textContent = slice.dataset.pieAmount;
      piePercent.textContent = slice.dataset.pieShare;
      pieSlices.forEach((item) => item.classList.toggle("is-dimmed", Number(item.dataset.pieIndex) !== Number(index)));
      document.querySelectorAll(".budget-pie__legend-item").forEach((item) => {
        item.classList.toggle("is-active", Number(item.dataset.pieIndex) === Number(index));
      });
    }

    function resetPiePreview() {
      if (selectedPieIndex !== null) return showPieCategory(selectedPieIndex);
      pieLabel.textContent = "Total gasto";
      pieAmount.textContent = "<?= brl($comprometido) ?>";
      piePercent.textContent = "<?= $totalGrafico > 0 ? '100% das despesas' : 'Neste mês' ?>";
      pieSlices.forEach((item) => item.classList.remove("is-dimmed"));
      document.querySelectorAll(".budget-pie__legend-item").forEach((item) => item.classList.remove("is-active"));
    }

    pieTargets.forEach((target) => {
      const index = Number(target.dataset.pieIndex);
      target.addEventListener("pointerenter", () => showPieCategory(index));
      target.addEventListener("pointerleave", resetPiePreview);
      target.addEventListener("focus", () => showPieCategory(index));
      target.addEventListener("blur", resetPiePreview);
      target.addEventListener("click", () => {
        selectedPieIndex = selectedPieIndex === index ? null : index;
        resetPiePreview();
      });
      if (target.classList.contains("budget-pie__slice")) {
        target.addEventListener("keydown", (event) => {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            target.click();
          }
        });
      }
    });

    document.querySelectorAll("[data-pie-select]").forEach((button) => {
      button.addEventListener("click", () => {
        selectedPieIndex = Number(button.dataset.pieSelect);
        showPieCategory(selectedPieIndex);
        document.querySelector(".budget-visual-card")?.scrollIntoView({ behavior: "smooth", block: "center" });
      });
    });
  </script>

  <script src="../assets/js/page-entrance.js?v=3" defer></script>
  <script src="../assets/js/notifications.js?v=1" defer></script>
  <script src="../assets/js/ai-assistant.js?v=1" defer></script>
</body>
</html>
