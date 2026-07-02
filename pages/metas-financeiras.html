<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Metas Financeiras - FinMap</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./FinMap_Projeto/css/dashboard.css">
  <link rel="stylesheet" href="./FinMap_Projeto/css/metas-financeiras.css">
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
        JD
      </button>
    </div>
  </header>

  <main class="goals-page">
    <div class="back-navigation">
      <a href="./dashboard.html" class="back-btn">
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
            <strong id="totalGoalsCount">0</strong>
            <p>Quantidade de objetivos cadastrados no momento.</p>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--blue">
            <i class="bi bi-piggy-bank"></i>
          </div>
          <div class="summary-card__content">
            <span>Total já guardado</span>
            <strong id="totalSavedValue">R$ 0,00</strong>
            <p>Soma acumulada entre todas as metas ativas.</p>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--orange">
            <i class="bi bi-graph-up"></i>
          </div>
          <div class="summary-card__content">
            <span>Progresso médio</span>
            <strong id="averageProgressValue">0%</strong>
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

        <div class="goals-list" id="goalsList"></div>

        <button class="new-goal-btn" id="openCreateGoalModalBottom" type="button">
          <i class="bi bi-plus-lg"></i>
          Nova meta
        </button>
      </section>
    </section>
  </main>

  <!-- MODAL CRIAR/EDITAR META -->
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

      <div class="goal-modal__body">
        <div class="goal-form-grid">
          <label class="goal-field">
            <span>Nome da meta</span>
            <input type="text" id="goalNameInput" placeholder="Ex: Reserva de Emergência">
          </label>

          <label class="goal-field">
            <span>Valor total da meta</span>
            <input type="number" id="goalTargetInput" min="0" step="0.01" placeholder="15000">
          </label>

          <label class="goal-field">
            <span>Valor já guardado</span>
            <input type="number" id="goalSavedInput" min="0" step="0.01" placeholder="8750">
          </label>

          <label class="goal-field">
            <span>Ícone</span>
            <select id="goalIconInput">
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
            <select id="goalColorInput">
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
        <button class="goal-footer-btn goal-footer-btn--primary" id="saveGoalBtn" type="button">
          Salvar meta
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL ADICIONAR VALOR -->
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

      <div class="goal-modal__body">
        <label class="goal-field">
          <span>Valor a adicionar</span>
          <input type="number" id="progressAmountInput" min="0" step="0.01" placeholder="500">
        </label>
      </div>

      <div class="goal-modal__footer">
        <button class="goal-footer-btn goal-footer-btn--secondary" id="cancelProgressModal" type="button">
          Cancelar
        </button>
        <button class="goal-footer-btn goal-footer-btn--primary" id="saveProgressBtn" type="button">
          Adicionar valor
        </button>
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

            <div class="ai-message ai-message--user">
              <div class="ai-message__bubble">
                Qual meta faz mais sentido priorizar agora?
              </div>
            </div>

            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Em geral, a reserva de emergência costuma ser a meta mais estratégica para fortalecer sua segurança financeira primeiro.
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
    const GOALS_STORAGE_KEY = "finmap_goals";
    const DEFAULT_GOALS = [
      {
        id: 1,
        name: "Reserva de Emergência",
        target: 15000,
        saved: 8750,
        color: "green",
        icon: "shield-check"
      },
      {
        id: 2,
        name: "Viagem para Europa",
        target: 8000,
        saved: 3200,
        color: "blue",
        icon: "airplane"
      },
      {
        id: 3,
        name: "Novo carro",
        target: 45000,
        saved: 12000,
        color: "orange",
        icon: "car-front"
      }
    ];

    let goals = loadGoals();
    let editingGoalId = null;
    let progressGoalId = null;

    function loadGoals() {
      const saved = localStorage.getItem(GOALS_STORAGE_KEY);
      if (saved) {
        try {
          return JSON.parse(saved);
        } catch (error) {
          return DEFAULT_GOALS;
        }
      }
      localStorage.setItem(GOALS_STORAGE_KEY, JSON.stringify(DEFAULT_GOALS));
      return DEFAULT_GOALS;
    }

    function saveGoals() {
      localStorage.setItem(GOALS_STORAGE_KEY, JSON.stringify(goals));
    }

    function formatBRL(value) {
      return value.toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL"
      });
    }

    function escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }

    function getColorClass(color) {
      return {
        green: "green",
        blue: "blue",
        orange: "orange",
        purple: "purple"
      }[color] || "green";
    }

    function calculatePercent(saved, target) {
      if (!target || target <= 0) return 0;
      return Math.min((saved / target) * 100, 100);
    }

    function renderGoals() {
      const list = document.getElementById("goalsList");
      if (!list) return;

      if (!goals.length) {
        list.innerHTML = `
          <div class="empty-goals-state">
            <div class="empty-goals-state__icon">
              <i class="bi bi-bullseye"></i>
            </div>
            <h4>Nenhuma meta criada ainda</h4>
            <p>Crie sua primeira meta financeira para começar a acompanhar seu progresso.</p>
          </div>
        `;
        renderSummary();
        return;
      }

      list.innerHTML = goals.map((goal) => {
        const percent = calculatePercent(goal.saved, goal.target);
        const remaining = Math.max(goal.target - goal.saved, 0);
        const color = getColorClass(goal.color);

        return `
          <article class="goal-card" data-id="${goal.id}">
            <div class="goal-card__top">
              <div class="goal-card__left">
                <div class="goal-card__icon goal-card__icon--${color}">
                  <i class="bi bi-${goal.icon}"></i>
                </div>

                <div class="goal-card__info">
                  <h4>${escapeHtml(goal.name)}</h4>
                  <p>${formatBRL(goal.saved)} de ${formatBRL(goal.target)}</p>
                </div>
              </div>

              <span class="goal-card__badge">${Math.round(percent)}%</span>
            </div>

            <div class="goal-card__progress">
              <div class="goal-card__progress-fill goal-card__progress-fill--${color}" style="width: ${percent}%;"></div>
            </div>

            <div class="goal-card__bottom">
              <span>${Math.round(percent)}% concluído</span>
              <span>Faltam ${formatBRL(remaining)}</span>
            </div>

            <div class="goal-card__actions">
              <button class="goal-action-btn goal-action-btn--green" type="button" data-add-progress="${goal.id}">
                <i class="bi bi-plus-circle"></i>
                Adicionar valor
              </button>

              <button class="goal-action-btn goal-action-btn--blue" type="button" data-edit-goal="${goal.id}">
                <i class="bi bi-pencil-square"></i>
                Editar
              </button>

              <button class="goal-action-btn goal-action-btn--red" type="button" data-delete-goal="${goal.id}">
                <i class="bi bi-trash3"></i>
                Excluir
              </button>
            </div>
          </article>
        `;
      }).join("");

      bindGoalActions();
      renderSummary();
    }

    function renderSummary() {
      const totalGoals = goals.length;
      const totalSaved = goals.reduce((sum, goal) => sum + Number(goal.saved), 0);
      const averageProgress = totalGoals
        ? Math.round(goals.reduce((sum, goal) => sum + calculatePercent(goal.saved, goal.target), 0) / totalGoals)
        : 0;

      document.getElementById("totalGoalsCount").textContent = totalGoals;
      document.getElementById("totalSavedValue").textContent = formatBRL(totalSaved);
      document.getElementById("averageProgressValue").textContent = `${averageProgress}%`;
    }

    function bindGoalActions() {
      document.querySelectorAll("[data-add-progress]").forEach((button) => {
        button.addEventListener("click", () => {
          progressGoalId = Number(button.getAttribute("data-add-progress"));
          document.getElementById("progressAmountInput").value = "";
          openModal("progressModal");
        });
      });

      document.querySelectorAll("[data-edit-goal]").forEach((button) => {
        button.addEventListener("click", () => {
          const id = Number(button.getAttribute("data-edit-goal"));
          const goal = goals.find(item => item.id === id);
          if (!goal) return;

          editingGoalId = id;
          document.getElementById("goalModalTitle").textContent = "Editar meta";
          document.getElementById("goalNameInput").value = goal.name;
          document.getElementById("goalTargetInput").value = goal.target;
          document.getElementById("goalSavedInput").value = goal.saved;
          document.getElementById("goalColorInput").value = goal.color;
          document.getElementById("goalIconInput").value = goal.icon;

          openModal("goalModal");
        });
      });

      document.querySelectorAll("[data-delete-goal]").forEach((button) => {
        button.addEventListener("click", () => {
          const id = Number(button.getAttribute("data-delete-goal"));
          goals = goals.filter(goal => goal.id !== id);
          saveGoals();
          renderGoals();
        });
      });
    }

    function resetGoalForm() {
      editingGoalId = null;
      document.getElementById("goalModalTitle").textContent = "Nova meta";
      document.getElementById("goalNameInput").value = "";
      document.getElementById("goalTargetInput").value = "";
      document.getElementById("goalSavedInput").value = "";
      document.getElementById("goalColorInput").value = "green";
      document.getElementById("goalIconInput").value = "shield-check";
    }

    function saveGoal() {
      const name = document.getElementById("goalNameInput").value.trim();
      const target = Number(document.getElementById("goalTargetInput").value);
      const saved = Number(document.getElementById("goalSavedInput").value);
      const color = document.getElementById("goalColorInput").value;
      const icon = document.getElementById("goalIconInput").value;

      if (!name || target <= 0 || saved < 0) return;

      if (editingGoalId) {
        const goal = goals.find(item => item.id === editingGoalId);
        if (!goal) return;

        goal.name = name;
        goal.target = target;
        goal.saved = Math.min(saved, target);
        goal.color = color;
        goal.icon = icon;
      } else {
        goals.push({
          id: Date.now(),
          name,
          target,
          saved: Math.min(saved, target),
          color,
          icon
        });
      }

      saveGoals();
      renderGoals();
      closeModal("goalModal");
      resetGoalForm();
    }

    function addProgressToGoal() {
      const amount = Number(document.getElementById("progressAmountInput").value);
      if (!progressGoalId || amount <= 0) return;

      const goal = goals.find(item => item.id === progressGoalId);
      if (!goal) return;

      goal.saved = Math.min(goal.saved + amount, goal.target);
      saveGoals();
      renderGoals();
      closeModal("progressModal");
    }

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

    document.getElementById("openCreateGoalModal").addEventListener("click", () => {
      resetGoalForm();
      openModal("goalModal");
    });

    document.getElementById("openCreateGoalModalBottom").addEventListener("click", () => {
      resetGoalForm();
      openModal("goalModal");
    });

    document.getElementById("saveGoalBtn").addEventListener("click", saveGoal);
    document.getElementById("saveProgressBtn").addEventListener("click", addProgressToGoal);

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

    renderGoals();
  </script>

</body>
</html>