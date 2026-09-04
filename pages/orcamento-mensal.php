<?php
session_start();
require_once __DIR__ . '/../config/conn.php';
if (!isset($_SESSION['usuario_id'])) {
    $sqlTeste = "SELECT id FROM usuarios WHERE email = 'joao@email.com' LIMIT 1";
    $resultadoTeste = $conn->query($sqlTeste);
    $usuarioTeste = $resultadoTeste ? $resultadoTeste->fetch_assoc() : null;

    if ($usuarioTeste) {
        $_SESSION['usuario_id'] = (int)$usuarioTeste['id'];
    } else {
        die('Nenhum usuário de teste encontrado. Rode o INSERT do usuário de exemplo (joao@email.com) do schema antes de testar esta tela.');
    }
}

$usuario_id = (int)$_SESSION['usuario_id'];

function parseBRLParaFloat(string $valor): float
{

    $limpo = preg_replace('/[\s\x{00A0}]/u', '', $valor);
    $limpo = str_replace('R$', '', $limpo);
    $limpo = str_replace('.', '', $limpo);
    $limpo = str_replace(',', '.', $limpo);
    return (float) $limpo;
}

$cenariosValidos = ['base', 'cauteloso', 'pressionado'];


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
    } elseif ($acao === 'atualizar_limites') {
        $faixaIdeal = (int)($_POST['faixa_ideal'] ?? 70);
        $faixaAlerta = (int)($_POST['faixa_alerta'] ?? 85);

        if ($faixaIdeal > 0 && $faixaIdeal <= 100 && $faixaAlerta > $faixaIdeal && $faixaAlerta <= 100) {
            $sql = "INSERT INTO orcamento_configuracao (usuario_id, faixa_ideal_percentual, faixa_alerta_percentual)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        faixa_ideal_percentual = VALUES(faixa_ideal_percentual),
                        faixa_alerta_percentual = VALUES(faixa_alerta_percentual)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $usuario_id, $faixaIdeal, $faixaAlerta);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($acao === 'selecionar_cenario') {
        $cenario = $_POST['cenario'] ?? 'base';

        if (in_array($cenario, $cenariosValidos, true)) {
            $sql = "INSERT INTO orcamento_configuracao (usuario_id, cenario_selecionado)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE cenario_selecionado = VALUES(cenario_selecionado)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $usuario_id, $cenario);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($acao === 'atualizar_categorias') {
        $categoriasAtivas = $_POST['categoria_ativa'] ?? [];
        $sqlTodas = "SELECT id FROM categorias WHERE usuario_id = ? AND tipo = 'despesa'";
        $stmtTodas = $conn->prepare($sqlTodas);
        $stmtTodas->bind_param("i", $usuario_id);
        $stmtTodas->execute();
        $todasCategorias = $stmtTodas->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtTodas->close();

        $sqlUpdate = "UPDATE categorias SET ativo_no_orcamento = ? WHERE id = ? AND usuario_id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);

        foreach ($todasCategorias as $cat) {
            $ativo = in_array((string)$cat['id'], $categoriasAtivas, true) ? 1 : 0;
            $catId = (int)$cat['id'];
            $stmtUpdate->bind_param("iii", $ativo, $catId, $usuario_id);
            $stmtUpdate->execute();
        }
        $stmtUpdate->close();
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


$sqlConfig = "SELECT faixa_ideal_percentual, faixa_alerta_percentual, cenario_selecionado
              FROM orcamento_configuracao WHERE usuario_id = ?";
$stmtConfig = $conn->prepare($sqlConfig);
$stmtConfig->bind_param("i", $usuario_id);
$stmtConfig->execute();
$config = $stmtConfig->get_result()->fetch_assoc();
$stmtConfig->close();

$faixaIdeal = $config['faixa_ideal_percentual'] ?? 70;
$faixaAlerta = $config['faixa_alerta_percentual'] ?? 85;
$cenarioAtual = $config['cenario_selecionado'] ?? 'base';


// Importações passam a compor o período no momento em que são aprovadas;
// lançamentos manuais preservam a data financeira informada pelo usuário.
// `atualizado_em` é alterado na aprovação e está disponível em todas as versões
// atuais da tabela `transacoes`.
// O LEFT JOIN mantém visíveis as categorias sem gastos no período.
$sqlCategorias = "
    SELECT c.id, c.nome, c.icone, c.cor, c.ativo_no_orcamento,
           COALESCE(SUM(t.valor), 0) AS gasto_real
    FROM categorias c
    LEFT JOIN transacoes t
        ON t.categoria_id = c.id
       AND t.usuario_id = c.usuario_id
       AND t.tipo = 'despesa'
       AND t.status = 'aprovado'
       AND (CASE WHEN t.origem = 'importacao' THEN DATE(t.atualizado_em) ELSE t.data_transacao END) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND (CASE WHEN t.origem = 'importacao' THEN DATE(t.atualizado_em) ELSE t.data_transacao END) < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
    WHERE c.usuario_id = ? AND c.tipo = 'despesa'
    GROUP BY c.id, c.nome, c.icone, c.cor, c.ativo_no_orcamento
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
      AND (CASE WHEN origem = 'importacao' THEN DATE(atualizado_em) ELSE data_transacao END) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND (CASE WHEN origem = 'importacao' THEN DATE(atualizado_em) ELSE data_transacao END) < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
");
$stmtSemCategoria->bind_param("i", $usuario_id);
$stmtSemCategoria->execute();
$gastoSemCategoria = (float) ($stmtSemCategoria->get_result()->fetch_assoc()['gasto_sem_categoria'] ?? 0);
$stmtSemCategoria->close();


$mapaCategoriaParaChave = [
    'Moradia'              => 'housing',
    'Alimentação'          => 'food',
    'Transporte'           => 'transport',
    'Contas e utilidades'  => 'utilities',
    'Lazer e extras'       => 'extras',
];

$multiplicadores = [
    'base'        => ['housing' => 1,    'food' => 1,    'transport' => 1,    'utilities' => 1,    'extras' => 1],
    'cauteloso'   => ['housing' => 1,    'food' => 0.92, 'transport' => 0.9,  'utilities' => 1,    'extras' => 0.65],
    'pressionado' => ['housing' => 1.05, 'food' => 1.1,  'transport' => 1.12, 'utilities' => 1.08, 'extras' => 1.05],
];

$categoriasCalculadas = [];
$comprometido = 0.0;
$categoriasAtivasCount = 0;

foreach ($categorias as $cat) {
    $chave = $mapaCategoriaParaChave[$cat['nome']] ?? null;
    $multiplicador = $chave ? $multiplicadores[$cenarioAtual][$chave] : 1;
    // CORRIGIDO: valor_base agora é o gasto real do mês (gasto_real),
    // não mais a coluna estática valor_base_orcamento.
    $valorBase = (float)($cat['gasto_real'] ?? 0);
    $ativo = (bool)$cat['ativo_no_orcamento'];

    $valorCalculado = $ativo ? round($valorBase * $multiplicador, 2) : 0.0;

    if ($ativo) {
        $comprometido += $valorCalculado;
        $categoriasAtivasCount++;
    }

    $categoriasCalculadas[] = [
        'id'         => (int)$cat['id'],
        'nome'       => $cat['nome'],
        'icone'      => $cat['icone'],
        'cor'        => $cat['cor'],
        'ativo'      => $ativo,
        'valor'      => $valorCalculado,
        'valor_base' => $valorBase,
    ];
}

// A tela permite salvar ou aprovar lançamentos ainda sem categoria. Eles não
// devem desaparecer do comprometimento; aparecem separados para que possam
// ser classificados depois, sem alterar o total do orçamento.
if ($gastoSemCategoria > 0) {
    $comprometido += $gastoSemCategoria;
    $categoriasCalculadas[] = [
        'id'         => 0,
        'nome'       => 'Sem categoria',
        'icone'      => 'question-circle',
        'cor'        => 'orange',
        'ativo'      => true,
        'valor'      => $gastoSemCategoria,
        'valor_base' => $gastoSemCategoria,
    ];
}

$livre = max($renda - $comprometido, 0);
$percentual = $renda > 0 ? ($comprometido / $renda) * 100 : 0;

function statusOrcamento(float $percentual, int $faixaIdeal, int $faixaAlerta): array
{
    if ($percentual <= $faixaIdeal) {
        return [
            'label' => 'Saudável',
            'texto' => 'Seu orçamento ainda mantém boa margem de segurança.',
            'leitura' => 'Orçamento saudável',
            'sugestao' => 'Preservar a folga atual e controlar lazer/extras',
            'cor' => '#24bb45',
        ];
    }
    if ($percentual <= $faixaAlerta) {
        return [
            'label' => 'Atenção',
            'texto' => 'Seu orçamento entrou em uma faixa que exige mais controle.',
            'leitura' => 'Orçamento em atenção',
            'sugestao' => 'Evitar novos gastos variáveis e revisar categorias flexíveis',
            'cor' => '#f59e0b',
        ];
    }
    return [
        'label' => 'Pressionado',
        'texto' => 'Seu orçamento está em uma faixa arriscada para o restante do mês.',
        'leitura' => 'Orçamento pressionado',
        'sugestao' => 'Reduzir despesas flexíveis e revisar custos fixos imediatamente',
        'cor' => '#ef4444',
    ];
}

$status = statusOrcamento($percentual, (int)$faixaIdeal, (int)$faixaAlerta);

$categoriaMaisPesada = 'Nenhuma categoria';
$maiorValor = 0;
foreach ($categoriasCalculadas as $c) {
    if ($c['valor'] > $maiorValor) {
        $maiorValor = $c['valor'];
        $categoriaMaisPesada = $c['nome'];
    }
}

$labelsCenario = ['base' => 'Atual', 'cauteloso' => 'Cauteloso', 'pressionado' => 'Pressionado'];

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
  <link rel="stylesheet" href="../assets/css/orcamento-mensal.css">

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
        <?= htmlspecialchars($_SESSION['avatar_iniciais'] ?? 'JD') ?>
      </button>
    </div>
  </header>

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
              <strong id="incomeValue"><?= brl($renda) ?></strong>
              <small>base usada no orçamento atual</small>
            </article>

            <article class="budget-kpi">
              <span>Comprometido</span>
              <strong id="committedValue"><?= brl($comprometido) ?></strong>
            </article>

            <article class="budget-kpi">
              <span>Livre no mês</span>
              <strong id="remainingValue"><?= brl($livre) ?></strong>
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

          <?php if ($renda <= 0): ?>
            <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">
              Nenhuma renda cadastrada ainda. <a href="config-renda.php">Configure sua renda primeiro</a>.
            </p>
          <?php endif; ?>
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
            <div class="budget-ring" id="budgetRing" style="--percent: <?= number_format($percentual, 1, '.', '') ?>; --ring-color: <?= htmlspecialchars($status['cor']) ?>;">
              <div class="budget-ring__inner">
                <span class="budget-ring__label">Comprometido</span>
                <strong id="budgetPercent"><?= round($percentual) ?>%</strong>
              </div>
            </div>

            <div class="budget-ring-metrics">
              <div class="budget-ring-metric">
                <span>Faixa ideal</span>
                <strong id="idealRangeText">até <?= (int)$faixaIdeal ?>%</strong>
              </div>

              <div class="budget-ring-metric">
                <span>Status atual</span>
                <strong id="budgetStatusText"><?= htmlspecialchars($status['label']) ?></strong>
              </div>

              <div class="budget-ring-metric">
                <span>Folga estimada</span>
                <strong id="bufferValue"><?= brl($livre) ?></strong>
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
            <strong id="summaryAvailable"><?= brl($livre) ?></strong>
            <p>Valor ainda livre antes de novas despesas no período.</p>
          </div>
        </article>

        <article class="budget-summary-card">
          <div class="budget-summary-card__icon budget-summary-card__icon--orange">
            <i class="bi bi-exclamation-circle"></i>
          </div>

          <div class="budget-summary-card__content">
            <span>Faixa atual</span>
            <strong id="summaryRange"><?= htmlspecialchars($status['label']) ?></strong>
            <p id="summaryRangeText"><?= htmlspecialchars($status['texto']) ?></p>
          </div>
        </article>

        <article class="budget-summary-card">
          <div class="budget-summary-card__icon budget-summary-card__icon--blue">
            <i class="bi bi-collection"></i>
          </div>

          <div class="budget-summary-card__content">
            <span>Categorias ativas</span>
            <strong id="summaryCategories"><?= $categoriasAtivasCount ?> categorias</strong>
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
            <?php if (empty($categoriasCalculadas)): ?>
              <p class="text-muted px-3 py-4 mb-0">Nenhuma categoria de despesa cadastrada ainda.</p>
            <?php endif; ?>

            <?php foreach ($categoriasCalculadas as $c):
              $catPercent = $renda > 0 ? ($c['valor'] / $renda) * 100 : 0;
            ?>
              <article class="budget-category-item" style="<?= $c['ativo'] ? '' : 'opacity:0.45;' ?>">
                <div class="budget-category-item__top">
                  <div class="budget-category-item__left">
                    <div class="budget-category-item__icon budget-category-item__icon--<?= htmlspecialchars($c['cor']) ?>">
                      <i class="bi bi-<?= htmlspecialchars($c['icone']) ?>"></i>
                    </div>
                    <div>
                      <h4><?= htmlspecialchars($c['nome']) ?></h4>
                      <p><?= $c['ativo'] ? 'Considerada no cálculo deste mês' : 'Desativada do cálculo atual' ?></p>
                    </div>
                  </div>

                  <div class="budget-category-item__right">
                    <strong><?= brl($c['valor']) ?></strong>
                    <span><?= number_format($catPercent, 1, ',', '.') ?>%</span>
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
              <strong><?= htmlspecialchars($status['leitura']) ?></strong>
            </div>

            <div class="budget-reading-card__item">
              <span>Categoria mais pesada</span>
              <strong><?= htmlspecialchars($categoriaMaisPesada) ?></strong>
            </div>

            <div class="budget-reading-card__item">
              <span>Recomendação principal</span>
              <strong><?= htmlspecialchars($status['sugestao']) ?></strong>
            </div>

            <div class="budget-reading-card__item">
              <span>Cenário selecionado</span>
              <strong><?= htmlspecialchars($labelsCenario[$cenarioAtual] ?? 'Atual') ?></strong>
            </div>

            <div class="budget-reading-card__item">
              <span>Faixa ideal configurada</span>
              <strong>Até <?= (int)$faixaIdeal ?>%</strong>
            </div>

            <div class="budget-reading-card__item">
              <span>Faixa de alerta</span>
              <strong>Acima de <?= (int)$faixaAlerta ?>%</strong>
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

      <form method="post" action="orcamento-mensal.php">
        <input type="hidden" name="acao" value="atualizar_limites">

        <div class="budget-modal__body">
          <div class="planner-grid">
            <label class="budget-field">
              <span>Faixa ideal (%)</span>
              <input name="faixa_ideal" id="idealLimitInput" type="number" min="1" max="100" value="<?= (int)$faixaIdeal ?>">
            </label>

            <label class="budget-field">
              <span>Faixa de alerta (%)</span>
              <input name="faixa_alerta" id="warningLimitInput" type="number" min="1" max="100" value="<?= (int)$faixaAlerta ?>">
            </label>

            <button class="budget-btn budget-btn--primary budget-btn--full" type="submit">
              Aplicar limites
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
            <strong><?= brl($renda) ?></strong>
            <p>Valor base usado como referência do orçamento atual.</p>
          </article>

          <article class="details-box">
            <span>Total comprometido</span>
            <strong><?= brl($comprometido) ?></strong>
            <p>Soma das despesas consideradas no orçamento deste mês.</p>
          </article>

          <article class="details-box">
            <span>Comprometimento</span>
            <strong><?= round($percentual) ?>%</strong>
            <p>Percentual da sua renda já absorvido por despesas no período.</p>
          </article>

          <article class="details-box">
            <span>Valor livre</span>
            <strong><?= brl($livre) ?></strong>
            <p>Margem ainda disponível antes de novos gastos.</p>
          </article>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL CATEGORIAS: um form com um checkbox por categoria -->
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

      <form method="post" action="orcamento-mensal.php">
        <input type="hidden" name="acao" value="atualizar_categorias">

        <div class="budget-modal__body">
          <div class="budget-config-list">
            <?php foreach ($categoriasCalculadas as $c): ?>
              <?php if ((int)$c['id'] <= 0) continue; ?>
              <div class="budget-config-row">
                <div>
                  <h4><?= htmlspecialchars($c['nome']) ?></h4>
                  <p>Gasto real neste mês: <?= brl($c['valor_base']) ?></p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="categoria_ativa[]" value="<?= $c['id'] ?>" <?= $c['ativo'] ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="budget-modal__footer">
          <button class="budget-btn budget-btn--secondary" type="button" data-close-budget-modal="categoriesModal">
            Cancelar
          </button>
          <button class="budget-btn budget-btn--primary" type="submit">
            Salvar categorias
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL CENÁRIOS: cada opção é o próprio botão de submit de um mini-form -->
  <div class="budget-modal-overlay" id="scenarioModal">
    <div class="budget-modal budget-modal--medium">
      <div class="budget-modal__header">
        <div class="budget-modal__title-group">
          <div class="budget-modal__icon">
            <i class="bi bi-bar-chart-line"></i>
          </div>
          <div>
            <h3>Explorar cenários</h3>
            <p>Escolha um cenário para recalcular o orçamento com base nele.</p>
          </div>
        </div>

        <button class="budget-modal__close" data-close-budget-modal="scenarioModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="budget-modal__body">
        <div class="budget-simulation-list budget-simulation-list--modal">
          <?php foreach (['base' => 'Cenário atual', 'cauteloso' => 'Cenário cauteloso', 'pressionado' => 'Cenário pressionado'] as $chaveCenario => $tituloCenario): ?>
            <form method="post" action="orcamento-mensal.php">
              <input type="hidden" name="acao" value="selecionar_cenario">
              <input type="hidden" name="cenario" value="<?= $chaveCenario ?>">
              <button
                type="submit"
                class="budget-sim-option <?= $cenarioAtual === $chaveCenario ? 'budget-sim-option--active' : '' ?>"
                style="all: unset; display: flex; width: 100%; cursor: pointer; box-sizing: border-box;"
              >
                <div>
                  <h4><?= $tituloCenario ?></h4>
                  <p>
                    <?php if ($chaveCenario === 'base'): ?>
                      Baseado nos valores atuais cadastrados.
                    <?php elseif ($chaveCenario === 'cauteloso'): ?>
                      Redução de despesas variáveis e preservação de margem.
                    <?php else: ?>
                      Aumento em custos fixos e menor folga financeira.
                    <?php endif; ?>
                  </p>
                </div>
              </button>
            </form>
          <?php endforeach; ?>
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
            <p>Seu comprometimento atual ainda está dentro da faixa saudável.</p>
          </div>

          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button">Analisar meu orçamento</button>
            <button class="ai-shortcut" type="button">Onde cortar gastos?</button>
          </div>
        </aside>

        <section class="ai-chat-area">
          <div class="ai-chat-area__messages"></div>

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
      openPlannerModal: "plannerModal",
      openDetailsModal: "detailsModal",
      openCategoriesModal: "categoriesModal",
      openScenarioModal: "scenarioModal"
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

      let formatado = (Number(digits) / 100).toFixed(2);
      formatado = formatado.replace(".", ",");
      formatado = formatado.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
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
  </script>

</body>
</html>
