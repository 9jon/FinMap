<?php

session_start();
include '../config/conn.php';

// Por enquanto fixo, até o login gravar isso na sessão de verdade
$usuario_id = $_SESSION['usuario_id'] ?? 1;

// --- Dados do usuário (nome, iniciais, saldo) ---
$stmt = $conn->prepare("SELECT nome, avatar_iniciais, saldo_total FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$primeiroNome = explode(' ', $usuario['nome'] ?? 'Usuário')[0];
$iniciais = $usuario['avatar_iniciais'] ?? 'US';
$saldoTotal = $usuario['saldo_total'] ?? 0;

// --- Receitas do mês atual ---
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(valor), 0) AS total
    FROM transacoes
    WHERE usuario_id = ? AND tipo = 'receita' AND status = 'aprovado'
      AND MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$receitasMes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// --- Despesas do mês atual ---
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(valor), 0) AS total
    FROM transacoes
    WHERE usuario_id = ? AND tipo = 'despesa' AND status = 'aprovado'
      AND MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$despesasMes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// --- Últimas 5 transações aprovadas ---
$stmt = $conn->prepare("
    SELECT t.id, t.descricao, t.valor, t.tipo, t.data_transacao, c.nome AS categoria_nome
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND t.status = 'aprovado'
    ORDER BY t.data_transacao DESC
    LIMIT 5
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$transacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Até 3 metas mais recentes ---
$stmt = $conn->prepare("
    SELECT id, nome, valor_meta, valor_guardado, icone, cor
    FROM metas_financeiras
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
    LIMIT 3
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$metas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FinMap Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
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
          <p>Dashboard principal inteligente</p>
        </div>
      </div>
    </div>

    <div class="topbar-right">
      <button class="ai-btn" id="openAiModal" type="button" aria-label="Abrir Assistente IA">
        <i class="bi bi-stars"></i>
        <span>Assistente IA</span>
      </button>

      <button class="icon-plain" id="openSettingsModal" type="button" aria-label="Configurações">
  <i class="bi bi-gear"></i>
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

  <section class="finance-overview">
    <div class="overview-header">
      <div class="overview-title-group">
        <h2>Olá, <?= htmlspecialchars($primeiroNome) ?>!</h2>
        <p>Aqui está um resumo inteligente das suas finanças hoje.</p>
      </div>
    </div>

    <div class="overview-cards">
      <article class="finance-card finance-card--highlight">
        <div class="finance-card__top">
          <div class="finance-card__main">
            <div class="finance-card__label-row">
              <span class="finance-card__label">Saldo total</span>

              <button
                class="finance-card__edit-btn"
                id="openBalanceModal"
                type="button"
                aria-label="Editar saldo total"
              >
                <i class="bi bi-pencil-square"></i>
              </button>
            </div>

            <strong class="finance-card__value">R$ <?= number_format($saldoTotal, 2, ',', '.') ?></strong>
          </div>

          <div class="finance-card__icon finance-card__icon--glass">
            <i class="bi bi-wallet2"></i>
          </div>
        </div>

        <div class="finance-card__bottom">
          <span class="finance-card__trend finance-card__trend--light">
            <i class="bi bi-arrow-up-right"></i>
            +12,5% este mês
          </span>
        </div>
      </article>

      <article class="finance-card">
        <div class="finance-card__top">
          <div>
            <span class="finance-card__label">Receitas</span>
            <strong class="finance-card__value">R$ <?= number_format($receitasMes, 2, ',', '.') ?></strong>
          </div>

          <div class="finance-card__icon finance-card__icon--green-soft">
            <i class="bi bi-arrow-up-right"></i>
          </div>
        </div>

        <div class="finance-card__bottom">
          <span class="finance-card__trend finance-card__trend--positive">
            <i class="bi bi-graph-up-arrow"></i>
            +8,2% vs mês anterior
          </span>
        </div>
      </article>

      <article class="finance-card">
        <div class="finance-card__top">
          <div>
            <span class="finance-card__label">Despesas</span>
            <strong class="finance-card__value">R$ <?= number_format($despesasMes, 2, ',', '.') ?></strong>
          </div>

          <div class="finance-card__icon finance-card__icon--red-soft">
            <i class="bi bi-arrow-down-right"></i>
          </div>
        </div>

        <div class="finance-card__bottom">
          <span class="finance-card__trend finance-card__trend--negative">
            <i class="bi bi-graph-down-arrow"></i>
            -3,5% vs mês anterior
          </span>
        </div>
      </article>
    </div>
  </section>

  <section class="main-actions">
    <div class="main-actions__grid">
  <article class="action-card action-card--green" data-link-page="orcamento-mensal.php">
    <div class="action-icon">
      <i class="bi bi-calendar2-week"></i>
    </div>
    <h4>Orçamento mensal</h4>
    <p>Veja quanto da sua renda já foi comprometido neste mês</p>
  </article>

  <article class="action-card action-card--purple" data-link-page="metas-financeiras.php">
    <div class="action-icon">
      <i class="bi bi-bullseye"></i>
    </div>
    <h4>Metas financeiras</h4>
    <p>Defina objetivos e acompanhe sua evolução com clareza</p>
  </article>

  <article class="action-card action-card--orange" data-link-page="poupanca-invisivel.php">
    <div class="action-icon">
      <i class="bi bi-piggy-bank"></i>
    </div>
    <h4>Poupança invisível</h4>
    <p>Converta desperdícios recorrentes em reserva automática</p>
  </article>

  <article class="action-card action-card--red" data-link-page="alertas-preditivos.php">
    <div class="action-icon">
      <i class="bi bi-exclamation-triangle"></i>
    </div>
    <h4>Alertas preditivos</h4>
    <p>Receba avisos antes de um possível desequilíbrio financeiro</p>
  </article>

  <article class="action-card action-card--blue" data-link-page="revisar-lancamentos.php">
    <div class="action-icon">
      <i class="bi bi-check2-square"></i>
    </div>
    <h4>Revisar lançamentos</h4>
    <p>Valide capturas automáticas vindas de OCR, SMS ou importação</p>
  </article>
</div>

    <div class="dashboard-panels">
      <section class="recent-transactions-panel">
        <div class="recent-transactions-panel__header">
          <div>
            <h3>Transações recentes</h3>
            <p>Últimas movimentações registradas e consolidadas pelo FinMap</p>
          </div>

          <a href="#" class="recent-transactions-panel__link" id="openAllTransactionsModal">
            Ver todas
            <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <div class="recent-transactions-panel__list">
          <?php if (empty($transacoes)): ?>
            <p style="padding: 16px 0; color: #888;">Nenhuma transação registrada ainda.</p>
          <?php else: ?>
            <?php foreach ($transacoes as $t): ?>
              <?php $isReceita = $t['tipo'] === 'receita'; ?>
              <article class="transaction-item">
                <div class="transaction-item__left">
                  <div class="transaction-item__icon transaction-item__icon--<?= $isReceita ? 'income' : 'expense' ?>">
                    <i class="bi bi-arrow-<?= $isReceita ? 'up' : 'down' ?>-right"></i>
                  </div>

                  <div class="transaction-item__info">
                    <h4><?= htmlspecialchars($t['descricao']) ?></h4>
                    <p><?= htmlspecialchars($t['categoria_nome'] ?? 'Sem categoria') ?></p>
                  </div>
                </div>

                <div class="transaction-item__right">
                  <strong class="transaction-item__value transaction-item__value--<?= $isReceita ? 'positive' : 'negative' ?>">
                    <?= $isReceita ? '+ ' : '' ?>R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                  </strong>
                  <span class="transaction-item__date"><?= date('d M', strtotime($t['data_transacao'])) ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="financial-goals-panel">
        <div class="financial-goals-panel__header">
          <div>
            <h3>Metas financeiras</h3>
            <p>Acompanhe o progresso dos seus objetivos</p>
          </div>

          <button class="financial-goals-panel__menu" id="openGoalsMenuModal" type="button" aria-label="Mais opções">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
        </div>

        <div class="financial-goals-list" id="dashboardGoalsList">
          <?php if (empty($metas)): ?>
            <article class="goal-card">
              <div class="goal-card__top">
                <div class="goal-card__left">
                  <div class="goal-card__icon goal-card__icon--green">
                    <i class="bi bi-bullseye"></i>
                  </div>
                  <div class="goal-card__info">
                    <h4>Nenhuma meta criada</h4>
                    <p>Crie sua primeira meta para acompanhar aqui no dashboard</p>
                  </div>
                </div>
                <span class="goal-card__badge">0%</span>
              </div>
              <div class="goal-card__progress">
                <div class="goal-card__progress-fill goal-card__progress-fill--green" style="width: 0%;"></div>
              </div>
              <div class="goal-card__bottom">
                <span>0% concluído</span>
                <span>Sem metas ativas</span>
              </div>
            </article>
          <?php else: ?>
            <?php foreach ($metas as $m):
              $percent = $m['valor_meta'] > 0 ? min(($m['valor_guardado'] / $m['valor_meta']) * 100, 100) : 0;
              $restante = max($m['valor_meta'] - $m['valor_guardado'], 0);
            ?>
              <article class="goal-card">
                <div class="goal-card__top">
                  <div class="goal-card__left">
                    <div class="goal-card__icon goal-card__icon--<?= htmlspecialchars($m['cor']) ?>">
                      <i class="bi bi-<?= htmlspecialchars($m['icone']) ?>"></i>
                    </div>

                    <div class="goal-card__info">
                      <h4><?= htmlspecialchars($m['nome']) ?></h4>
                      <p>R$ <?= number_format($m['valor_guardado'], 2, ',', '.') ?> de R$ <?= number_format($m['valor_meta'], 2, ',', '.') ?></p>
                    </div>
                  </div>

                  <span class="goal-card__badge"><?= round($percent) ?>%</span>
                </div>

                <div class="goal-card__progress">
                  <div class="goal-card__progress-fill goal-card__progress-fill--<?= htmlspecialchars($m['cor']) ?>" style="width: <?= $percent ?>%;"></div>
                </div>

                <div class="goal-card__bottom">
                  <span><?= round($percent) ?>% concluído</span>
                  <span>Faltam R$ <?= number_format($restante, 2, ',', '.') ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <button class="new-goal-btn" id="goToGoalsPageBtn" type="button">
          <i class="bi bi-plus-lg"></i>
          Nova meta
        </button>
      </section>
    </div>

    <div class="dashboard-bottom-row">
      <section class="category-expenses-panel">
        <div class="category-expenses-panel__header">
          <div>
            <h3>Gastos por categoria</h3>
            <p>Distribuição atual das principais despesas do mês</p>
          </div>

          <button class="category-expenses-panel__filter" id="openCategoryPeriodModal" type="button">
            <i class="bi bi-sliders"></i>
            <span id="categoryPeriodLabel">Este mês</span>
          </button>
        </div>

        <div class="category-expenses-list">
          <article class="category-expense-card">
            <div class="category-expense-card__top">
              <div class="category-expense-card__left">
                <div class="category-expense-card__icon category-expense-card__icon--green">
                  <i class="bi bi-basket2"></i>
                </div>

                <div class="category-expense-card__info">
                  <h4>Alimentação</h4>
                  <p>Maior gasto recorrente do período</p>
                </div>
              </div>

              <div class="category-expense-card__right">
                <strong>R$ 850</strong>
                <span>34%</span>
              </div>
            </div>

            <div class="category-expense-card__bar">
              <div class="category-expense-card__fill category-expense-card__fill--green" style="width: 34%;"></div>
            </div>
          </article>

          <article class="category-expense-card">
            <div class="category-expense-card__top">
              <div class="category-expense-card__left">
                <div class="category-expense-card__icon category-expense-card__icon--blue">
                  <i class="bi bi-bus-front"></i>
                </div>

                <div class="category-expense-card__info">
                  <h4>Transporte</h4>
                  <p>Custos com mobilidade no mês atual</p>
                </div>
              </div>

              <div class="category-expense-card__right">
                <strong>R$ 450</strong>
                <span>18%</span>
              </div>
            </div>

            <div class="category-expense-card__bar">
              <div class="category-expense-card__fill category-expense-card__fill--blue" style="width: 18%;"></div>
            </div>
          </article>

          <article class="category-expense-card">
            <div class="category-expense-card__top">
              <div class="category-expense-card__left">
                <div class="category-expense-card__icon category-expense-card__icon--purple">
                  <i class="bi bi-house-door"></i>
                </div>

                <div class="category-expense-card__info">
                  <h4>Moradia</h4>
                  <p>Despesas fixas ligadas à residência</p>
                </div>
              </div>

              <div class="category-expense-card__right">
                <strong>R$ 1.200</strong>
                <span>48%</span>
              </div>
            </div>

            <div class="category-expense-card__bar">
              <div class="category-expense-card__fill category-expense-card__fill--purple" style="width: 48%;"></div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </section>

  <button class="floating-transaction-btn" id="openTransactionModal" type="button" aria-label="Adicionar transação">
    <span class="floating-transaction-btn__icon">
      <i class="bi bi-plus-lg"></i>
    </span>
    <span class="floating-transaction-btn__content">
      <strong>Nova transação</strong>
      <small>Adicionar rapidamente</small>
    </span>
  </button>

  <!-- MODAL EDITAR SALDO  --> 
  <div class="balance-modal-overlay" id="balanceModal">
    <div class="balance-modal">
      <div class="balance-modal__header">
        <div class="balance-modal__title">
          <div class="balance-modal__icon">
            <i class="bi bi-pencil-square"></i>
          </div>
          <div>
            <h3>Editar saldo total</h3>
            <p>Atualize o valor principal exibido no seu dashboard.</p>
          </div>
        </div>

        <button class="balance-modal__close" id="closeBalanceModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form class="balance-modal__form" id="balanceForm">
        <label class="balance-modal__field">
          <span>Novo saldo</span>
          <input
            type="text"
            id="saldoEditInput"
            class="finmap-money-input"
            inputmode="numeric"
            placeholder="R$ 0,00"
            autocomplete="off"
          >
        </label>

        <div class="balance-modal__actions">
          <button class="balance-modal__secondary" id="cancelBalanceModal" type="button">Cancelar</button>
          <button class="balance-modal__primary" type="submit">Salvar saldo</button>
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
          <h4>Alerta preditivo de orçamento</h4>
          <p>Seu ritmo atual de gastos pode comprometer o fechamento do mês.</p>
          <span>Agora mesmo</span>
        </div>
      </article>

      <article class="notif-card notif-card--success">
        <div class="notif-card__icon">
          <i class="bi bi-bullseye"></i>
        </div>
        <div class="notif-card__content">
          <h4>Meta avançando bem</h4>
          <p>Sua reserva de emergência atingiu 58% do valor planejado.</p>
          <span>Hoje, 09:20</span>
        </div>
      </article>

      <article class="notif-card notif-card--warning">
        <div class="notif-card__icon">
          <i class="bi bi-receipt"></i>
        </div>
        <div class="notif-card__content">
          <h4>Gasto acima da média</h4>
          <p>A categoria alimentação ficou acima da média prevista nesta semana.</p>
          <span>Hoje, 08:05</span>
        </div>
      </article>

      <article class="notif-card notif-card--info">
        <div class="notif-card__icon">
          <i class="bi bi-stars"></i>
        </div>
        <div class="notif-card__content">
          <h4>Sugestão da IA</h4>
          <p>Você pode economizar até R$ 240 ajustando despesas recorrentes.</p>
          <span>Ontem</span>
        </div>
      </article>
    </div>
  </aside>

  <!-- MODAL CENTRALIZADO DA IA -->
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
            <strong>Saúde financeira estável</strong>
            <p>Seu saldo segue positivo, mas moradia e alimentação concentram grande parte das despesas.</p>
          </div>

          <div class="ai-summary-card ai-summary-card--soft">
            <div class="ai-summary-card__top">
              <span class="ai-summary-card__label">Sugestão principal</span>
              <i class="bi bi-lightbulb"></i>
            </div>
            <strong>Reduzir gastos variáveis</strong>
            <p>Uma redução de 8% nos gastos variáveis pode acelerar sua reserva de emergência.</p>
          </div>

          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button">Analisar meu mês</button>
            <button class="ai-shortcut" type="button">Onde posso economizar?</button>
            <button class="ai-shortcut" type="button">Revisar minhas metas</button>
            <button class="ai-shortcut" type="button">Criar plano rápido</button>
          </div>
        </aside>

        <section class="ai-chat-area">
          <div class="ai-chat-area__messages">
            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Olá, <?= htmlspecialchars($primeiroNome) ?>. Fiz uma leitura geral das suas finanças e identifiquei bons sinais de estabilidade. Quer que eu te mostre onde estão suas melhores oportunidades de economia?
              </div>
            </div>

            <div class="ai-message ai-message--user">
              <div class="ai-message__bubble">
                Sim, quero ver os principais pontos.
              </div>
            </div>

            <div class="ai-message ai-message--bot">
              <div class="ai-message__bubble">
                Perfeito. Hoje seus maiores focos de atenção estão em alimentação, moradia e despesas recorrentes menores que, somadas, têm bastante impacto no mês.
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

  <!-- MODAL DE TRANSAÇÃO -->
  <div class="transaction-modal-overlay" id="transactionModal">
    <div class="transaction-modal">
      <div class="transaction-modal__header">
        <div class="transaction-modal__title-group">
          <div class="transaction-modal__icon">
            <i class="bi bi-plus-lg"></i>
          </div>

          <div>
            <h3>Adicionar Transação</h3>
            <p>Escolha como deseja registrar uma nova movimentação no FinMap</p>
          </div>
        </div>

        <button class="transaction-modal__close" id="closeTransactionModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="transaction-modal__options">
        <button class="transaction-option-card" type="button">
          <div class="transaction-option-card__icon transaction-option-card__icon--green">
            <i class="bi bi-pencil-square"></i>
          </div>

          <div class="transaction-option-card__content">
            <h4>Adicionar manualmente</h4>
            <p>Preencha os dados da transação de forma personalizada e imediata.</p>
          </div>
        </button>

        <button class="transaction-option-card" type="button">
          <div class="transaction-option-card__icon transaction-option-card__icon--orange">
            <i class="bi bi-file-earmark-arrow-up"></i>
          </div>

          <div class="transaction-option-card__content">
            <h4>Importar arquivo</h4>
            <p>Envie arquivos CSV, OFX ou Excel para importar transações em lote.</p>
          </div>
        </button>

        <button class="transaction-option-card" type="button">
          <div class="transaction-option-card__icon transaction-option-card__icon--purple">
            <i class="bi bi-receipt-cutoff"></i>
          </div>

          <div class="transaction-option-card__content">
            <div class="transaction-option-card__topline">
              <h4>OCR de notas fiscais</h4>
              <span class="transaction-option-card__badge">IA</span>
            </div>
            <p>Escaneie notas fiscais e deixe a inteligência artificial identificar os dados automaticamente.</p>
          </div>
        </button>

        <button class="transaction-option-card" type="button">
          <div class="transaction-option-card__icon transaction-option-card__icon--blue">
            <i class="bi bi-chat-square-text"></i>
          </div>

          <div class="transaction-option-card__content">
            <div class="transaction-option-card__topline">
              <h4>Revisar capturas automáticas</h4>
              <span class="transaction-option-card__badge">SMS</span>
            </div>
            <p>Valide movimentações detectadas automaticamente para manter seus dados consistentes.</p>
          </div>
        </button>
      </div>
    </div>
  </div>

<!-- MODAL CENTRALIZADO DE CONFIGURAÇÕES -->
<div class="settings-modal-overlay" id="settingsModal">
  <div class="settings-modal">
    <div class="settings-modal__header">
      <div class="settings-modal__title-group">
        <div class="settings-modal__icon">
          <i class="bi bi-gear-fill"></i>
        </div>

        <div>
          <h3>Configurações</h3>
          <p>Gerencie alertas, integração bancária, segurança e conta.</p>
        </div>
      </div>

      <button class="settings-modal__close" id="closeSettingsModal" type="button" aria-label="Fechar configurações">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="settings-modal__body">
      <aside class="settings-sidebar">
        <button class="settings-sidebar__item active" type="button" data-target="settings-notifications">
          <span class="settings-sidebar__icon settings-sidebar__icon--yellow">
            <i class="bi bi-bell-fill"></i>
          </span>
          <span class="settings-sidebar__text">Notificações</span>
        </button>

        <button class="settings-sidebar__item" type="button" data-target="settings-bank">
          <span class="settings-sidebar__icon settings-sidebar__icon--blue">
            <i class="bi bi-bank2"></i>
          </span>
          <span class="settings-sidebar__text">API bancária</span>
        </button>

        <button class="settings-sidebar__item" type="button" data-target="settings-security">
          <span class="settings-sidebar__icon settings-sidebar__icon--purple">
            <i class="bi bi-shield-lock-fill"></i>
          </span>
          <span class="settings-sidebar__text">Proteção</span>
        </button>

        <button class="settings-sidebar__item" type="button" data-target="settings-account">
          <span class="settings-sidebar__icon settings-sidebar__icon--red">
            <i class="bi bi-person-circle"></i>
          </span>
          <span class="settings-sidebar__text">Conta</span>
        </button>
      </aside>

      <div class="settings-content">
        <!-- NOTIFICAÇÕES -->
        <section class="settings-group" id="settings-notifications">
          <button class="settings-group__trigger active" type="button">
            <div class="settings-group__trigger-left">
              <span class="settings-group__badge settings-group__badge--yellow">
                <i class="bi bi-bell-fill"></i>
              </span>
              <div>
                <h4>Notificações</h4>
                <p>Alertas mais importantes do sistema</p>
              </div>
            </div>

            <span class="settings-group__arrow">
              <i class="bi bi-chevron-down"></i>
            </span>
          </button>

          <div class="settings-group__content open">
            <div class="settings-list">
              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Alertas preditivos</h5>
                  <p>Avisos quando houver risco no orçamento.</p>
                </div>
                <label class="switch switch--yellow">
                  <input type="checkbox" checked>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Metas financeiras</h5>
                  <p>Notificações de progresso e conclusão.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" checked>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Sugestões da IA</h5>
                  <p>Recomendações rápidas do assistente.</p>
                </div>
                <label class="switch switch--purple">
                  <input type="checkbox" checked>
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>
          </div>
        </section>

        <!-- API BANCÁRIA -->
        <section class="settings-group" id="settings-bank">
          <button class="settings-group__trigger" type="button">
            <div class="settings-group__trigger-left">
              <span class="settings-group__badge settings-group__badge--blue">
                <i class="bi bi-bank2"></i>
              </span>
              <div>
                <h4>API bancária</h4>
                <p>Conexão e sincronização das contas</p>
              </div>
            </div>

            <span class="settings-group__arrow">
              <i class="bi bi-chevron-down"></i>
            </span>
          </button>

          <div class="settings-group__content">
            <div class="settings-bank-status">
              <div>
                <h5>Banco principal conectado</h5>
                <p>Sincronização automática ativa.</p>
              </div>

              <span class="settings-pill settings-pill--success">Conectado</span>
            </div>

            <div class="settings-list">
              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Sincronização automática</h5>
                  <p>Atualiza transações e saldo automaticamente.</p>
                </div>
                <label class="switch switch--blue">
                  <input type="checkbox" checked>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Atualizar saldo total</h5>
                  <p>Usa os dados da API para atualizar o dashboard.</p>
                </div>
                <label class="switch switch--blue">
                  <input type="checkbox" checked>
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>

            <div class="settings-actions-row">
              <button class="settings-action-btn settings-action-btn--blue" type="button">
                <i class="bi bi-arrow-repeat"></i>
                Reconectar
              </button>

              <button class="settings-action-btn settings-action-btn--blue-soft" type="button">
                <i class="bi bi-link-45deg"></i>
                Gerenciar contas
              </button>
            </div>
          </div>
        </section>

        <!-- PROTEÇÃO -->
        <section class="settings-group" id="settings-security">
          <button class="settings-group__trigger" type="button">
            <div class="settings-group__trigger-left">
              <span class="settings-group__badge settings-group__badge--purple">
                <i class="bi bi-shield-lock-fill"></i>
              </span>
              <div>
                <h4>Proteção</h4>
                <p>Segurança da conta e dos dados</p>
              </div>
            </div>

            <span class="settings-group__arrow">
              <i class="bi bi-chevron-down"></i>
            </span>
          </button>

          <div class="settings-group__content">
            <div class="settings-list">
              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Autenticação em duas etapas</h5>
                  <p>Camada extra de segurança no login.</p>
                </div>
                <label class="switch switch--purple">
                  <input type="checkbox" checked>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Ocultar valores</h5>
                  <p>Esconde os valores em ambientes públicos.</p>
                </div>
                <label class="switch switch--red">
                  <input type="checkbox">
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>

            <div class="settings-actions-row">
              <button class="settings-action-btn settings-action-btn--purple" type="button">
                <i class="bi bi-key-fill"></i>
                Alterar senha
              </button>
            </div>
          </div>
        </section>

        <!-- CONTA -->
        <section class="settings-group" id="settings-account">
          <button class="settings-group__trigger" type="button">
            <div class="settings-group__trigger-left">
              <span class="settings-group__badge settings-group__badge--red">
                <i class="bi bi-person-circle"></i>
              </span>
              <div>
                <h4>Conta</h4>
                <p>Dados principais e ações da conta</p>
              </div>
            </div>

            <span class="settings-group__arrow">
              <i class="bi bi-chevron-down"></i>
            </span>
          </button>

          <div class="settings-group__content">
            <div class="settings-account-grid">
              <article class="settings-profile-card">
                <h5>E-mail principal</h5>
                <p><?= htmlspecialchars($usuario['email'] ?? '—') ?></p>
                <button class="settings-action-btn settings-action-btn--red-soft" type="button">
                  <i class="bi bi-envelope"></i>
                  Alterar e-mail
                </button>
              </article>  

              <article class="settings-profile-card settings-profile-card--danger">
                <h5>Excluir conta</h5>
                <p>Essa ação é permanente.</p>
                <button class="settings-action-btn settings-action-btn--danger" type="button">
                  <i class="bi bi-trash3-fill"></i>
                  Excluir conta
                </button>
              </article>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="settings-modal__footer">
      <button class="settings-footer-btn settings-footer-btn--secondary" id="cancelSettingsModal" type="button">Cancelar</button>
      <button class="settings-footer-btn settings-footer-btn--primary" type="button">Salvar alterações</button>
    </div>
  </div>
</div>


<!-- MODAL: VER TODAS AS TRANSAÇÕES -->
<div class="dashboard-popout-overlay" id="allTransactionsModal">
  <div class="dashboard-popout dashboard-popout--large">
    <div class="dashboard-popout__header">
      <div class="dashboard-popout__title-group">
        <div class="dashboard-popout__icon dashboard-popout__icon--green">
          <i class="bi bi-clock-history"></i>
        </div>
        <div>
          <h3>Todas as transações recentes</h3>
          <p>Veja o histórico consolidado das últimas movimentações do FinMap.</p>
        </div>
      </div>

      <button class="dashboard-popout__close" id="closeAllTransactionsModal" type="button" aria-label="Fechar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="dashboard-popout__body">
      <div class="all-transactions-list">
        <?php if (empty($transacoes)): ?>
          <p style="padding: 16px 0; color: #888;">Nenhuma transação registrada ainda.</p>
        <?php else: ?>
          <?php foreach ($transacoes as $t): ?>
            <?php $isReceita = $t['tipo'] === 'receita'; ?>
            <article class="transaction-item">
              <div class="transaction-item__left">
                <div class="transaction-item__icon transaction-item__icon--<?= $isReceita ? 'income' : 'expense' ?>">
                  <i class="bi bi-arrow-<?= $isReceita ? 'up' : 'down' ?>-right"></i>
                </div>
                <div class="transaction-item__info">
                  <h4><?= htmlspecialchars($t['descricao']) ?></h4>
                  <p><?= htmlspecialchars($t['categoria_nome'] ?? 'Sem categoria') ?></p>
                </div>
              </div>
              <div class="transaction-item__right">
                <strong class="transaction-item__value transaction-item__value--<?= $isReceita ? 'positive' : 'negative' ?>">
                  <?= $isReceita ? '+ ' : '' ?>R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                </strong>
                <span class="transaction-item__date"><?= date('d M', strtotime($t['data_transacao'])) ?></span>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: MENU DE METAS -->
<div class="dashboard-popout-overlay" id="goalsMenuModal">
  <div class="dashboard-popout dashboard-popout--small">
    <div class="dashboard-popout__header">
      <div class="dashboard-popout__title-group">
        <div class="dashboard-popout__icon dashboard-popout__icon--purple">
          <i class="bi bi-bullseye"></i>
        </div>
        <div>
          <h3>Opções de metas</h3>
          <p>Ações rápidas para gerenciar seus objetivos.</p>
        </div>
      </div>

      <button class="dashboard-popout__close" id="closeGoalsMenuModal" type="button" aria-label="Fechar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="dashboard-popout__body">
      <div class="dashboard-menu-list">
        <button class="dashboard-menu-item" id="goToGoalsPageFromMenu" type="button">
          <i class="bi bi-box-arrow-up-right"></i>
          <span>Ver tela completa de metas</span>
        </button>

        <button class="dashboard-menu-item" id="goToGoalsCreateFromMenu" type="button">
          <i class="bi bi-plus-circle"></i>
          <span>Criar nova meta</span>
        </button>

        <button class="dashboard-menu-item" id="refreshGoalsDashboardBtn" type="button">
          <i class="bi bi-arrow-repeat"></i>
          <span>Atualizar metas no dashboard</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: FILTRO DE PERÍODO DE CATEGORIAS -->
<div class="dashboard-popout-overlay" id="categoryPeriodModal">
  <div class="dashboard-popout dashboard-popout--small">
    <div class="dashboard-popout__header">
      <div class="dashboard-popout__title-group">
        <div class="dashboard-popout__icon dashboard-popout__icon--blue">
          <i class="bi bi-sliders"></i>
        </div>
        <div>
          <h3>Período de categorias</h3>
          <p>Escolha o intervalo para visualizar os gastos por categoria.</p>
        </div>
      </div>

      <button class="dashboard-popout__close" id="closeCategoryPeriodModal" type="button" aria-label="Fechar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="dashboard-popout__body">
      <div class="dashboard-period-list">
        <button class="dashboard-period-option active" type="button" data-period="this-month">Este mês</button>
        <button class="dashboard-period-option" type="button" data-period="last-30">Últimos 30 dias</button>
        <button class="dashboard-period-option" type="button" data-period="last-month">Último mês</button>
        <button class="dashboard-period-option" type="button" data-period="last-3-months">Últimos 3 meses</button>
      </div>
    </div>
  </div>
</div>


 <script>
  const body = document.body;

  const openTransactionModal = document.getElementById("openTransactionModal");
  const closeTransactionModal = document.getElementById("closeTransactionModal");
  const transactionModal = document.getElementById("transactionModal");

  const openBalanceModal = document.getElementById("openBalanceModal");
  const closeBalanceModal = document.getElementById("closeBalanceModal");
  const cancelBalanceModal = document.getElementById("cancelBalanceModal");
  const balanceModal = document.getElementById("balanceModal");

  const openNotificationsPanel = document.getElementById("openNotificationsPanel");
  const closeNotificationsPanel = document.getElementById("closeNotificationsPanel");
  const notifPanel = document.getElementById("notifPanel");
  const notifOverlay = document.getElementById("notifOverlay");

  const openAiModalBtn = document.getElementById("openAiModal");
  const closeAiModalBtn = document.getElementById("closeAiModal");
  const aiModal = document.getElementById("aiModal");

  const openSettingsModal = document.getElementById("openSettingsModal");
  const closeSettingsModal = document.getElementById("closeSettingsModal");
  const cancelSettingsModal = document.getElementById("cancelSettingsModal");
  const settingsModal = document.getElementById("settingsModal");

  const settingsSidebarItems = document.querySelectorAll(".settings-sidebar__item");
  const settingsGroups = document.querySelectorAll(".settings-group");
  const settingsTriggers = document.querySelectorAll(".settings-group__trigger");

  const openAllTransactionsModal = document.getElementById("openAllTransactionsModal");
  const closeAllTransactionsModal = document.getElementById("closeAllTransactionsModal");
  const allTransactionsModal = document.getElementById("allTransactionsModal");

  const openGoalsMenuModal = document.getElementById("openGoalsMenuModal");
  const closeGoalsMenuModal = document.getElementById("closeGoalsMenuModal");
  const goalsMenuModal = document.getElementById("goalsMenuModal");

  const openCategoryPeriodModal = document.getElementById("openCategoryPeriodModal");
  const closeCategoryPeriodModal = document.getElementById("closeCategoryPeriodModal");
  const categoryPeriodModal = document.getElementById("categoryPeriodModal");

  const goToGoalsPageBtn = document.getElementById("goToGoalsPageBtn");
  const goToGoalsPageFromMenu = document.getElementById("goToGoalsPageFromMenu");
  const goToGoalsCreateFromMenu = document.getElementById("goToGoalsCreateFromMenu");
  const refreshGoalsDashboardBtn = document.getElementById("refreshGoalsDashboardBtn");

  const dashboardGoalsList = document.getElementById("dashboardGoalsList");
  const categoryPeriodLabel = document.getElementById("categoryPeriodLabel");

  const categoryData = {
    "this-month": {
      label: "Este mês",
      items: [
        { name: "Alimentação", desc: "Maior gasto recorrente do período", value: 850, percent: 34, color: "green", icon: "basket2" },
        { name: "Transporte", desc: "Custos com mobilidade no mês atual", value: 450, percent: 18, color: "blue", icon: "bus-front" },
        { name: "Moradia", desc: "Despesas fixas ligadas à residência", value: 1200, percent: 48, color: "purple", icon: "house-door" }
      ]
    },
    "last-30": {
      label: "Últimos 30 dias",
      items: [
        { name: "Alimentação", desc: "Consumo recorrente do período recente", value: 980, percent: 32, color: "green", icon: "basket2" },
        { name: "Transporte", desc: "Custos de deslocamento acumulados", value: 620, percent: 20, color: "blue", icon: "bus-front" },
        { name: "Moradia", desc: "Despesas fixas da casa", value: 1460, percent: 48, color: "purple", icon: "house-door" }
      ]
    },
    "last-month": {
      label: "Último mês",
      items: [
        { name: "Alimentação", desc: "Gasto médio do último mês fechado", value: 790, percent: 30, color: "green", icon: "basket2" },
        { name: "Transporte", desc: "Mobilidade do mês anterior", value: 500, percent: 19, color: "blue", icon: "bus-front" },
        { name: "Moradia", desc: "Custos fixos do último mês", value: 1320, percent: 51, color: "purple", icon: "house-door" }
      ]
    },
    "last-3-months": {
      label: "Últimos 3 meses",
      items: [
        { name: "Alimentação", desc: "Média acumulada do trimestre", value: 2410, percent: 31, color: "green", icon: "basket2" },
        { name: "Transporte", desc: "Deslocamentos do trimestre", value: 1590, percent: 20, color: "blue", icon: "bus-front" },
        { name: "Moradia", desc: "Despesas residenciais acumuladas", value: 3800, percent: 49, color: "purple", icon: "house-door" }
      ]
    }
  };

  function hasClassSafe(element, className) {
    return element && element.classList.contains(className);
  }

  function lockBody() {
    body.style.overflow = "hidden";
  }

  function unlockBody() {
    const hasOpenModal =
      hasClassSafe(transactionModal, "active") ||
      hasClassSafe(balanceModal, "active") ||
      hasClassSafe(notifPanel, "active") ||
      hasClassSafe(aiModal, "active") ||
      hasClassSafe(settingsModal, "active") ||
      hasClassSafe(allTransactionsModal, "active") ||
      hasClassSafe(goalsMenuModal, "active") ||
      hasClassSafe(categoryPeriodModal, "active");

    body.style.overflow = hasOpenModal ? "hidden" : "";
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add("active");
    lockBody();
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove("active");
    unlockBody();
  }

  function openNotifications() {
    if (!notifPanel || !notifOverlay) return;
    notifPanel.classList.add("active");
    notifOverlay.classList.add("active");
    lockBody();
  }

  function closeNotifications() {
    if (notifPanel) notifPanel.classList.remove("active");
    if (notifOverlay) notifOverlay.classList.remove("active");
    unlockBody();
  }

  function closeAllSettingsSections() {
    settingsGroups.forEach((group) => {
      const trigger = group.querySelector(".settings-group__trigger");
      const content = group.querySelector(".settings-group__content");
      if (trigger) trigger.classList.remove("active");
      if (content) content.classList.remove("open");
    });
  }

  function activateSidebarItemById(groupId) {
    settingsSidebarItems.forEach((item) => {
      item.classList.toggle("active", item.getAttribute("data-target") === groupId);
    });
  }

  function openSettingsSection(groupId, scrollToSection = true) {
    const targetSection = document.getElementById(groupId);
    if (!targetSection) return;

    closeAllSettingsSections();

    const trigger = targetSection.querySelector(".settings-group__trigger");
    const content = targetSection.querySelector(".settings-group__content");

    if (trigger) trigger.classList.add("active");
    if (content) content.classList.add("open");

    activateSidebarItemById(groupId);

    if (scrollToSection) {
      targetSection.scrollIntoView({
        behavior: "smooth",
        block: "start"
      });
    }
  }

  function formatBRL(value) {
    return value.toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL"
    });
  }

  function getGoalColorClass(color) {
    return {
      green: "green",
      blue: "blue",
      orange: "orange",
      purple: "purple"
    }[color] || "green";
  }

  // ATENÇÃO: esta função ainda lê metas do localStorage (era assim no
  // protótipo estático). Como agora o bloco PHP já renderiza as metas
  // reais do banco direto no HTML, NÃO chamamos mais essa função
  // automaticamente ao carregar a página (veja o final do script).
  // Ela fica aqui só para não quebrar o botão "Atualizar metas no
  // dashboard" do menu de metas — quando vocês migrarem a tela
  // metas-financeiras.php para o banco também, essa função deve ser
  // reescrita para buscar as metas via fetch() em vez de localStorage.
  function renderDashboardGoals() {
    if (!dashboardGoalsList) return;

    const savedGoals = JSON.parse(localStorage.getItem("finmap_goals")) || [];

    if (!savedGoals.length) {
      dashboardGoalsList.innerHTML = `
        <article class="goal-card">
          <div class="goal-card__top">
            <div class="goal-card__left">
              <div class="goal-card__icon goal-card__icon--green">
                <i class="bi bi-bullseye"></i>
              </div>
              <div class="goal-card__info">
                <h4>Nenhuma meta criada</h4>
                <p>Crie sua primeira meta para acompanhar aqui no dashboard</p>
              </div>
            </div>
            <span class="goal-card__badge">0%</span>
          </div>
          <div class="goal-card__progress">
            <div class="goal-card__progress-fill goal-card__progress-fill--green" style="width: 0%;"></div>
          </div>
          <div class="goal-card__bottom">
            <span>0% concluído</span>
            <span>Sem metas ativas</span>
          </div>
        </article>
      `;
      return;
    }

    const topGoals = savedGoals.slice(0, 3);

    dashboardGoalsList.innerHTML = topGoals.map((goal) => {
      const target = Number(goal.target) || 0;
      const saved = Number(goal.saved) || 0;
      const percent = target > 0 ? Math.min((saved / target) * 100, 100) : 0;
      const remaining = Math.max(target - saved, 0);
      const color = getGoalColorClass(goal.color);

      return `
        <article class="goal-card">
          <div class="goal-card__top">
            <div class="goal-card__left">
              <div class="goal-card__icon goal-card__icon--${color}">
                <i class="bi bi-${goal.icon || "bullseye"}"></i>
              </div>
              <div class="goal-card__info">
                <h4>${goal.name}</h4>
                <p>${formatBRL(saved)} de ${formatBRL(target)}</p>
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
        </article>
      `;
    }).join("");
  }

  function renderCategoryExpenses(periodKey) {
    const panelList = document.querySelector(".category-expenses-list");
    const data = categoryData[periodKey];
    if (!panelList || !data) return;

    categoryPeriodLabel.textContent = data.label;

    panelList.innerHTML = data.items.map((item) => `
      <article class="category-expense-card">
        <div class="category-expense-card__top">
          <div class="category-expense-card__left">
            <div class="category-expense-card__icon category-expense-card__icon--${item.color}">
              <i class="bi bi-${item.icon}"></i>
            </div>
            <div class="category-expense-card__info">
              <h4>${item.name}</h4>
              <p>${item.desc}</p>
            </div>
          </div>

          <div class="category-expense-card__right">
            <strong>${formatBRL(item.value)}</strong>
            <span>${item.percent}%</span>
          </div>
        </div>

        <div class="category-expense-card__bar">
          <div class="category-expense-card__fill category-expense-card__fill--${item.color}" style="width: ${item.percent}%;"></div>
        </div>
      </article>
    `).join("");

    document.querySelectorAll(".dashboard-period-option").forEach((btn) => {
      btn.classList.toggle("active", btn.getAttribute("data-period") === periodKey);
    });
  }

  document.querySelectorAll("[data-link-page]").forEach((card) => {
    card.addEventListener("click", () => {
      const page = card.getAttribute("data-link-page");
      if (page) window.location.href = page;
    });
  });

  if (goToGoalsPageBtn) {
    goToGoalsPageBtn.addEventListener("click", () => {
      window.location.href = "metas-financeiras.php";
    });
  }

  if (goToGoalsPageFromMenu) {
    goToGoalsPageFromMenu.addEventListener("click", () => {
      window.location.href = "metas-financeiras.php";
    });
  }

  if (goToGoalsCreateFromMenu) {
    goToGoalsCreateFromMenu.addEventListener("click", () => {
      window.location.href = "metas-financeiras.php";
    });
  }

  if (refreshGoalsDashboardBtn) {
    refreshGoalsDashboardBtn.addEventListener("click", () => {
      renderDashboardGoals();
      closeModal(goalsMenuModal);
    });
  }

  if (openTransactionModal) {
    openTransactionModal.addEventListener("click", () => openModal(transactionModal));
  }

  if (closeTransactionModal) {
    closeTransactionModal.addEventListener("click", () => closeModal(transactionModal));
  }

  if (transactionModal) {
    transactionModal.addEventListener("click", (e) => {
      if (e.target === transactionModal) closeModal(transactionModal);
    });
  }


  if (closeBalanceModal) {
    closeBalanceModal.addEventListener("click", () => closeModal(balanceModal));
  }

  if (cancelBalanceModal) {
    cancelBalanceModal.addEventListener("click", () => closeModal(balanceModal));
  }

  if (balanceModal) {
    balanceModal.addEventListener("click", (e) => {
      if (e.target === balanceModal) closeModal(balanceModal);
    });
  }

  if (openNotificationsPanel) {
    openNotificationsPanel.addEventListener("click", openNotifications);
  }

  if (closeNotificationsPanel) {
    closeNotificationsPanel.addEventListener("click", closeNotifications);
  }

  if (notifOverlay) {
    notifOverlay.addEventListener("click", closeNotifications);
  }

  if (openAiModalBtn) {
    openAiModalBtn.addEventListener("click", () => openModal(aiModal));
  }

  if (closeAiModalBtn) {
    closeAiModalBtn.addEventListener("click", () => closeModal(aiModal));
  }

  if (aiModal) {
    aiModal.addEventListener("click", (e) => {
      if (e.target === aiModal) closeModal(aiModal);
    });
  }

  if (openSettingsModal) {
    openSettingsModal.addEventListener("click", () => {
      openModal(settingsModal);
      openSettingsSection("settings-notifications", false);
    });
  }

  if (closeSettingsModal) {
    closeSettingsModal.addEventListener("click", () => closeModal(settingsModal));
  }

  if (cancelSettingsModal) {
    cancelSettingsModal.addEventListener("click", () => closeModal(settingsModal));
  }

  if (settingsModal) {
    settingsModal.addEventListener("click", (e) => {
      if (e.target === settingsModal) closeModal(settingsModal);
    });
  }

  settingsSidebarItems.forEach((item) => {
    item.addEventListener("click", () => {
      const targetId = item.getAttribute("data-target");
      if (!targetId) return;
      openSettingsSection(targetId, true);
    });
  });

  settingsTriggers.forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const group = trigger.closest(".settings-group");
      const content = trigger.nextElementSibling;
      if (!group || !content) return;

      const groupId = group.getAttribute("id");
      const isOpen = trigger.classList.contains("active");

      closeAllSettingsSections();

      if (!isOpen) {
        trigger.classList.add("active");
        content.classList.add("open");
        activateSidebarItemById(groupId);
      } else {
        settingsSidebarItems.forEach((item) => item.classList.remove("active"));
      }
    });
  });

  if (openAllTransactionsModal) {
    openAllTransactionsModal.addEventListener("click", (e) => {
      e.preventDefault();
      openModal(allTransactionsModal);
    });
  }

  if (closeAllTransactionsModal) {
    closeAllTransactionsModal.addEventListener("click", () => closeModal(allTransactionsModal));
  }

  if (allTransactionsModal) {
    allTransactionsModal.addEventListener("click", (e) => {
      if (e.target === allTransactionsModal) closeModal(allTransactionsModal);
    });
  }

  if (openGoalsMenuModal) {
    openGoalsMenuModal.addEventListener("click", () => openModal(goalsMenuModal));
  }

  if (closeGoalsMenuModal) {
    closeGoalsMenuModal.addEventListener("click", () => closeModal(goalsMenuModal));
  }

  if (goalsMenuModal) {
    goalsMenuModal.addEventListener("click", (e) => {
      if (e.target === goalsMenuModal) closeModal(goalsMenuModal);
    });
  }

  if (openCategoryPeriodModal) {
    openCategoryPeriodModal.addEventListener("click", () => openModal(categoryPeriodModal));
  }

  if (closeCategoryPeriodModal) {
    closeCategoryPeriodModal.addEventListener("click", () => closeModal(categoryPeriodModal));
  }

  if (categoryPeriodModal) {
    categoryPeriodModal.addEventListener("click", (e) => {
      if (e.target === categoryPeriodModal) closeModal(categoryPeriodModal);
    });
  }

  document.querySelectorAll(".dashboard-period-option").forEach((button) => {
    button.addEventListener("click", () => {
      const period = button.getAttribute("data-period");
      renderCategoryExpenses(period);
      closeModal(categoryPeriodModal);
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal(transactionModal);
      closeModal(balanceModal);
      closeModal(aiModal);
      closeModal(settingsModal);
      closeModal(allTransactionsModal);
      closeModal(goalsMenuModal);
      closeModal(categoryPeriodModal);
      closeNotifications();
    }
  });

  window.addEventListener("storage", (e) => {
    if (e.key === "finmap_goals") {
      renderDashboardGoals();
    }
  });


  const saldoCardValue = document.querySelector(".finance-card--highlight .finance-card__value");
  const balanceForm = document.getElementById("balanceForm");
  const saldoEditInput = document.getElementById("saldoEditInput");

  function getDigitsOnly(value) {
    return String(value || "").replace(/\D/g, "");
  }

  function formatCurrencyBRL(value) {
    const digits = getDigitsOnly(value);
    const amount = digits ? Number(digits) / 100 : 0;
    return amount.toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL"
    });
  }

  if (saldoEditInput) {
    saldoEditInput.addEventListener("input", () => {
      saldoEditInput.value = formatCurrencyBRL(saldoEditInput.value);
    });

    saldoEditInput.addEventListener("focus", () => {
      saldoEditInput.value = formatCurrencyBRL(saldoEditInput.value);
    });
  }

  if (openBalanceModal) {
    openBalanceModal.addEventListener("click", () => {
      const currentValue = saldoCardValue ? saldoCardValue.textContent : "R$ 0,00";
      if (saldoEditInput) {
        saldoEditInput.value = formatCurrencyBRL(currentValue);
      }
      openModal(balanceModal);
      setTimeout(() => {
        if (saldoEditInput) {
          saldoEditInput.focus();
          const length = saldoEditInput.value.length;
          saldoEditInput.setSelectionRange(length, length);
        }
      }, 60);
    });
  }

  // ATENÇÃO: por enquanto o formulário só atualiza o valor NA TELA
  // (não grava no banco ainda). O próximo passo é criar um endpoint
  // PHP (ex: atualizar-saldo.php) que recebe esse valor via fetch()
  // e faz um UPDATE na tabela usuarios. Sem isso, o saldo volta ao
  // valor do banco toda vez que a página é recarregada.
  function parseCurrencyToNumber(value) {
  const digits = getDigitsOnly(value);
  return digits ? Number(digits) / 100 : 0;
}

if (balanceForm) {
  balanceForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const valorNumerico = parseCurrencyToNumber(saldoEditInput ? saldoEditInput.value : "0");
    const submitBtn = balanceForm.querySelector(".balance-modal__primary");

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Salvando...";
    }

    try {
      const response = await fetch("atualizar-saldo.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ novo_saldo: valorNumerico })
      });

      const resultado = await response.json();

      if (resultado.sucesso) {
        if (saldoCardValue) {
          saldoCardValue.textContent = "R$ " + resultado.novo_saldo;
        }
        closeModal(balanceModal);
      } else {
        alert("Não foi possível salvar o saldo: " + (resultado.erro || "erro desconhecido"));
      }
    } catch (error) {
      alert("Erro de conexão ao salvar o saldo. Tente novamente.");
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Salvar saldo";
      }
    }
  });
}

  // renderDashboardGoals() e syncBalanceFromStorage() NÃO são mais
  // chamadas automaticamente aqui, porque o PHP já renderizou o
  // saldo e as metas reais do banco direto no HTML acima.
  renderCategoryExpenses("this-month");
</script>




</body>
</html>