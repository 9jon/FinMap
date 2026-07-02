<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Orçamento Mensal - FinMap</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./FinMap_Projeto/css/dashboard.css">
  <link rel="stylesheet" href="./FinMap_Projeto/css/orcamento-mensal.css">

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
          <p>Orçamento mensal inteligente</p>
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

  <main class="monthly-budget-page">
    <div class="back-navigation">
      <a href="./dashboard.html" class="back-btn">
        <i class="bi bi-chevron-left"></i>
        <span>Voltar</span>
      </a>
    </div>

    <section class="budget-dashboard">
      <section class="budget-dashboard__top">
        <article class="budget-hero-card">
          <div class="budget-hero-card__badge">
            <i class="bi bi-calendar-check"></i>
            <span>Controle mensal atualizado</span>
          </div>

          <h2>Quanto da sua renda já foi comprometido</h2>

          <p>
            Visualize o peso atual das suas despesas no mês, entenda quais categorias
            pressionam mais o orçamento e simule ajustes para recuperar folga financeira.
          </p>

          <div class="budget-hero-card__stats">
            <article class="budget-kpi budget-kpi--main">
              <span>Renda mensal</span>
              <strong id="incomeValue">R$ 5.800,00</strong>
              <small>base usada no orçamento atual</small>
            </article>

            <article class="budget-kpi">
              <span>Comprometido</span>
              <strong id="committedValue">R$ 3.245,50</strong>
            </article>

            <article class="budget-kpi">
              <span>Livre no mês</span>
              <strong id="remainingValue">R$ 2.554,50</strong>
            </article>
          </div>

          <div class="budget-hero-card__actions">
            <button class="budget-btn budget-btn--primary" id="openIncomeModal" type="button">
              <i class="bi bi-cash-coin"></i>
              Ajustar renda
            </button>

            <button class="budget-btn budget-btn--secondary" id="openPlannerModal" type="button">
              <i class="bi bi-sliders"></i>
              Configurar limites
            </button>
          </div>
        </article>

        <article class="budget-visual-card">
          <div class="budget-visual-card__header">
            <div>
              <h3>Comprometimento do mês</h3>
              <p>Leitura visual da parcela da sua renda já ocupada por despesas</p>
            </div>

            <button class="panel-action-btn" id="openDetailsModal" type="button">
              <i class="bi bi-pie-chart"></i>
              Ver detalhes
            </button>
          </div>

          <div class="budget-ring-area">
            <div class="budget-ring" id="budgetRing" style="--percent: 56;">
              <div class="budget-ring__inner">
                <span class="budget-ring__label">Comprometido</span>
                <strong id="budgetPercent">56%</strong>
              </div>
            </div>

            <div class="budget-ring-metrics">
              <div class="budget-ring-metric">
                <span>Faixa ideal</span>
                <strong id="idealRangeText">até 70%</strong>
              </div>

              <div class="budget-ring-metric">
                <span>Status atual</span>
                <strong id="budgetStatusText">Saudável</strong>
              </div>

              <div class="budget-ring-metric">
                <span>Folga estimada</span>
                <strong id="bufferValue">R$ 2.554,50</strong>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="budget-overview-grid">
        <article class="budget-summary-card">
          <div class="budget-summary-card__icon budget-summary-card__icon--green">
            <i class="bi bi-wallet2"></i>
          </div>

          <div class="budget-summary-card__content">
            <span>Disponível no mês</span>
            <strong id="summaryAvailable">R$ 2.554,50</strong>
            <p>Valor ainda livre antes de novas despesas no período.</p>
          </div>
        </article>

        <article class="budget-summary-card">
          <div class="budget-summary-card__icon budget-summary-card__icon--orange">
            <i class="bi bi-exclamation-circle"></i>
          </div>

          <div class="budget-summary-card__content">
            <span>Faixa atual</span>
            <strong id="summaryRange">Saudável</strong>
            <p id="summaryRangeText">Seu orçamento ainda mantém boa margem de segurança.</p>
          </div>
        </article>

        <article class="budget-summary-card">
          <div class="budget-summary-card__icon budget-summary-card__icon--blue">
            <i class="bi bi-collection"></i>
          </div>

          <div class="budget-summary-card__content">
            <span>Categorias ativas</span>
            <strong id="summaryCategories">5 categorias</strong>
            <p>Áreas consideradas no cálculo do orçamento mensal atual.</p>
          </div>
        </article>
      </section>

      <section class="budget-dashboard__bottom budget-dashboard__bottom--balanced">
  <section class="budget-panel budget-panel--equal">
    <div class="budget-panel__header">
      <div>
        <h3>Onde sua renda está sendo comprometida</h3>
        <p>As categorias que mais pressionam seu orçamento neste mês</p>
      </div>

      <button class="panel-action-btn" id="openCategoriesModal" type="button">
        <i class="bi bi-grid-3x3-gap"></i>
        Categorias
      </button>
    </div>

    <div class="budget-category-list">
      <article class="budget-category-item">
        <div class="budget-category-item__top">
          <div class="budget-category-item__left">
            <div class="budget-category-item__icon budget-category-item__icon--purple">
              <i class="bi bi-house-door"></i>
            </div>
            <div>
              <h4>Moradia</h4>
              <p>Despesas fixas ligadas à residência</p>
            </div>
          </div>

          <div class="budget-category-item__right">
            <strong class="cat-value" data-key="housing">R$ 1.200,00</strong>
            <span class="cat-percent" data-key="housingPercent">20,7%</span>
          </div>
        </div>

        <div class="budget-category-item__bar">
          <div class="budget-category-item__fill budget-category-item__fill--purple" data-key="housingBar" style="width: 20.7%;"></div>
        </div>
      </article>

      <article class="budget-category-item">
        <div class="budget-category-item__top">
          <div class="budget-category-item__left">
            <div class="budget-category-item__icon budget-category-item__icon--green">
              <i class="bi bi-basket2"></i>
            </div>
            <div>
              <h4>Alimentação</h4>
              <p>Compras de mercado e consumo alimentar</p>
            </div>
          </div>

          <div class="budget-category-item__right">
            <strong class="cat-value" data-key="food">R$ 850,00</strong>
            <span class="cat-percent" data-key="foodPercent">14,7%</span>
          </div>
        </div>

        <div class="budget-category-item__bar">
          <div class="budget-category-item__fill budget-category-item__fill--green" data-key="foodBar" style="width: 14.7%;"></div>
        </div>
      </article>

      <article class="budget-category-item">
        <div class="budget-category-item__top">
          <div class="budget-category-item__left">
            <div class="budget-category-item__icon budget-category-item__icon--blue">
              <i class="bi bi-bus-front"></i>
            </div>
            <div>
              <h4>Transporte</h4>
              <p>Mobilidade e deslocamentos recorrentes</p>
            </div>
          </div>

          <div class="budget-category-item__right">
            <strong class="cat-value" data-key="transport">R$ 450,00</strong>
            <span class="cat-percent" data-key="transportPercent">7,8%</span>
          </div>
        </div>

        <div class="budget-category-item__bar">
          <div class="budget-category-item__fill budget-category-item__fill--blue" data-key="transportBar" style="width: 7.8%;"></div>
        </div>
      </article>

      <article class="budget-category-item">
        <div class="budget-category-item__top">
          <div class="budget-category-item__left">
            <div class="budget-category-item__icon budget-category-item__icon--orange">
              <i class="bi bi-lightning-charge"></i>
            </div>
            <div>
              <h4>Contas e utilidades</h4>
              <p>Luz, internet, água e outros custos mensais</p>
            </div>
          </div>

          <div class="budget-category-item__right">
            <strong class="cat-value" data-key="utilities">R$ 395,50</strong>
            <span class="cat-percent" data-key="utilitiesPercent">6,8%</span>
          </div>
        </div>

        <div class="budget-category-item__bar">
          <div class="budget-category-item__fill budget-category-item__fill--orange" data-key="utilitiesBar" style="width: 6.8%;"></div>
        </div>
      </article>

      <article class="budget-category-item">
        <div class="budget-category-item__top">
          <div class="budget-category-item__left">
            <div class="budget-category-item__icon budget-category-item__icon--red">
              <i class="bi bi-bag-heart"></i>
            </div>
            <div>
              <h4>Lazer e extras</h4>
              <p>Despesas flexíveis e menos prioritárias</p>
            </div>
          </div>

          <div class="budget-category-item__right">
            <strong class="cat-value" data-key="extras">R$ 350,00</strong>
            <span class="cat-percent" data-key="extrasPercent">6,0%</span>
          </div>
        </div>

        <div class="budget-category-item__bar">
          <div class="budget-category-item__fill budget-category-item__fill--red" data-key="extrasBar" style="width: 6.0%;"></div>
        </div>
      </article>
    </div>
  </section>

  <section class="budget-panel budget-panel--equal">
    <div class="budget-panel__header">
      <div>
        <h3>Leitura do mês</h3>
        <p>Resumo do comportamento do seu orçamento atual</p>
      </div>

      <button class="panel-action-btn" id="openScenarioModal" type="button">
        <i class="bi bi-bar-chart-line"></i>
        Cenários
      </button>
    </div>

    <div class="budget-reading-card budget-reading-card--expanded">
      <div class="budget-reading-card__item">
        <span>Status atual</span>
        <strong id="readingStatus">Orçamento saudável</strong>
      </div>

      <div class="budget-reading-card__item">
        <span>Categoria mais pesada</span>
        <strong id="readingCategory">Moradia</strong>
      </div>

      <div class="budget-reading-card__item">
        <span>Recomendação principal</span>
        <strong id="readingSuggestion">Preservar a folga atual e controlar lazer/extras</strong>
      </div>

      <div class="budget-reading-card__item">
        <span>Cenário selecionado</span>
        <strong id="readingScenario">Atual</strong>
      </div>

      <div class="budget-reading-card__item">
        <span>Faixa ideal configurada</span>
        <strong id="readingIdealLimit">Até 70%</strong>
      </div>

      <div class="budget-reading-card__item">
        <span>Faixa de alerta</span>
        <strong id="readingWarningLimit">Acima de 85%</strong>
      </div>
    </div>
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

      <div class="budget-modal__body">
        <div class="budget-form-card">
          <label class="budget-field budget-field--money">
            <span>Nova renda mensal</span>
            <input id="incomeInput" class="finmap-money-input" type="text" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00" value="R$ 5.800,00">
          </label>

          <button class="budget-btn budget-btn--primary budget-btn--full" id="saveIncomeBtn" type="button">
            Salvar renda
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL LIMITES -->
  <div class="budget-modal-overlay" id="plannerModal">
    <div class="budget-modal budget-modal--medium">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-sliders"></i>
          </div>
          <div>
            <h3>Configurar limites</h3>
            <p>Defina a faixa de comprometimento considerada saudável no seu mês.</p>
          </div>
        </div>

        <button class="budget-modal__close" data-close-budget-modal="plannerModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="budget-modal__body">
        <div class="planner-grid">
          <label class="budget-field">
            <span>Faixa ideal (%)</span>
            <input id="idealLimitInput" type="number" min="1" max="100" value="70">
          </label>

          <label class="budget-field">
            <span>Faixa de alerta (%)</span>
            <input id="warningLimitInput" type="number" min="1" max="100" value="85">
          </label>

          <button class="budget-btn budget-btn--primary budget-btn--full" id="savePlannerBtn" type="button">
            Aplicar limites
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL DETALHES -->
  <div class="budget-modal-overlay" id="detailsModal">
    <div class="budget-modal">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-pie-chart"></i>
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
            <strong id="detailIncome">R$ 5.800,00</strong>
            <p>Valor base usado como referência do orçamento atual.</p>
          </article>

          <article class="details-box">
            <span>Total comprometido</span>
            <strong id="detailCommitted">R$ 3.245,50</strong>
            <p>Soma de todas as categorias ativas no orçamento deste mês.</p>
          </article>

          <article class="details-box">
            <span>Comprometimento</span>
            <strong id="detailPercent">56%</strong>
            <p>Percentual da sua renda já absorvido por despesas no período.</p>
          </article>

          <article class="details-box">
            <span>Valor livre</span>
            <strong id="detailRemaining">R$ 2.554,50</strong>
            <p>Margem ainda disponível antes de novos gastos.</p>
          </article>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL CATEGORIAS -->
  <div class="budget-modal-overlay" id="categoriesModal">
    <div class="budget-modal">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-grid-3x3-gap"></i>
          </div>
          <div>
            <h3>Categorias do orçamento</h3>
            <p>Gerencie quais categorias entram no cálculo do comprometimento mensal.</p>
          </div>
        </div>

        <button class="budget-modal__close" data-close-budget-modal="categoriesModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="budget-modal__body">
        <div class="budget-config-list">
          <div class="budget-config-row">
            <div>
              <h4>Moradia</h4>
              <p>Despesas fixas relacionadas à residência.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="toggleHousing" checked>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="budget-config-row">
            <div>
              <h4>Alimentação</h4>
              <p>Mercado, refeições e custos alimentares gerais.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="toggleFood" checked>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="budget-config-row">
            <div>
              <h4>Transporte</h4>
              <p>Combustível, ônibus, apps e deslocamentos.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="toggleTransport" checked>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="budget-config-row">
            <div>
              <h4>Contas e utilidades</h4>
              <p>Internet, energia, água e despesas da casa.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="toggleUtilities" checked>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="budget-config-row">
            <div>
              <h4>Lazer e extras</h4>
              <p>Gastos flexíveis e menos prioritários no mês.</p>
            </div>
            <label class="switch switch--green">
              <input type="checkbox" id="toggleExtras" checked>
              <span class="switch-slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL CENÁRIOS -->
  <div class="budget-modal-overlay" id="scenarioModal">
    <div class="budget-modal budget-modal--medium">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-bar-chart-line"></i>
          </div>
          <div>
            <h3>Explorar cenários</h3>
            <p>Troque o cenário e veja a tela se atualizar em tempo real.</p>
          </div>
        </div>

        <button class="budget-modal__close" data-close-budget-modal="scenarioModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="budget-modal__body">
        <div class="budget-simulation-list budget-simulation-list--modal">
          <article class="budget-sim-option budget-sim-option--active" data-scenario="base">
            <div>
              <h4>Cenário atual</h4>
              <p>Baseado nos valores atuais cadastrados.</p>
            </div>
            <strong>56% comprometido</strong>
          </article>

          <article class="budget-sim-option" data-scenario="cauteloso">
            <div>
              <h4>Cenário cauteloso</h4>
              <p>Redução de despesas variáveis e preservação de margem.</p>
            </div>
            <strong>49% comprometido</strong>
          </article>

          <article class="budget-sim-option" data-scenario="pressionado">
            <div>
              <h4>Cenário pressionado</h4>
              <p>Aumento em custos fixos e menor folga financeira.</p>
            </div>
            <strong>67% comprometido</strong>
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
          <h4>Faixa de alerta próxima</h4>
          <p>Seu orçamento pode se aproximar da faixa de alerta se novas despesas entrarem.</p>
          <span>Agora mesmo</span>
        </div>
      </article>

      <article class="notif-card notif-card--success">
        <div class="notif-card__icon">
          <i class="bi bi-wallet2"></i>
        </div>
        <div class="notif-card__content">
          <h4>Boa margem disponível</h4>
          <p>Seu orçamento ainda mantém uma folga saudável neste mês.</p>
          <span>Hoje, 09:20</span>
        </div>
      </article>

      <article class="notif-card notif-card--warning">
        <div class="notif-card__icon">
          <i class="bi bi-house-door"></i>
        </div>
        <div class="notif-card__content">
          <h4>Moradia lidera o peso mensal</h4>
          <p>Essa é a categoria que mais compromete sua renda no momento.</p>
          <span>Hoje, 08:05</span>
        </div>
      </article>

      <article class="notif-card notif-card--info">
        <div class="notif-card__icon">
          <i class="bi bi-stars"></i>
        </div>
        <div class="notif-card__content">
          <h4>Sugestão da IA</h4>
          <p>Simule o cenário cauteloso para enxergar uma margem mensal mais confortável.</p>
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
            <strong>Orçamento com boa margem</strong>
            <p>Seu comprometimento atual ainda está dentro da faixa saudável, mas moradia segue como principal peso do mês.</p>
          </div>

          <div class="ai-summary-card ai-summary-card--soft">
            <div class="ai-summary-card__top">
              <span class="ai-summary-card__label">Sugestão principal</span>
              <i class="bi bi-lightbulb"></i>
            </div>
            <strong>Controlar despesas flexíveis</strong>
            <p>Preservar a folga atual depende principalmente do controle em lazer e gastos variáveis.</p>
          </div>

          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button">Analisar meu orçamento</button>
            <button class="ai-shortcut" type="button">Onde cortar gastos?</button>
            <button class="ai-shortcut" type="button">Comparar cenários</button>
            <button class="ai-shortcut" type="button">Criar plano rápido</button>
          </div>
        </aside>

        <section class="ai-chat-area">
          <div class="ai-chat-area__messages">
            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Analisei seu orçamento mensal. Hoje ele ainda está em uma faixa saudável, mas moradia e alimentação são os pontos que mais consomem sua renda.
              </div>
            </div>

            <div class="ai-message ai-message--user">
              <div class="ai-message__bubble">
                Onde eu recuperaria mais folga rápido?
              </div>
            </div>

            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                O caminho mais rápido costuma estar em despesas variáveis, como lazer, extras e alimentação acima do planejado. Isso preserva mais margem sem mexer tanto nos custos fixos.
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

  const budgetModalMap = {
    openIncomeModal: "incomeModal",
    openPlannerModal: "plannerModal",
    openDetailsModal: "detailsModal",
    openCategoriesModal: "categoriesModal",
    openScenarioModal: "scenarioModal"
  };

  const baseBudget = {
    housing: 1200,
    food: 850,
    transport: 450,
    utilities: 395.5,
    extras: 350
  };

  const scenarios = {
    base: {
      label: "Atual",
      multipliers: {
        housing: 1,
        food: 1,
        transport: 1,
        utilities: 1,
        extras: 1
      }
    },
    cauteloso: {
      label: "Cauteloso",
      multipliers: {
        housing: 1,
        food: 0.92,
        transport: 0.9,
        utilities: 1,
        extras: 0.65
      }
    },
    pressionado: {
      label: "Pressionado",
      multipliers: {
        housing: 1.05,
        food: 1.1,
        transport: 1.12,
        utilities: 1.08,
        extras: 1.05
      }
    }
  };

  const state = {
    income: 5800,
    scenario: "base",
    idealLimit: 70,
    warningLimit: 85,
    enabled: {
      housing: true,
      food: true,
      transport: true,
      utilities: true,
      extras: true
    }
  };

  function formatBRL(value) {
    return value.toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL"
    });
  }

  function onlyDigits(value) {
    return String(value || "").replace(/\D/g, "");
  }

  function formatCurrencyInput(value) {
    const digits = onlyDigits(value);
    if (!digits) return "R$ 0,00";

    const number = Number(digits) / 100;
    return formatBRL(number);
  }

  function parseBRLToNumber(value) {
    const digits = onlyDigits(value);
    return digits ? Number(digits) / 100 : 0;
  }

  function getStoredDashboardBalance() {
    const storedBalance = localStorage.getItem("finmapSaldoTotal");
    if (!storedBalance) return null;

    const parsedValue = parseBRLToNumber(storedBalance);
    return parsedValue > 0 ? parsedValue : null;
  }

  function syncIncomeInput() {
    const incomeInput = document.getElementById("incomeInput");
    if (incomeInput) {
      incomeInput.value = formatBRL(state.income);
    }
  }

  function hasClassSafe(element, className) {
    return element && element.classList.contains(className);
  }

  function lockBody() {
    body.style.overflow = "hidden";
  }

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

  function getCurrentCategoryValues() {
    const scenario = scenarios[state.scenario];
    const result = {};

    Object.keys(baseBudget).forEach((key) => {
      if (!state.enabled[key]) {
        result[key] = 0;
      } else {
        result[key] = +(baseBudget[key] * scenario.multipliers[key]).toFixed(2);
      }
    });

    return result;
  }

  function getTotalCommitted(values) {
    return Object.values(values).reduce((sum, value) => sum + value, 0);
  }

  function getLargestCategory(values) {
    const filtered = Object.entries(values).filter(([, value]) => value > 0);
    if (!filtered.length) return "Nenhuma categoria";

    const [key] = filtered.sort((a, b) => b[1] - a[1])[0];

    const labels = {
      housing: "Moradia",
      food: "Alimentação",
      transport: "Transporte",
      utilities: "Contas e utilidades",
      extras: "Lazer e extras"
    };

    return labels[key];
  }

  function getStatus(percent) {
    if (percent <= state.idealLimit) {
      return {
        label: "Saudável",
        text: "Seu orçamento ainda mantém boa margem de segurança.",
        reading: "Orçamento saudável",
        suggestion: "Preservar a folga atual e controlar lazer/extras"
      };
    }

    if (percent <= state.warningLimit) {
      return {
        label: "Atenção",
        text: "Seu orçamento entrou em uma faixa que exige mais controle.",
        reading: "Orçamento em atenção",
        suggestion: "Evitar novos gastos variáveis e revisar categorias flexíveis"
      };
    }

    return {
      label: "Pressionado",
      text: "Seu orçamento está em uma faixa arriscada para o restante do mês.",
      reading: "Orçamento pressionado",
      suggestion: "Reduzir despesas flexíveis e revisar custos fixos imediatamente"
    };
  }

  function updateScenarioSelection() {
    document.querySelectorAll(".budget-sim-option").forEach((item) => {
      item.classList.toggle(
        "budget-sim-option--active",
        item.getAttribute("data-scenario") === state.scenario
      );
    });
  }

  function updateCategoryToggles() {
    const toggleHousing = document.getElementById("toggleHousing");
    const toggleFood = document.getElementById("toggleFood");
    const toggleTransport = document.getElementById("toggleTransport");
    const toggleUtilities = document.getElementById("toggleUtilities");
    const toggleExtras = document.getElementById("toggleExtras");

    if (toggleHousing) toggleHousing.checked = state.enabled.housing;
    if (toggleFood) toggleFood.checked = state.enabled.food;
    if (toggleTransport) toggleTransport.checked = state.enabled.transport;
    if (toggleUtilities) toggleUtilities.checked = state.enabled.utilities;
    if (toggleExtras) toggleExtras.checked = state.enabled.extras;
  }

  function updateUI() {
    const values = getCurrentCategoryValues();
    const committed = getTotalCommitted(values);
    const remaining = Math.max(state.income - committed, 0);
    const percent = state.income > 0 ? (committed / state.income) * 100 : 0;
    const status = getStatus(percent);
    const activeCount = Object.values(state.enabled).filter(Boolean).length;
    const largestCategory = getLargestCategory(values);

    const incomeValue = document.getElementById("incomeValue");
    const committedValue = document.getElementById("committedValue");
    const remainingValue = document.getElementById("remainingValue");

    if (incomeValue) incomeValue.textContent = formatBRL(state.income);
    if (committedValue) committedValue.textContent = formatBRL(committed);
    if (remainingValue) remainingValue.textContent = formatBRL(remaining);

    const budgetPercent = document.getElementById("budgetPercent");
    const budgetRing = document.getElementById("budgetRing");
    const idealRangeText = document.getElementById("idealRangeText");
    const budgetStatusText = document.getElementById("budgetStatusText");
    const bufferValue = document.getElementById("bufferValue");

    if (budgetPercent) budgetPercent.textContent = Math.round(percent) + "%";
    if (budgetRing) budgetRing.style.setProperty("--percent", percent.toFixed(1));
    if (idealRangeText) idealRangeText.textContent = "até " + state.idealLimit + "%";
    if (budgetStatusText) budgetStatusText.textContent = status.label;
    if (bufferValue) bufferValue.textContent = formatBRL(remaining);

    const summaryAvailable = document.getElementById("summaryAvailable");
    const summaryRange = document.getElementById("summaryRange");
    const summaryRangeText = document.getElementById("summaryRangeText");
    const summaryCategories = document.getElementById("summaryCategories");

    if (summaryAvailable) summaryAvailable.textContent = formatBRL(remaining);
    if (summaryRange) summaryRange.textContent = status.label;
    if (summaryRangeText) summaryRangeText.textContent = status.text;
    if (summaryCategories) summaryCategories.textContent = activeCount + " categorias";

    const readingStatus = document.getElementById("readingStatus");
    const readingCategory = document.getElementById("readingCategory");
    const readingSuggestion = document.getElementById("readingSuggestion");
    const readingScenario = document.getElementById("readingScenario");
    const readingIdealLimit = document.getElementById("readingIdealLimit");
    const readingWarningLimit = document.getElementById("readingWarningLimit");

    if (readingStatus) readingStatus.textContent = status.reading;
    if (readingCategory) readingCategory.textContent = largestCategory;
    if (readingSuggestion) readingSuggestion.textContent = status.suggestion;
    if (readingScenario) readingScenario.textContent = scenarios[state.scenario].label;
    if (readingIdealLimit) readingIdealLimit.textContent = "Até " + state.idealLimit + "%";
    if (readingWarningLimit) readingWarningLimit.textContent = "Acima de " + state.warningLimit + "%";

    const detailIncome = document.getElementById("detailIncome");
    const detailCommitted = document.getElementById("detailCommitted");
    const detailPercent = document.getElementById("detailPercent");
    const detailRemaining = document.getElementById("detailRemaining");

    if (detailIncome) detailIncome.textContent = formatBRL(state.income);
    if (detailCommitted) detailCommitted.textContent = formatBRL(committed);
    if (detailPercent) detailPercent.textContent = Math.round(percent) + "%";
    if (detailRemaining) detailRemaining.textContent = formatBRL(remaining);

    const percentKeys = {
      housing: "housingPercent",
      food: "foodPercent",
      transport: "transportPercent",
      utilities: "utilitiesPercent",
      extras: "extrasPercent"
    };

    Object.keys(values).forEach((key) => {
      const valueEl = document.querySelector(`.cat-value[data-key="${key}"]`);
      const percentEl = document.querySelector(`.cat-percent[data-key="${percentKeys[key]}"]`);
      const barEl = document.querySelector(`[data-key="${key}Bar"]`);

      const catPercent = state.income > 0 ? (values[key] / state.income) * 100 : 0;

      if (valueEl) valueEl.textContent = formatBRL(values[key]);
      if (percentEl) percentEl.textContent = catPercent.toFixed(1).replace(".", ",") + "%";
      if (barEl) barEl.style.width = catPercent.toFixed(1) + "%";
    });

    updateScenarioSelection();
    updateCategoryToggles();
  }

  Object.entries(budgetModalMap).forEach(([buttonId, modalId]) => {
    const button = document.getElementById(buttonId);
    if (!button) return;
    button.addEventListener("click", () => openBudgetModal(modalId));
  });

  const openIncomeModalButton = document.getElementById("openIncomeModal");
  if (openIncomeModalButton) {
    openIncomeModalButton.addEventListener("click", () => {
      const latestDashboardBalance = getStoredDashboardBalance();
      if (latestDashboardBalance !== null) {
        state.income = latestDashboardBalance;
      }
      syncIncomeInput();
    });
  }

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

  document.querySelectorAll(".budget-sim-option").forEach((option) => {
    option.addEventListener("click", () => {
      state.scenario = option.getAttribute("data-scenario");
      updateUI();
    });
  });

  const saveIncomeBtn = document.getElementById("saveIncomeBtn");
  if (saveIncomeBtn) {
    saveIncomeBtn.addEventListener("click", () => {
      const input = document.getElementById("incomeInput");
      const value = input ? parseBRLToNumber(input.value) : 0;

      if (value > 0) {
        state.income = value;
        localStorage.setItem("finmapSaldoTotal", formatBRL(value));
        syncIncomeInput();
        updateUI();
        closeBudgetModal("incomeModal");
      }
    });
  }

  const savePlannerBtn = document.getElementById("savePlannerBtn");
  if (savePlannerBtn) {
    savePlannerBtn.addEventListener("click", () => {
      const idealInput = document.getElementById("idealLimitInput");
      const warningInput = document.getElementById("warningLimitInput");

      const ideal = idealInput ? parseFloat(idealInput.value) : NaN;
      const warning = warningInput ? parseFloat(warningInput.value) : NaN;

      if (!isNaN(ideal) && !isNaN(warning) && ideal > 0 && warning > ideal) {
        state.idealLimit = ideal;
        state.warningLimit = warning;
        updateUI();
        closeBudgetModal("plannerModal");
      }
    });
  }

  const toggleHousing = document.getElementById("toggleHousing");
  const toggleFood = document.getElementById("toggleFood");
  const toggleTransport = document.getElementById("toggleTransport");
  const toggleUtilities = document.getElementById("toggleUtilities");
  const toggleExtras = document.getElementById("toggleExtras");

  if (toggleHousing) {
    toggleHousing.addEventListener("change", (e) => {
      state.enabled.housing = e.target.checked;
      updateUI();
    });
  }

  if (toggleFood) {
    toggleFood.addEventListener("change", (e) => {
      state.enabled.food = e.target.checked;
      updateUI();
    });
  }

  if (toggleTransport) {
    toggleTransport.addEventListener("change", (e) => {
      state.enabled.transport = e.target.checked;
      updateUI();
    });
  }

  if (toggleUtilities) {
    toggleUtilities.addEventListener("change", (e) => {
      state.enabled.utilities = e.target.checked;
      updateUI();
    });
  }

  if (toggleExtras) {
    toggleExtras.addEventListener("change", (e) => {
      state.enabled.extras = e.target.checked;
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

  const incomeInput = document.getElementById("incomeInput");
  if (incomeInput) {
    incomeInput.addEventListener("input", function () {
      this.value = formatCurrencyInput(this.value);
    });

    incomeInput.addEventListener("focus", function () {
      if (!this.value.trim()) {
        this.value = "R$ 0,00";
      }
    });
  }

  const storedDashboardBalance = getStoredDashboardBalance();
  if (storedDashboardBalance !== null) {
    state.income = storedDashboardBalance;
  }

  syncIncomeInput();

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

  updateUI();
</script>

</body>
</html>