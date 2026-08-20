<?php
// ============================================================
// BLOCO PHP — busca lançamentos pendentes/revisados e as regras
// ============================================================
session_start();
include '../config/conn.php';

$usuario_id = $_SESSION['usuario_id'] ?? 1;

// --- Avatar do usuário ---
$stmt = $conn->prepare("SELECT avatar_iniciais FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();
$iniciais = $usuario['avatar_iniciais'] ?? 'US';

// --- Regras de revisão (cria com padrão se ainda não existir) ---
$stmt = $conn->prepare("SELECT * FROM revisao_regras WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$regras = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$regras) {
    $stmt = $conn->prepare("INSERT INTO revisao_regras (usuario_id) VALUES (?)");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();

    $regras = [
        'priorizar_ocr_baixa_confianca' => 1,
        'ocultar_aprovados' => 1,
        'limite_confianca_percentual' => 80
    ];
}

// --- Todos os lançamentos aguardando revisão, inclusive os manuais ---
$stmt = $conn->prepare("
    SELECT t.id, t.descricao, t.valor, t.origem, t.status, t.confianca_percentual,
           t.observacao_captura, t.data_transacao, c.nome AS categoria_nome
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ?
    ORDER BY t.data_transacao DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$transacoesResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Metadados visuais por origem (ícone, cor, rótulo)
$sourceMeta = [
    'manual'     => ['label' => 'Manual', 'badge' => 'green', 'icon' => 'pencil-square', 'padrao' => 'Lançamento informado manualmente'],
    'ocr'        => ['label' => 'OCR', 'badge' => 'purple', 'icon' => 'receipt-cutoff', 'padrao' => 'Capturado de nota fiscal'],
    'sms'        => ['label' => 'SMS', 'badge' => 'green', 'icon' => 'chat-square-text', 'padrao' => 'Mensagem bancária'],
    'importacao' => ['label' => 'Importação', 'badge' => 'blue', 'icon' => 'file-earmark-arrow-up', 'padrao' => 'Arquivo importado']
];

// Mapeia status do banco (português) pro que o JS já usa (inglês)
$statusMap = ['pendente' => 'pending', 'aprovado' => 'approved', 'rejeitado' => 'rejected'];

$launches = [];
foreach ($transacoesResult as $t) {
    $launches[] = [
        'id' => (int) $t['id'],
        'description' => $t['descricao'],
        'amount' => (float) $t['valor'],
        'source' => $t['origem'],
        'sourceLabel' => $sourceMeta[$t['origem']]['label'] ?? ucfirst($t['origem']),
        'category' => $t['categoria_nome'] ?? 'Sem categoria',
        'confidence' => $t['origem'] === 'manual' ? 100 : ($t['confianca_percentual'] !== null ? (int) $t['confianca_percentual'] : 0),
        'status' => $statusMap[$t['status']] ?? 'pending',
        'note' => $t['observacao_captura'] ?? ($sourceMeta[$t['origem']]['padrao'] ?? ''),
        'date' => date('d/m/Y', strtotime($t['data_transacao']))
    ];
}

// --- Contadores de hoje (aprovados/rejeitados) ---
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'aprovado' AND DATE(atualizado_em) = CURDATE() THEN 1 ELSE 0 END) AS aprovados_hoje,
        SUM(CASE WHEN status = 'rejeitado' AND DATE(atualizado_em) = CURDATE() THEN 1 ELSE 0 END) AS rejeitados_hoje
    FROM transacoes
    WHERE usuario_id = ?
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$contadores = $stmt->get_result()->fetch_assoc();
$stmt->close();

$aprovadosHoje = (int) ($contadores['aprovados_hoje'] ?? 0);
$rejeitadosHoje = (int) ($contadores['rejeitados_hoje'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Revisar Lançamentos - FinMap</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/revisar-lancamentos.css">
</head>
<body>

  <header class="topbar">
    <div class="topbar-left">
      <div class="brand">
        <div class="brand-mark">
          <i class="bi bi-graph-up-arrow"></i>
        </div>

        <div class="brand-copy">
          <h1>FinMap</h1>
          <p>Revisão inteligente de lançamentos</p>
        </div>
      </div>
    </div>

    <div class="topbar-right">
      <button class="ai-btn" id="openAiModal" type="button" aria-label="Abrir Assistente IA">
        <i class="bi bi-stars"></i>
        <span>Assistente IA</span>
      </button>

      <button class="icon-plain notification-btn" id="openNotificationsPanel" type="button" aria-label="Notificações">
        <i class="bi bi-bell"></i>
        <span class="notification-dot"></span>
      </button>

      <button class="profile-avatar" type="button" aria-label="Perfil">
        <?= htmlspecialchars($iniciais) ?>
      </button>
    </div>
  </header>

  <main class="review-page">
    <div class="back-navigation">
      <a href="dashboard.php" class="back-btn">
        <i class="bi bi-chevron-left"></i>
        <span>Voltar</span>
      </a>
    </div>

    <section class="review-dashboard">
      <section class="review-header-minimal">
        <div class="review-header-minimal__content">
          <h2>Revisar lançamentos</h2>
          <p>Valide capturas automáticas vindas de OCR, SMS ou importação.</p>
        </div>

        <div class="review-header-minimal__actions">
          <button class="review-btn review-btn--primary" id="approveSelectedBtn" type="button">
            <i class="bi bi-check2-circle"></i>
            Aprovar selecionados
          </button>

          <button class="review-btn review-btn--secondary" id="openRulesModal" type="button">
            <i class="bi bi-sliders"></i>
            Ajustar regras
          </button>
        </div>
      </section>

      <section class="review-panel">
        <div class="review-panel__header">
          <div>
            <h3>Fila de revisão</h3>
            <p>Valide, edite ou descarte cada lançamento detectado pelo sistema.</p>
          </div>

          <div class="review-panel__actions">
            <button class="filter-chip active" type="button" data-filter="all">Todos</button>
            <button class="filter-chip" type="button" data-filter="ocr">OCR</button>
            <button class="filter-chip" type="button" data-filter="sms">SMS</button>
            <button class="filter-chip" type="button" data-filter="manual">Manual</button>
            <button class="filter-chip" type="button" data-filter="importacao">Importação</button>
          </div>
        </div>

        <div class="review-list" id="reviewList">
          <?php if (empty($launches)): ?>
            <p style="padding: 24px 0; color: #888;">
              Nenhum lançamento para revisar encontrado ainda.
            </p>
          <?php else: ?>
            <?php foreach ($launches as $l):
              $meta = $sourceMeta[$l['source']] ?? ['badge' => 'purple', 'icon' => 'receipt-cutoff'];
            ?>
              <article class="review-item" data-id="<?= $l['id'] ?>" data-source="<?= htmlspecialchars($l['source']) ?>" data-status="<?= htmlspecialchars($l['status']) ?>" data-confidence="<?= $l['confidence'] ?>">
                <div class="review-item__select">
                  <label class="review-check">
                    <input type="checkbox" class="launch-checkbox">
                    <span></span>
                  </label>
                </div>

                <div class="review-item__main">
                  <div class="review-item__top">
                    <div class="review-item__left">
                      <div class="review-item__icon review-item__icon--<?= $meta['badge'] ?>">
                        <i class="bi bi-<?= $meta['icon'] ?>"></i>
                      </div>

                      <div class="review-item__info">
                        <div class="review-item__title-row">
                          <h4><?= htmlspecialchars($l['description']) ?></h4>
                          <span class="review-badge review-badge--<?= $meta['badge'] ?>"><?= htmlspecialchars($l['sourceLabel']) ?></span>
                        </div>
                        <p><?= htmlspecialchars($l['note']) ?> • <?= htmlspecialchars($l['category']) ?> • <?= htmlspecialchars($l['date']) ?></p>
                      </div>
                    </div>

                    <div class="review-item__amount">
                      <strong>R$ <?= number_format($l['amount'], 2, ',', '.') ?></strong>
                      <span>Confiança: <?= $l['confidence'] ?>%</span>
                    </div>
                  </div>

                  <div class="review-item__meta">
                    <span><i class="bi bi-tag"></i> <?= htmlspecialchars($l['category']) ?></span>
                    <span><i class="bi bi-clock"></i> <?= htmlspecialchars($l['date']) ?></span>
                    <span><i class="bi bi-<?= $l['confidence'] >= 80 ? 'check2-circle' : 'exclamation-triangle' ?>"></i> <?= htmlspecialchars($l['note']) ?></span>
                  </div>

                  <div class="review-item__actions">
                    <button class="item-action-btn item-action-btn--ghost" type="button" data-open-details="<?= $l['id'] ?>">
                      <i class="bi bi-eye"></i>
                      Ver detalhes
                    </button>

                    <button class="item-action-btn item-action-btn--edit" type="button" data-open-edit="<?= $l['id'] ?>">
                      <i class="bi bi-pencil-square"></i>
                      Editar
                    </button>

                    <button class="item-action-btn item-action-btn--danger" type="button" data-reject="<?= $l['id'] ?>">
                      <i class="bi bi-x-circle"></i>
                      Rejeitar
                    </button>

                    <button class="item-action-btn item-action-btn--success" type="button" data-approve="<?= $l['id'] ?>">
                      <i class="bi bi-check-circle"></i>
                      Aprovar
                    </button>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </section>
  </main>

  <!-- MODAL DETALHES -->
  <div class="review-modal-overlay" id="detailsModal">
    <div class="review-modal">
      <div class="review-modal__header">
        <div class="review-modal__title-group">
          <div class="review-modal__icon">
            <i class="bi bi-eye"></i>
          </div>
          <div>
            <h3>Detalhes do lançamento</h3>
            <p>Confira os dados capturados antes de aprovar ou editar.</p>
          </div>
        </div>

        <button class="review-modal__close" id="closeDetailsModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="review-modal__body">
        <div class="details-grid">
          <article class="details-box">
            <span>Descrição</span>
            <strong id="detailDescription">-</strong>
          </article>

          <article class="details-box">
            <span>Valor</span>
            <strong id="detailAmount">-</strong>
          </article>

          <article class="details-box">
            <span>Origem</span>
            <strong id="detailSource">-</strong>
          </article>

          <article class="details-box">
            <span>Confiança</span>
            <strong id="detailConfidence">-</strong>
          </article>

          <article class="details-box">
            <span>Categoria</span>
            <strong id="detailCategory">-</strong>
          </article>

          <article class="details-box">
            <span>Status</span>
            <strong id="detailStatus">Pendente</strong>
          </article>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL EDIÇÃO -->
  <div class="review-modal-overlay" id="editModal">
    <div class="review-modal review-modal--medium">
      <div class="review-modal__header">
        <div class="review-modal__title-group">
          <div class="review-modal__icon">
            <i class="bi bi-pencil-square"></i>
          </div>
          <div>
            <h3>Editar lançamento</h3>
            <p>Ajuste os dados da captura antes de validar.</p>
          </div>
        </div>

        <button class="review-modal__close" id="closeEditModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="review-modal__body">
        <div class="edit-form-grid">
          <label class="review-field">
            <span>Descrição</span>
            <input type="text" id="editDescriptionInput" placeholder="Descrição do lançamento">
          </label>

          <label class="review-field">
            <span>Valor</span>
            <input type="number" id="editAmountInput" step="0.01" min="0">
          </label>

          <label class="review-field">
            <span>Categoria</span>
            <input type="text" id="editCategoryInput" placeholder="Categoria">
          </label>

          <label class="review-field">
            <span>Origem</span>
            <input type="text" id="editSourceInput" placeholder="Origem" disabled>
          </label>

          <button class="review-btn review-btn--primary review-btn--full" id="saveEditBtn" type="button">
            Salvar alterações
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL REGRAS -->
  <div class="review-modal-overlay" id="rulesModal">
    <div class="review-modal review-modal--medium">
      <div class="review-modal__header">
        <div class="review-modal__title-group">
          <div class="review-modal__icon">
            <i class="bi bi-sliders"></i>
          </div>
          <div>
            <h3>Ajustar regras de revisão</h3>
            <p>Defina como o FinMap deve priorizar e sinalizar lançamentos automáticos.</p>
          </div>
        </div>

        <button class="review-modal__close" id="closeRulesModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="review-modal__body">
        <div class="rules-list">
          <div class="rules-row">
            <div>
              <h4>Priorizar OCR com baixa confiança</h4>
              <p>Destaca capturas de OCR abaixo do limite ideal.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="togglePrioritizeOCR" <?= $regras['priorizar_ocr_baixa_confianca'] ? 'checked' : '' ?>>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="rules-row">
            <div>
              <h4>Ocultar lançamentos aprovados</h4>
              <p>Remove automaticamente da fila os itens já validados.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="toggleHideApproved" <?= $regras['ocultar_aprovados'] ? 'checked' : '' ?>>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="rules-row">
            <div>
              <h4>Faixa de confiança ideal</h4>
              <p>Capturas abaixo desse valor ficam mais sensíveis para revisão.</p>
            </div>
            <strong id="confidenceThresholdText"><?= (int) $regras['limite_confianca_percentual'] ?>%</strong>
          </div>

          <label class="review-field">
            <span>Limite de confiança (%)</span>
            <input type="number" id="confidenceThresholdInput" min="1" max="100" value="<?= (int) $regras['limite_confianca_percentual'] ?>">
          </label>

          <button class="review-btn review-btn--primary review-btn--full" id="saveRulesBtn" type="button">
            Aplicar regras
          </button>
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
      <button class="notif-tab" type="button">OCR</button>
      <button class="notif-tab" type="button">SMS</button>
    </div>

    <div class="notif-panel__list">
      <article class="notif-card notif-card--danger">
        <div class="notif-card__icon">
          <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="notif-card__content">
          <h4>OCR com baixa confiança</h4>
          <p>Duas capturas de OCR exigem revisão manual prioritária.</p>
          <span>Agora mesmo</span>
        </div>
      </article>

      <article class="notif-card notif-card--success">
        <div class="notif-card__icon">
          <i class="bi bi-check2-circle"></i>
        </div>
        <div class="notif-card__content">
          <h4>Fila avançando bem</h4>
          <p>Você já validou <?= $aprovadosHoje ?> lançamentos hoje.</p>
          <span>Hoje, 09:20</span>
        </div>
      </article>

      <article class="notif-card notif-card--warning">
        <div class="notif-card__icon">
          <i class="bi bi-receipt"></i>
        </div>
        <div class="notif-card__content">
          <h4>Categoria incerta</h4>
          <p>Uma captura automática pode ter sido categorizada incorretamente.</p>
          <span>Hoje, 08:05</span>
        </div>
      </article>

      <article class="notif-card notif-card--info">
        <div class="notif-card__icon">
          <i class="bi bi-stars"></i>
        </div>
        <div class="notif-card__content">
          <h4>Sugestão da IA</h4>
          <p>Priorize validações de OCR antes de revisar importações em lote.</p>
          <span>Ontem</span>
        </div>
      </article>
    </div>
  </aside>

  <!-- MODAL IA -->
  <div class="ai-modal-overlay" id="aiModal">
    <div class="ai-modal">
      <div class="ai-modal__header">
        <div class="ai-modal__title-group">
          <div class="ai-modal__icon">
            <i class="bi bi-stars"></i>
          </div>

          <div>
            <h3>Assistente IA FinMap</h3>
            <p>Análise inteligente, sugestões rápidas e apoio financeiro em tempo real.</p>
          </div>
        </div>

        <button class="ai-modal__close" id="closeAiModal" type="button" aria-label="Fechar assistente IA">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="ai-modal__body">
        <aside class="ai-modal__sidebar">
          <div class="ai-summary-card ai-summary-card--green">
            <div class="ai-summary-card__top">
              <span class="ai-summary-card__label">Análise geral</span>
              <i class="bi bi-activity"></i>
            </div>
            <strong>Fila com boa consistência</strong>
            <p>A maior parte dos lançamentos está consistente, mas OCRs com confiança baixa ainda precisam de revisão mais cuidadosa.</p>
          </div>

          <div class="ai-summary-card ai-summary-card--soft">
            <div class="ai-summary-card__top">
              <span class="ai-summary-card__label">Sugestão principal</span>
              <i class="bi bi-lightbulb"></i>
            </div>
            <strong>Validar OCR primeiro</strong>
            <p>Comece pelas capturas com menor confiança para reduzir erros na base financeira.</p>
          </div>

          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button">Analisar fila atual</button>
            <button class="ai-shortcut" type="button">Quais itens revisar primeiro?</button>
            <button class="ai-shortcut" type="button">Comparar fontes</button>
            <button class="ai-shortcut" type="button">Criar regra automática</button>
          </div>
        </aside>

        <section class="ai-chat-area">
          <div class="ai-chat-area__messages">
            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Analisei sua fila de revisão. Hoje as capturas mais sensíveis estão nas entradas de OCR com confiança abaixo de 80%.
              </div>
            </div>

            <div class="ai-message ai-message--user">
              <div class="ai-message__bubble">
                Por onde eu devo começar?
              </div>
            </div>

            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Comece pelos OCRs com descrição ou valor incerto. Depois avance para SMS e importações, que tendem a ter consistência maior.
              </div>
            </div>
          </div>

          <form class="ai-chat-area__input">
            <button class="ai-input-action" type="button" aria-label="Anexar">
              <i class="bi bi-plus-lg"></i>
            </button>

            <input type="text" placeholder="Pergunte algo ao Assistente IA">

            <button class="ai-send-btn" type="submit" aria-label="Enviar mensagem">
              <i class="bi bi-send-fill"></i>
            </button>
          </form>
        </section>
      </div>
    </div>
  </div>

  <script>
    const body = document.body;

    // ATENÇÃO: os lançamentos agora vêm do banco (via PHP), não são
    // mais fixos no JavaScript. O approvedToday/rejectedToday também
    // vêm de uma contagem real no banco.
    const state = {
      filter: "all",
      hideApproved: <?= $regras['ocultar_aprovados'] ? 'true' : 'false' ?>,
      prioritizeOCR: <?= $regras['priorizar_ocr_baixa_confianca'] ? 'true' : 'false' ?>,
      confidenceThreshold: <?= (int) $regras['limite_confianca_percentual'] ?>,
      selectedIds: [],
      currentEditId: null,
      launches: <?= json_encode($launches) ?>,
      approvedToday: <?= $aprovadosHoje ?>,
      rejectedToday: <?= $rejeitadosHoje ?>
    };

    function formatBRL(value) {
      return value.toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL"
      });
    }

    function hasClassSafe(element, className) {
      return element && element.classList.contains(className);
    }

    function lockBody() {
      body.style.overflow = "hidden";
    }

    function unlockBody() {
      const hasOpenModal =
        hasClassSafe(document.getElementById("detailsModal"), "active") ||
        hasClassSafe(document.getElementById("editModal"), "active") ||
        hasClassSafe(document.getElementById("rulesModal"), "active") ||
        hasClassSafe(document.getElementById("notifPanel"), "active") ||
        hasClassSafe(document.getElementById("aiModal"), "active");

      body.style.overflow = hasOpenModal ? "hidden" : "";
    }

    function openReviewModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.add("active");
      lockBody();
    }

    function closeReviewModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.remove("active");
      unlockBody();
    }

    function openNotifications() {
      const notifPanel = document.getElementById("notifPanel");
      const notifOverlay = document.getElementById("notifOverlay");

      if (!notifPanel || !notifOverlay) return;
      notifPanel.classList.add("active");
      notifOverlay.classList.add("active");
      lockBody();
    }

    function closeNotifications() {
      const notifPanel = document.getElementById("notifPanel");
      const notifOverlay = document.getElementById("notifOverlay");

      if (notifPanel) notifPanel.classList.remove("active");
      if (notifOverlay) notifOverlay.classList.remove("active");
      unlockBody();
    }

    function openAiModal() {
      const aiModal = document.getElementById("aiModal");
      if (!aiModal) return;
      aiModal.classList.add("active");
      lockBody();
    }

    function closeAiModal() {
      const aiModal = document.getElementById("aiModal");
      if (!aiModal) return;
      aiModal.classList.remove("active");
      unlockBody();
    }

    function getFilteredLaunches() {
      let launches = state.launches.filter(item => item.status === "pending" || !state.hideApproved);

      if (state.hideApproved) {
        launches = launches.filter(item => item.status === "pending");
      }

      if (state.filter !== "all") {
        launches = launches.filter(item => item.source === state.filter);
      }

      if (state.prioritizeOCR) {
        launches = launches.sort((a, b) => {
          const aPriority = a.source === "ocr" && a.confidence < state.confidenceThreshold ? 0 : 1;
          const bPriority = b.source === "ocr" && b.confidence < state.confidenceThreshold ? 0 : 1;

          if (aPriority !== bPriority) return aPriority - bPriority;
          return a.confidence - b.confidence;
        });
      }

      return launches;
    }

    function updateFilterButtons() {
      document.querySelectorAll(".filter-chip").forEach((button) => {
        button.classList.toggle("active", button.getAttribute("data-filter") === state.filter);
      });
    }

    function updateCheckboxesFromState() {
      document.querySelectorAll(".review-item").forEach((item) => {
        const id = Number(item.getAttribute("data-id"));
        const checkbox = item.querySelector(".launch-checkbox");
        if (checkbox) checkbox.checked = state.selectedIds.includes(id);
      });
    }

    function refreshListVisibility() {
      const filteredIds = new Set(getFilteredLaunches().map(item => item.id));

      document.querySelectorAll(".review-item").forEach((element) => {
        const id = Number(element.getAttribute("data-id"));
        const launch = state.launches.find(item => item.id === id);
        const shouldShow = launch && filteredIds.has(id);

        element.style.display = shouldShow ? "" : "none";
      });
    }

    function updateUI() {
      document.getElementById("approveSelectedBtn").innerHTML = `
        <i class="bi bi-check2-circle"></i>
        Aprovar selecionados (${state.selectedIds.length})
      `;

      updateFilterButtons();
      updateCheckboxesFromState();
      refreshListVisibility();
    }

    function openDetails(id) {
      const launch = state.launches.find(item => item.id === id);
      if (!launch) return;

      document.getElementById("detailDescription").textContent = launch.description;
      document.getElementById("detailAmount").textContent = formatBRL(launch.amount);
      document.getElementById("detailSource").textContent = launch.sourceLabel;
      document.getElementById("detailConfidence").textContent = launch.confidence + "%";
      document.getElementById("detailCategory").textContent = launch.category;
      document.getElementById("detailStatus").textContent =
        launch.status === "approved" ? "Aprovado" :
        launch.status === "rejected" ? "Rejeitado" : "Pendente";

      openReviewModal("detailsModal");
    }

    function openEdit(id) {
      const launch = state.launches.find(item => item.id === id);
      if (!launch) return;

      state.currentEditId = id;
      document.getElementById("editDescriptionInput").value = launch.description;
      document.getElementById("editAmountInput").value = launch.amount;
      document.getElementById("editCategoryInput").value = launch.category;
      document.getElementById("editSourceInput").value = launch.sourceLabel;

      openReviewModal("editModal");
    }

    // ATENÇÃO: as 3 funções abaixo (approveLaunch, rejectLaunch,
    // approveSelected) agora chamam o endpoint revisar-acao.php pra
    // gravar a mudança de status no banco de verdade.
    async function enviarAcao(payload) {
      try {
        const response = await fetch("revisar-acao.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });
        return await response.json();
      } catch (error) {
        console.error("Erro ao comunicar com o servidor:", error);
        return { sucesso: false };
      }
    }

    async function approveLaunch(id) {
      const launch = state.launches.find(item => item.id === id);
      if (!launch || launch.status !== "pending") return;

      const resultado = await enviarAcao({ acao: "aprovar", id });
      if (!resultado.sucesso) {
        alert("Não foi possível aprovar esse lançamento.");
        return;
      }

      launch.status = "approved";
      state.approvedToday += 1;
      state.selectedIds = state.selectedIds.filter(selectedId => selectedId !== id);
      updateUI();
    }

    async function rejectLaunch(id) {
      const launch = state.launches.find(item => item.id === id);
      if (!launch || launch.status !== "pending") return;

      const resultado = await enviarAcao({ acao: "rejeitar", id });
      if (!resultado.sucesso) {
        alert("Não foi possível rejeitar esse lançamento.");
        return;
      }

      launch.status = "rejected";
      state.rejectedToday += 1;
      state.selectedIds = state.selectedIds.filter(selectedId => selectedId !== id);
      updateUI();
    }

    async function approveSelected() {
      const idsToApprove = [...state.selectedIds];
      for (const id of idsToApprove) {
        await approveLaunch(id);
      }
      state.selectedIds = [];
      updateUI();
    }

    document.querySelectorAll(".filter-chip").forEach((button) => {
      button.addEventListener("click", () => {
        state.filter = button.getAttribute("data-filter");
        updateUI();
      });
    });

    document.querySelectorAll(".launch-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", (e) => {
        const item = e.target.closest(".review-item");
        if (!item) return;

        const id = Number(item.getAttribute("data-id"));

        if (e.target.checked) {
          if (!state.selectedIds.includes(id)) state.selectedIds.push(id);
        } else {
          state.selectedIds = state.selectedIds.filter(selectedId => selectedId !== id);
        }

        updateUI();
      });
    });

    document.querySelectorAll("[data-open-details]").forEach((button) => {
      button.addEventListener("click", () => {
        openDetails(Number(button.getAttribute("data-open-details")));
      });
    });

    document.querySelectorAll("[data-open-edit]").forEach((button) => {
      button.addEventListener("click", () => {
        openEdit(Number(button.getAttribute("data-open-edit")));
      });
    });

    document.querySelectorAll("[data-approve]").forEach((button) => {
      button.addEventListener("click", () => {
        approveLaunch(Number(button.getAttribute("data-approve")));
      });
    });

    document.querySelectorAll("[data-reject]").forEach((button) => {
      button.addEventListener("click", () => {
        rejectLaunch(Number(button.getAttribute("data-reject")));
      });
    });

    const approveSelectedBtn = document.getElementById("approveSelectedBtn");
    if (approveSelectedBtn) {
      approveSelectedBtn.addEventListener("click", approveSelected);
    }

    const openRulesModal = document.getElementById("openRulesModal");
    if (openRulesModal) {
      openRulesModal.addEventListener("click", () => openReviewModal("rulesModal"));
    }

    const closeDetailsModal = document.getElementById("closeDetailsModal");
    const closeEditModal = document.getElementById("closeEditModal");
    const closeRulesModal = document.getElementById("closeRulesModal");

    if (closeDetailsModal) closeDetailsModal.addEventListener("click", () => closeReviewModal("detailsModal"));
    if (closeEditModal) closeEditModal.addEventListener("click", () => closeReviewModal("editModal"));
    if (closeRulesModal) closeRulesModal.addEventListener("click", () => closeReviewModal("rulesModal"));

    document.querySelectorAll(".review-modal-overlay").forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
          overlay.classList.remove("active");
          unlockBody();
        }
      });
    });

    // ATENÇÃO: salvar edição agora persiste no banco via revisar-acao.php
    const saveEditBtn = document.getElementById("saveEditBtn");
    if (saveEditBtn) {
      saveEditBtn.addEventListener("click", async () => {
        const launch = state.launches.find(item => item.id === state.currentEditId);
        if (!launch) return;

        const novaDescricao = document.getElementById("editDescriptionInput").value.trim() || launch.description;
        const novoValor = parseFloat(document.getElementById("editAmountInput").value) || launch.amount;
        const novaCategoria = document.getElementById("editCategoryInput").value.trim() || launch.category;

        const resultado = await enviarAcao({
          acao: "editar",
          id: launch.id,
          descricao: novaDescricao,
          valor: novoValor,
          categoria: novaCategoria
        });

        if (!resultado.sucesso) {
          alert("Não foi possível salvar as alterações.");
          return;
        }

        launch.description = novaDescricao;
        launch.amount = novoValor;
        launch.category = novaCategoria;

        // Atualiza o card na tela também (o protótipo original não fazia isso)
        const item = document.querySelector(`.review-item[data-id="${launch.id}"]`);
        if (item) {
          const titleEl = item.querySelector(".review-item__title-row h4");
          const amountEl = item.querySelector(".review-item__amount strong");
          if (titleEl) titleEl.textContent = novaDescricao;
          if (amountEl) amountEl.textContent = formatBRL(novoValor);
        }

        closeReviewModal("editModal");
        updateUI();
      });
    }

    // ATENÇÃO: salvar regras agora persiste no banco via
    // atualizar-regras-revisao.php
    const saveRulesBtn = document.getElementById("saveRulesBtn");
    if (saveRulesBtn) {
      saveRulesBtn.addEventListener("click", async () => {
        const thresholdInput = document.getElementById("confidenceThresholdInput");
        const threshold = parseInt(thresholdInput.value, 10);

        if (!isNaN(threshold) && threshold > 0 && threshold <= 100) {
          state.confidenceThreshold = threshold;
        }

        state.prioritizeOCR = document.getElementById("togglePrioritizeOCR").checked;
        state.hideApproved = document.getElementById("toggleHideApproved").checked;
        document.getElementById("confidenceThresholdText").textContent = state.confidenceThreshold + "%";

        try {
          await fetch("atualizar-regras-revisao.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              priorizar_ocr_baixa_confianca: state.prioritizeOCR,
              ocultar_aprovados: state.hideApproved,
              limite_confianca_percentual: state.confidenceThreshold
            })
          });
        } catch (error) {
          console.error("Não foi possível salvar as regras:", error);
        }

        closeReviewModal("rulesModal");
        updateUI();
      });
    }

    const openNotificationsPanel = document.getElementById("openNotificationsPanel");
    const closeNotificationsPanel = document.getElementById("closeNotificationsPanel");
    const notifOverlay = document.getElementById("notifOverlay");

    if (openNotificationsPanel) {
      openNotificationsPanel.addEventListener("click", openNotifications);
    }

    if (closeNotificationsPanel) {
      closeNotificationsPanel.addEventListener("click", closeNotifications);
    }

    if (notifOverlay) {
      notifOverlay.addEventListener("click", closeNotifications);
    }

    const openAiModalButton = document.getElementById("openAiModal");
    const closeAiModalButton = document.getElementById("closeAiModal");
    const aiModal = document.getElementById("aiModal");

    if (openAiModalButton) {
      openAiModalButton.addEventListener("click", openAiModal);
    }

    if (closeAiModalButton) {
      closeAiModalButton.addEventListener("click", closeAiModal);
    }

    if (aiModal) {
      aiModal.addEventListener("click", function (e) {
        if (e.target === aiModal) {
          closeAiModal();
        }
      });
    }

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        document.querySelectorAll(".review-modal-overlay.active").forEach((modal) => {
          modal.classList.remove("active");
        });
        closeNotifications();
        closeAiModal();
        unlockBody();
      }
    });

    updateUI();
  </script>

</body>
</html>
