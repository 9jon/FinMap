<?php
session_start();
require_once __DIR__ . '/../config/conn.php'; // ajuste se necessário

// ==========================================================
// TEMPORÁRIO: login ainda não está conectado ao banco.
// Enquanto isso, usamos automaticamente o usuário de exemplo
// (João Dias) já inserido no seed, só pra dar pra testar essa
// tela sem passar pelo login.
//
// QUANDO O LOGIN ESTIVER PRONTO: apague o bloco "if" abaixo e
// deixe só a checagem normal de sessão (redirecionar pra
// login.php se não tiver $_SESSION['usuario_id']).
// ==========================================================
if (!isset($_SESSION['usuario_id'])) {
    $sqlTeste = "SELECT id FROM usuarios WHERE email = 'joao@email.com' LIMIT 1";
    $resultadoTeste = $conn->query($sqlTeste);
    $usuarioTeste = $resultadoTeste ? $resultadoTeste->fetch_assoc() : null;

    if ($usuarioTeste) {
        $_SESSION['usuario_id'] = (int)$usuarioTeste['id'];
    } else {
        die('Nenhum usuário de teste encontrado na tabela usuarios. Rode o INSERT do usuário de exemplo (joao@email.com) do schema antes de testar esta tela.');
    }
}

$usuario_id = (int)$_SESSION['usuario_id'];

$coresValidas = ['green', 'blue', 'orange', 'purple'];
$iconesValidos = ['shield-check', 'airplane', 'car-front', 'house-door', 'mortarboard', 'heart-pulse', 'gift', 'stars'];

// -----------------------------------------------------------------
// PROCESSAMENTO DAS AÇÕES (POST) — tudo via formulário normal,
// sem AJAX. Depois de cada ação, redirecionamos pra própria página
// (padrão Post/Redirect/Get).
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {

    $acao = $_POST['acao'];

    if ($acao === 'criar_meta' || $acao === 'editar_meta') {
        $nome = trim($_POST['nome'] ?? '');
        $valorMeta = (float)str_replace(',', '.', $_POST['valor_meta'] ?? 0);
        $valorGuardado = (float)str_replace(',', '.', $_POST['valor_guardado'] ?? 0);
        $icone = $_POST['icone'] ?? 'shield-check';
        $cor = $_POST['cor'] ?? 'green';

        if (!in_array($icone, $iconesValidos, true)) $icone = 'shield-check';
        if (!in_array($cor, $coresValidas, true)) $cor = 'green';

        // Não deixa guardar mais do que o valor total da meta
        if ($valorGuardado > $valorMeta) {
            $valorGuardado = $valorMeta;
        }

        if ($nome !== '' && $valorMeta > 0 && $valorGuardado >= 0) {

            if ($acao === 'criar_meta') {
                $sql = "INSERT INTO metas_financeiras (usuario_id, nome, valor_meta, valor_guardado, icone, cor)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddss", $usuario_id, $nome, $valorMeta, $valorGuardado, $icone, $cor);
                $stmt->execute();
                $stmt->close();
            } else {
                $metaId = (int)($_POST['meta_id'] ?? 0);

                $sql = "UPDATE metas_financeiras
                        SET nome = ?, valor_meta = ?, valor_guardado = ?, icone = ?, cor = ?
                        WHERE id = ? AND usuario_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddssii", $nome, $valorMeta, $valorGuardado, $icone, $cor, $metaId, $usuario_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($acao === 'excluir_meta') {
        $metaId = (int)($_POST['meta_id'] ?? 0);

        if ($metaId > 0) {
            $sql = "DELETE FROM metas_financeiras WHERE id = ? AND usuario_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $metaId, $usuario_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($acao === 'adicionar_valor') {
        $metaId = (int)($_POST['meta_id'] ?? 0);
        $valorAdicionar = (float)str_replace(',', '.', $_POST['valor_adicionar'] ?? 0);

        if ($metaId > 0 && $valorAdicionar > 0) {
            // Confirma que a meta é do usuário logado e pega os valores atuais
            $sqlBusca = "SELECT valor_meta, valor_guardado FROM metas_financeiras WHERE id = ? AND usuario_id = ?";
            $stmtBusca = $conn->prepare($sqlBusca);
            $stmtBusca->bind_param("ii", $metaId, $usuario_id);
            $stmtBusca->execute();
            $meta = $stmtBusca->get_result()->fetch_assoc();
            $stmtBusca->close();

            if ($meta) {
                $valorMetaAtual = (float)$meta['valor_meta'];
                $valorGuardadoAtual = (float)$meta['valor_guardado'];

                $novoValorGuardado = min($valorGuardadoAtual + $valorAdicionar, $valorMetaAtual);
                $valorRealmenteAdicionado = $novoValorGuardado - $valorGuardadoAtual;

                $sqlUpdate = "UPDATE metas_financeiras SET valor_guardado = ? WHERE id = ? AND usuario_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("dii", $novoValorGuardado, $metaId, $usuario_id);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Registra o aporte no histórico (metas_progresso)
                if ($valorRealmenteAdicionado > 0) {
                    $sqlHistorico = "INSERT INTO metas_progresso (meta_id, valor_adicionado) VALUES (?, ?)";
                    $stmtHistorico = $conn->prepare($sqlHistorico);
                    $stmtHistorico->bind_param("id", $metaId, $valorRealmenteAdicionado);
                    $stmtHistorico->execute();
                    $stmtHistorico->close();
                }
            }
        }
    }

    header('Location: metas-financeiras.php');
    exit;
}

// -----------------------------------------------------------------
// Busca as metas do usuário
// -----------------------------------------------------------------
$sql = "SELECT id, nome, valor_meta, valor_guardado, icone, cor
        FROM metas_financeiras
        WHERE usuario_id = ?
        ORDER BY criado_em ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

$metas = [];
while ($linha = $resultado->fetch_assoc()) {
    $metas[] = $linha;
}
$stmt->close();

// -----------------------------------------------------------------
// Resumo (total de metas, total guardado, progresso médio)
// -----------------------------------------------------------------
function calcularPercentual(float $guardado, float $meta): float
{
    if ($meta <= 0) return 0;
    return min(($guardado / $meta) * 100, 100);
}

$totalMetas = count($metas);
$totalGuardado = array_sum(array_map(fn($m) => (float)$m['valor_guardado'], $metas));
$progressoMedio = $totalMetas > 0
    ? round(array_sum(array_map(fn($m) => calcularPercentual((float)$m['valor_guardado'], (float)$m['valor_meta']), $metas)) / $totalMetas)
    : 0;

function brl(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Metas Financeiras - FinMap</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/metas-financeiras.css">
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
          <p>Metas financeiras inteligentes</p>
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
        <?= htmlspecialchars($_SESSION['avatar_iniciais'] ?? 'JD') ?>
      </button>
    </div>
  </header>

  <main class="goals-page">
    <div class="back-navigation">
      <a href="dashboard.php" class="back-btn">
        <i class="bi bi-chevron-left"></i>
        <span>Voltar</span>
      </a>
    </div>

    <section class="goals-dashboard">
      <section class="goals-header">
        <div class="goals-header__content">
          <h2>Metas financeiras</h2>
          <p>Crie objetivos, acompanhe sua evolução e mantenha seus planos visíveis dentro do FinMap.</p>
        </div>

        <div class="goals-header__actions">
          <button class="goal-btn goal-btn--primary" id="openCreateGoalModal" type="button">
            <i class="bi bi-plus-lg"></i>
            Nova meta
          </button>
        </div>
      </section>

      <section class="goals-summary-grid">
        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--green">
            <i class="bi bi-bullseye"></i>
          </div>
          <div class="summary-card__content">
            <span>Total de metas</span>
            <strong id="totalGoalsCount"><?= $totalMetas ?></strong>
            <p>Quantidade de objetivos cadastrados no momento.</p>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--blue">
            <i class="bi bi-piggy-bank"></i>
          </div>
          <div class="summary-card__content">
            <span>Total já guardado</span>
            <strong id="totalSavedValue"><?= brl($totalGuardado) ?></strong>
            <p>Soma acumulada entre todas as metas ativas.</p>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--orange">
            <i class="bi bi-graph-up"></i>
          </div>
          <div class="summary-card__content">
            <span>Progresso médio</span>
            <strong id="averageProgressValue"><?= $progressoMedio ?>%</strong>
            <p>Média geral de avanço das metas cadastradas.</p>
          </div>
        </article>
      </section>

      <section class="goals-panel">
        <div class="goals-panel__header">
          <div>
            <h3>Metas financeiras</h3>
            <p>Acompanhe o progresso dos seus objetivos</p>
          </div>

          <button class="goals-panel__menu" type="button" aria-label="Mais opções">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
        </div>

        <div class="goals-list" id="goalsList">
          <?php if (empty($metas)): ?>
            <div class="empty-goals-state">
              <div class="empty-goals-state__icon">
                <i class="bi bi-bullseye"></i>
              </div>
              <h4>Nenhuma meta criada ainda</h4>
              <p>Crie sua primeira meta financeira para começar a acompanhar seu progresso.</p>
            </div>
          <?php endif; ?>

          <?php foreach ($metas as $meta):
            $percentual = calcularPercentual((float)$meta['valor_guardado'], (float)$meta['valor_meta']);
            $faltam = max((float)$meta['valor_meta'] - (float)$meta['valor_guardado'], 0);
          ?>
            <article class="goal-card" data-id="<?= (int)$meta['id'] ?>">
              <div class="goal-card__top">
                <div class="goal-card__left">
                  <div class="goal-card__icon goal-card__icon--<?= htmlspecialchars($meta['cor']) ?>">
                    <i class="bi bi-<?= htmlspecialchars($meta['icone']) ?>"></i>
                  </div>

                  <div class="goal-card__info">
                    <h4><?= htmlspecialchars($meta['nome']) ?></h4>
                    <p><?= brl((float)$meta['valor_guardado']) ?> de <?= brl((float)$meta['valor_meta']) ?></p>
                  </div>
                </div>

                <span class="goal-card__badge"><?= round($percentual) ?>%</span>
              </div>

              <div class="goal-card__progress">
                <div class="goal-card__progress-fill goal-card__progress-fill--<?= htmlspecialchars($meta['cor']) ?>" style="width: <?= $percentual ?>%;"></div>
              </div>

              <div class="goal-card__bottom">
                <span><?= round($percentual) ?>% concluído</span>
                <span>Faltam <?= brl($faltam) ?></span>
              </div>

              <div class="goal-card__actions">
                <button
                  class="goal-action-btn goal-action-btn--green"
                  type="button"
                  data-add-progress="<?= (int)$meta['id'] ?>"
                >
                  <i class="bi bi-plus-circle"></i>
                  Adicionar valor
                </button>

                <button
                  class="goal-action-btn goal-action-btn--blue"
                  type="button"
                  data-edit-goal="<?= (int)$meta['id'] ?>"
                  data-nome="<?= htmlspecialchars($meta['nome']) ?>"
                  data-valor-meta="<?= (float)$meta['valor_meta'] ?>"
                  data-valor-guardado="<?= (float)$meta['valor_guardado'] ?>"
                  data-cor="<?= htmlspecialchars($meta['cor']) ?>"
                  data-icone="<?= htmlspecialchars($meta['icone']) ?>"
                >
                  <i class="bi bi-pencil-square"></i>
                  Editar
                </button>

                <form method="post" action="metas-financeiras.php" style="display:inline;" onsubmit="return confirm('Excluir esta meta? Essa ação não pode ser desfeita.');">
                  <input type="hidden" name="acao" value="excluir_meta">
                  <input type="hidden" name="meta_id" value="<?= (int)$meta['id'] ?>">
                  <button class="goal-action-btn goal-action-btn--red" type="submit">
                    <i class="bi bi-trash3"></i>
                    Excluir
                  </button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <button class="new-goal-btn" id="openCreateGoalModalBottom" type="button">
          <i class="bi bi-plus-lg"></i>
          Nova meta
        </button>
      </section>
    </section>
  </main>

  <!-- MODAL CRIAR/EDITAR META: um único form, o campo "acao" muda via JS -->
  <div class="goal-modal-overlay" id="goalModal">
    <div class="goal-modal">
      <div class="goal-modal__header">
        <div class="goal-modal__title-group">
          <div class="goal-modal__icon">
            <i class="bi bi-bullseye"></i>
          </div>

          <div>
            <h3 id="goalModalTitle">Nova meta</h3>
            <p>Defina um objetivo financeiro e acompanhe seu progresso.</p>
          </div>
        </div>

        <button class="goal-modal__close" id="closeGoalModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form method="post" action="metas-financeiras.php" id="goalForm">
        <input type="hidden" name="acao" id="goalAcaoInput" value="criar_meta">
        <input type="hidden" name="meta_id" id="goalIdInput" value="">

        <div class="goal-modal__body">
          <div class="goal-form-grid">
            <label class="goal-field">
              <span>Nome da meta</span>
              <input type="text" name="nome" id="goalNameInput" placeholder="Ex: Reserva de Emergência" required>
            </label>

            <label class="goal-field">
              <span>Valor total da meta</span>
              <input type="number" name="valor_meta" id="goalTargetInput" min="0.01" step="0.01" placeholder="15000" required>
            </label>

            <label class="goal-field">
              <span>Valor já guardado</span>
              <input type="number" name="valor_guardado" id="goalSavedInput" min="0" step="0.01" placeholder="8750">
            </label>

            <label class="goal-field">
              <span>Ícone</span>
              <select name="icone" id="goalIconInput">
                <option value="shield-check">Reserva</option>
                <option value="airplane">Viagem</option>
                <option value="car-front">Carro</option>
                <option value="house-door">Casa</option>
                <option value="mortarboard">Estudos</option>
                <option value="heart-pulse">Saúde</option>
                <option value="gift">Presente</option>
                <option value="stars">Objetivo geral</option>
              </select>
            </label>

            <label class="goal-field">
              <span>Cor</span>
              <select name="cor" id="goalColorInput">
                <option value="green">Verde</option>
                <option value="blue">Azul</option>
                <option value="orange">Laranja</option>
                <option value="purple">Roxo</option>
              </select>
            </label>
          </div>
        </div>

        <div class="goal-modal__footer">
          <button class="goal-footer-btn goal-footer-btn--secondary" id="cancelGoalModal" type="button">
            Cancelar
          </button>
          <button class="goal-footer-btn goal-footer-btn--primary" type="submit">
            Salvar meta
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL ADICIONAR VALOR: form próprio -->
  <div class="goal-modal-overlay" id="progressModal">
    <div class="goal-modal goal-modal--small">
      <div class="goal-modal__header">
        <div class="goal-modal__title-group">
          <div class="goal-modal__icon">
            <i class="bi bi-cash-stack"></i>
          </div>

          <div>
            <h3>Atualizar progresso</h3>
            <p>Adicione um novo valor guardado à meta.</p>
          </div>
        </div>

        <button class="goal-modal__close" id="closeProgressModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form method="post" action="metas-financeiras.php">
        <input type="hidden" name="acao" value="adicionar_valor">
        <input type="hidden" name="meta_id" id="progressMetaIdInput" value="">

        <div class="goal-modal__body">
          <label class="goal-field">
            <span>Valor a adicionar</span>
            <input type="number" name="valor_adicionar" id="progressAmountInput" min="0.01" step="0.01" placeholder="500" required>
          </label>
        </div>

        <div class="goal-modal__footer">
          <button class="goal-footer-btn goal-footer-btn--secondary" id="cancelProgressModal" type="button">
            Cancelar
          </button>
          <button class="goal-footer-btn goal-footer-btn--primary" type="submit">
            Adicionar valor
          </button>
        </div>
      </form>
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
      <button class="notif-tab" type="button">Metas</button>
      <button class="notif-tab" type="button">Progresso</button>
      <button class="notif-tab" type="button">IA</button>
    </div>

    <div class="notif-panel__list">
      <article class="notif-card notif-card--success">
        <div class="notif-card__icon">
          <i class="bi bi-bullseye"></i>
        </div>
        <div class="notif-card__content">
          <h4>Meta avançando bem</h4>
          <p>Sua meta principal está com progresso consistente neste mês.</p>
          <span>Hoje</span>
        </div>
      </article>

      <article class="notif-card notif-card--info">
        <div class="notif-card__icon">
          <i class="bi bi-stars"></i>
        </div>
        <div class="notif-card__content">
          <h4>Sugestão da IA</h4>
          <p>Manter aportes pequenos e recorrentes tende a acelerar suas metas mais longas.</p>
          <span>Hoje, 09:20</span>
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
            <strong>Metas com bom potencial</strong>
            <p>Suas metas estão organizadas e prontas para acompanhamento contínuo.</p>
          </div>

          <div class="ai-summary-card ai-summary-card--soft">
            <div class="ai-summary-card__top">
              <span class="ai-summary-card__label">Sugestão principal</span>
              <i class="bi bi-lightbulb"></i>
            </div>
            <strong>Manter consistência nos aportes</strong>
            <p>Pequenos valores recorrentes geram evolução mais previsível nas metas.</p>
          </div>

          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button">Analisar minhas metas</button>
            <button class="ai-shortcut" type="button">Qual priorizar?</button>
            <button class="ai-shortcut" type="button">Criar plano rápido</button>
            <button class="ai-shortcut" type="button">Revisar progresso</button>
          </div>
        </aside>

        <section class="ai-chat-area">
          <div class="ai-chat-area__messages">
            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Posso te ajudar a organizar suas metas por prioridade, prazo ou impacto financeiro.
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

    function hasClassSafe(element, className) {
      return element && element.classList.contains(className);
    }

    function lockBody() {
      body.style.overflow = "hidden";
    }

    function unlockBody() {
      const hasOpenModal =
        hasClassSafe(document.getElementById("goalModal"), "active") ||
        hasClassSafe(document.getElementById("progressModal"), "active") ||
        hasClassSafe(document.getElementById("notifPanel"), "active") ||
        hasClassSafe(document.getElementById("aiModal"), "active");

      body.style.overflow = hasOpenModal ? "hidden" : "";
    }

    function openModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.add("active");
      lockBody();
    }

    function closeModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.remove("active");
      unlockBody();
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

    function openAiModal() {
      document.getElementById("aiModal")?.classList.add("active");
      lockBody();
    }

    function closeAiModal() {
      document.getElementById("aiModal")?.classList.remove("active");
      unlockBody();
    }

    function resetGoalForm() {
      document.getElementById("goalModalTitle").textContent = "Nova meta";
      document.getElementById("goalAcaoInput").value = "criar_meta";
      document.getElementById("goalIdInput").value = "";
      document.getElementById("goalNameInput").value = "";
      document.getElementById("goalTargetInput").value = "";
      document.getElementById("goalSavedInput").value = "";
      document.getElementById("goalColorInput").value = "green";
      document.getElementById("goalIconInput").value = "shield-check";
    }

    document.getElementById("openCreateGoalModal").addEventListener("click", () => {
      resetGoalForm();
      openModal("goalModal");
    });

    document.getElementById("openCreateGoalModalBottom").addEventListener("click", () => {
      resetGoalForm();
      openModal("goalModal");
    });

    // Preenche o form de edição a partir dos data-* renderizados pelo PHP em cada card
    document.querySelectorAll("[data-edit-goal]").forEach((button) => {
      button.addEventListener("click", () => {
        document.getElementById("goalModalTitle").textContent = "Editar meta";
        document.getElementById("goalAcaoInput").value = "editar_meta";
        document.getElementById("goalIdInput").value = button.getAttribute("data-edit-goal");
        document.getElementById("goalNameInput").value = button.getAttribute("data-nome");
        document.getElementById("goalTargetInput").value = button.getAttribute("data-valor-meta");
        document.getElementById("goalSavedInput").value = button.getAttribute("data-valor-guardado");
        document.getElementById("goalColorInput").value = button.getAttribute("data-cor");
        document.getElementById("goalIconInput").value = button.getAttribute("data-icone");

        openModal("goalModal");
      });
    });

    document.querySelectorAll("[data-add-progress]").forEach((button) => {
      button.addEventListener("click", () => {
        document.getElementById("progressMetaIdInput").value = button.getAttribute("data-add-progress");
        document.getElementById("progressAmountInput").value = "";
        openModal("progressModal");
      });
    });

    document.getElementById("closeGoalModal").addEventListener("click", () => closeModal("goalModal"));
    document.getElementById("cancelGoalModal").addEventListener("click", () => closeModal("goalModal"));

    document.getElementById("closeProgressModal").addEventListener("click", () => closeModal("progressModal"));
    document.getElementById("cancelProgressModal").addEventListener("click", () => closeModal("progressModal"));

    document.getElementById("openNotificationsPanel").addEventListener("click", openNotifications);
    document.getElementById("closeNotificationsPanel").addEventListener("click", closeNotifications);
    document.getElementById("notifOverlay").addEventListener("click", closeNotifications);

    document.getElementById("openAiModal").addEventListener("click", openAiModal);
    document.getElementById("closeAiModal").addEventListener("click", closeAiModal);

    document.getElementById("goalModal").addEventListener("click", (e) => {
      if (e.target.id === "goalModal") closeModal("goalModal");
    });

    document.getElementById("progressModal").addEventListener("click", (e) => {
      if (e.target.id === "progressModal") closeModal("progressModal");
    });

    document.getElementById("aiModal").addEventListener("click", (e) => {
      if (e.target.id === "aiModal") closeAiModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal("goalModal");
        closeModal("progressModal");
        closeNotifications();
        closeAiModal();
      }
    });
  </script>

</body>
</html>