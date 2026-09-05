 <?php
session_start();
require_once __DIR__ . '/../config/conn.php'; // ajuste se necessário

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {

    $acao = $_POST['acao'];

    if ($acao === 'marcar_lido') {
        $id = (int)($_POST['alerta_id'] ?? 0);

        if ($id > 0) {
            $sql = "UPDATE alertas_preditivos SET lido = 1 WHERE id = ? AND usuario_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $usuario_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($acao === 'aplicar_simulacao') {
        $id = (int)($_POST['alerta_id'] ?? 0);
        $reducao = (float)($_POST['reducao'] ?? 0);

        if ($id > 0 && $reducao >= 0) {
            $sqlBusca = "SELECT impacto_estimado FROM alertas_preditivos WHERE id = ? AND usuario_id = ?";
            $stmtBusca = $conn->prepare($sqlBusca);
            $stmtBusca->bind_param("ii", $id, $usuario_id);
            $stmtBusca->execute();
            $alerta = $stmtBusca->get_result()->fetch_assoc();
            $stmtBusca->close();

            if ($alerta) {
                $novoImpacto = max((float)$alerta['impacto_estimado'] - $reducao, 0);

                if ($novoImpacto <= 100) {
                    $novaSeveridade = 'baixo';
                } elseif ($novoImpacto <= 250) {
                    $novaSeveridade = 'medio';
                } else {
                    $novaSeveridade = 'alto';
                }

                $sqlUpdate = "UPDATE alertas_preditivos
                              SET impacto_estimado = ?, severidade = ?
                              WHERE id = ? AND usuario_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("dsii", $novoImpacto, $novaSeveridade, $id, $usuario_id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }
    }

    if ($acao === 'salvar_config') {
        $sensibilidade = $_POST['sensibilidade'] ?? 'equilibrado';
        $alertasOrcamentoAtivo = isset($_POST['alertas_orcamento_ativo']) ? 1 : 0;
        $alertasCategoriaAtivo = isset($_POST['alertas_categoria_ativo']) ? 1 : 0;

        $valoresValidos = ['baixo', 'equilibrado', 'alto'];
        if (in_array($sensibilidade, $valoresValidos, true)) {
            $sql = "INSERT INTO alertas_configuracao (usuario_id, alertas_orcamento_ativo, alertas_categoria_ativo, sensibilidade)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      alertas_orcamento_ativo = VALUES(alertas_orcamento_ativo),
                      alertas_categoria_ativo = VALUES(alertas_categoria_ativo),
                      sensibilidade = VALUES(sensibilidade)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $usuario_id, $alertasOrcamentoAtivo, $alertasCategoriaAtivo, $sensibilidade);
            $stmt->execute();
            $stmt->close();
        }
    }

    header('Location: alertas-preditivos.php');
    exit;
}

function severidadeParaClasse(string $severidade): string
{
    return match ($severidade) {
        'alto'  => 'high',
        'medio' => 'medium',
        'baixo' => 'low',
        default => 'low',
    };
}

function severidadeParaLabel(string $severidade): string
{
    return match ($severidade) {
        'alto'  => 'Alto risco',
        'medio' => 'Médio risco',
        'baixo' => 'Baixo risco',
        default => 'Baixo risco',
    };
}




// -----------------------------------------------------------------
// Busca a configuração de sensibilidade do usuário
// -----------------------------------------------------------------
$sqlConfig = "SELECT alertas_orcamento_ativo, alertas_categoria_ativo, sensibilidade
              FROM alertas_configuracao WHERE usuario_id = ?";
$stmtConfig = $conn->prepare($sqlConfig);
$stmtConfig->bind_param("i", $usuario_id);
$stmtConfig->execute();
$config = $stmtConfig->get_result()->fetch_assoc();
$stmtConfig->close();

$sensibilidade = $config['sensibilidade'] ?? 'equilibrado';
$alertasOrcamentoAtivo = $config['alertas_orcamento_ativo'] ?? 1;
$alertasCategoriaAtivo = $config['alertas_categoria_ativo'] ?? 1;

// -----------------------------------------------------------------
// ANÁLISE REAL DOS GASTOS DO MÊS
// Usa somente transações aprovadas do usuário logado.
// -----------------------------------------------------------------
$stmtFinanceiro = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS receitas,
        COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS despesas
    FROM transacoes
    WHERE usuario_id = ?
      AND status = 'aprovado'
      AND (CASE WHEN origem = 'importacao' THEN DATE(atualizado_em) ELSE data_transacao END) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND (CASE WHEN origem = 'importacao' THEN DATE(atualizado_em) ELSE data_transacao END) < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
");
$stmtFinanceiro->bind_param("i", $usuario_id);
$stmtFinanceiro->execute();
$dadosFinanceiros = $stmtFinanceiro->get_result()->fetch_assoc();
$stmtFinanceiro->close();

$receitasMes = (float)($dadosFinanceiros['receitas'] ?? 0);
$despesasMes = (float)($dadosFinanceiros['despesas'] ?? 0);
$saldoMes = $receitasMes - $despesasMes;

$percentualGasto = $receitasMes > 0
    ? ($despesasMes / $receitasMes) * 100
    : 0;

$riscoFinanceiro = 'controlado';
$tituloRisco = 'Controlado';
$textoRisco = 'Seu cenário atual está estável e sem grandes sinais de desequilíbrio.';
$pressaoFinanceira = 'Sem pressão relevante';
$acaoFinanceira = 'Manter rotina atual';

if ($receitasMes <= 0 && $despesasMes > 0) {
    $riscoFinanceiro = 'alto';
    $tituloRisco = 'Alto risco';
    $textoRisco = 'Há gastos registrados neste mês, mas nenhuma receita aprovada foi identificada. Evite novos gastos até organizar o orçamento.';
    $pressaoFinanceira = 'Gastos sem receita registrada';
    $acaoFinanceira = 'Evitar novos gastos';
} elseif ($percentualGasto >= 85) {
    $riscoFinanceiro = 'alto';
    $tituloRisco = 'Alto risco';
    $textoRisco = 'Você já comprometeu ' . number_format($percentualGasto, 1, ',', '.') . '% da sua receita deste mês. Evite novos gastos desnecessários.';
    $pressaoFinanceira = 'Gastos do mês';
    $acaoFinanceira = 'Evitar novos gastos';
} elseif ($percentualGasto >= 70) {
    $riscoFinanceiro = 'medio';
    $tituloRisco = 'Médio risco';
    $textoRisco = 'Você já gastou ' . number_format($percentualGasto, 1, ',', '.') . '% da sua receita deste mês. Reduza gastos para evitar pressão no final do mês.';
    $pressaoFinanceira = 'Gastos do mês';
    $acaoFinanceira = 'Reduzir gastos variáveis';
} elseif ($percentualGasto >= 50) {
    $riscoFinanceiro = 'baixo';
    $tituloRisco = 'Atenção';
    $textoRisco = 'Você já utilizou ' . number_format($percentualGasto, 1, ',', '.') . '% da sua receita deste mês. Acompanhe os próximos gastos.';
    $pressaoFinanceira = 'Ritmo de gastos';
    $acaoFinanceira = 'Controlar próximos gastos';
}

if ($riscoFinanceiro === 'alto') {
    $horizonteCritico = 'Agora';
} elseif ($riscoFinanceiro === 'medio') {
    $horizonteCritico = 'Próximos dias';
} elseif ($riscoFinanceiro === 'baixo') {
    $horizonteCritico = 'Acompanhar este mês';
} else {
    $horizonteCritico = 'Sem urgência crítica';
}

if ($sensibilidade === 'baixo') {
    $sensibilidadeLabel = 'Baixo';
    $sensibilidadeWidth = 36;
    $sensibilidadeTexto = 'Sensibilidade menor, focada apenas em desvios mais evidentes.';
} elseif ($sensibilidade === 'alto') {
    $sensibilidadeLabel = 'Alto';
    $sensibilidadeWidth = 100;
    $sensibilidadeTexto = 'Sensibilidade alta para detectar movimentos pequenos antes que cresçam.';
} else {
    $sensibilidadeLabel = 'Equilibrado';
    $sensibilidadeWidth = 66;
    $sensibilidadeTexto = 'Sensibilidade intermediária para detectar desvios antes de se tornarem críticos.';
}

// -----------------------------------------------------------------
// Cria/atualiza automaticamente um alerta real no banco quando o
// comportamento financeiro indicar que é melhor evitar novos gastos.
// O alerta é específico do usuário e do mês atual.
// -----------------------------------------------------------------
$tituloAlertaGastos = 'Alerta de gastos do mês';

if ($alertasOrcamentoAtivo && ($riscoFinanceiro === 'medio' || $riscoFinanceiro === 'alto')) {
    if ($receitasMes > 0) {
        $restante = max($receitasMes - $despesasMes, 0);

        if ($riscoFinanceiro === 'alto') {
            $descricaoAlerta = 'Você já gastou R$ ' . number_format($despesasMes, 2, ',', '.') .
                ' de R$ ' . number_format($receitasMes, 2, ',', '.') .
                ' recebidos neste mês (' . number_format($percentualGasto, 1, ',', '.') .
                '%). Restam R$ ' . number_format($restante, 2, ',', '.') .
                '. Evite novos gastos desnecessários.';
        } else {
            $descricaoAlerta = 'Você já gastou R$ ' . number_format($despesasMes, 2, ',', '.') .
                ' de R$ ' . number_format($receitasMes, 2, ',', '.') .
                ' recebidos neste mês (' . number_format($percentualGasto, 1, ',', '.') .
                '%). Restam R$ ' . number_format($restante, 2, ',', '.') .
                '. Reduza os gastos para evitar pressão no fim do mês.';
        }
    } else {
        $descricaoAlerta = 'Há R$ ' . number_format($despesasMes, 2, ',', '.') .
            ' em gastos aprovados neste mês e nenhuma receita aprovada registrada. Evite novos gastos até organizar o orçamento.';
    }

    $impactoAlerta = max($despesasMes - ($receitasMes * 0.70), 0);
    $severidadeAlerta = $riscoFinanceiro === 'alto' ? 'alto' : 'medio';
    $horizonteAlerta = 'Neste mês';

    $stmtBuscaAlerta = $conn->prepare("
        SELECT id
        FROM alertas_preditivos
        WHERE usuario_id = ?
          AND titulo = ?
          AND MONTH(criado_em) = MONTH(CURDATE())
          AND YEAR(criado_em) = YEAR(CURDATE())
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtBuscaAlerta->bind_param("is", $usuario_id, $tituloAlertaGastos);
    $stmtBuscaAlerta->execute();
    $alertaExistente = $stmtBuscaAlerta->get_result()->fetch_assoc();
    $stmtBuscaAlerta->close();

    if ($alertaExistente) {
        $stmtAtualizaAlerta = $conn->prepare("
            UPDATE alertas_preditivos
            SET descricao = ?, severidade = ?, impacto_estimado = ?, horizonte = ?
            WHERE id = ? AND usuario_id = ?
        ");
        $idAlerta = (int)$alertaExistente['id'];
        $stmtAtualizaAlerta->bind_param(
            "ssdsii",
            $descricaoAlerta,
            $severidadeAlerta,
            $impactoAlerta,
            $horizonteAlerta,
            $idAlerta,
            $usuario_id
        );
        $stmtAtualizaAlerta->execute();
        $stmtAtualizaAlerta->close();
    } else {
        $tituloAlerta = $tituloAlertaGastos;
        $stmtInsereAlerta = $conn->prepare("
            INSERT INTO alertas_preditivos
                (usuario_id, titulo, descricao, severidade, impacto_estimado, horizonte, lido)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $stmtInsereAlerta->bind_param(
            "isssds",
            $usuario_id,
            $tituloAlerta,
            $descricaoAlerta,
            $severidadeAlerta,
            $impactoAlerta,
            $horizonteAlerta
        );
        $stmtInsereAlerta->execute();
        $stmtInsereAlerta->close();
    }
} else {
    // Se o cenário voltou ao normal, remove somente o alerta automático
    // deste mês, sem mexer nos demais alertas cadastrados pelo sistema.
    $stmtRemoveAlerta = $conn->prepare("
        DELETE FROM alertas_preditivos
        WHERE usuario_id = ?
          AND titulo = ?
          AND MONTH(criado_em) = MONTH(CURDATE())
          AND YEAR(criado_em) = YEAR(CURDATE())
    ");
    $stmtRemoveAlerta->bind_param("is", $usuario_id, $tituloAlertaGastos);
    $stmtRemoveAlerta->execute();
    $stmtRemoveAlerta->close();
}

// -----------------------------------------------------------------
// Busca novamente os alertas do usuário após criar/atualizar o alerta automático.

// ---------------

// -----------------------------------------------------------------


$sql = "SELECT id, titulo, descricao, severidade, impacto_estimado, horizonte, lido, criado_em
        FROM alertas_preditivos
        WHERE usuario_id = ?
        ORDER BY FIELD(severidade, 'alto', 'medio', 'baixo'), criado_em DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

$alertas = [];
while ($linha = $resultado->fetch_assoc()) {
    $alertas[] = $linha;
}
$stmt->close();



 
// -----------------------------------------------------------------
// Array pro JS usar só pra exibição (detalhes, cálculo de resumo,
// preview da simulação). Nenhuma ação grava usando esse array —
// quem grava é sempre o PHP no topo da página, via POST normal.
// -----------------------------------------------------------------


$sqlConfig = "SELECT alertas_orcamento_ativo, alertas_categoria_ativo, sensibilidade
              FROM alertas_configuracao WHERE usuario_id = ?";
$stmtConfig = $conn->prepare($sqlConfig);
$stmtConfig->bind_param("i", $usuario_id);
$stmtConfig->execute();
$config = $stmtConfig->get_result()->fetch_assoc();
$stmtConfig->close();

$sensibilidade = $config['sensibilidade'] ?? 'equilibrado';
$alertasOrcamentoAtivo = $config['alertas_orcamento_ativo'] ?? 1;
$alertasCategoriaAtivo = $config['alertas_categoria_ativo'] ?? 1;


$alertasParaJs = array_map(function ($a) {
    return [
        'id'            => (int)$a['id'],
        'title'         => $a['titulo'],
        'severity'      => severidadeParaClasse($a['severidade']),
        'severityLabel' => severidadeParaLabel($a['severidade']),
        'description'   => $a['descricao'],
        'horizon'       => $a['horizonte'],
        'impact'        => (float)$a['impacto_estimado'],
        'read'          => (bool)$a['lido'],
    ];
}, $alertas);

// Dados do resumo lateral. Mantemos estes valores reunidos para que a tela
// renderize corretamente tanto no PHP quanto após o JavaScript ser carregado.
$resumoRapido = [
    'status' => $tituloRisco,
    'pressao' => $pressaoFinanceira,
    'horizonte' => $horizonteCritico,
    'acao' => $acaoFinanceira,
];

$dadosSensibilidade = [
    'nivel' => $sensibilidadeLabel,
    'largura' => $sensibilidadeWidth,
    'texto' => $sensibilidadeTexto,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Alertas Preditivos - FinMap</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/alertas-preditivos.css">
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
          <p>Alertas preditivos inteligentes</p>
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

  <main class="alerts-page">
    <div class="back-navigation">
      <a href="dashboard.php" class="back-btn">
        <i class="bi bi-chevron-left"></i>
        <span>Voltar</span>
      </a>
    </div>

    <section class="alerts-dashboard">
      <section class="alerts-header">
        <div class="alerts-header__content">
          <h2>Alertas preditivos</h2>
          <p>Receba avisos antes de um possível desequilíbrio financeiro.</p>
        </div>

        <div class="alerts-header__actions">
          <button class="alert-btn alert-btn--primary" id="openSettingsModal" type="button">
            <i class="bi bi-sliders"></i>
            Ajustar alertas
          </button>
        </div>
      </section>

      <section class="alerts-summary-grid">
        <article class="summary-card summary-card--risk">
          <div class="summary-card__icon summary-card__icon--red">
            <i class="bi bi-exclamation-triangle"></i>
          </div>
          <div class="summary-card__content">
            <span>Risco atual</span>
            <strong id="riskStatus">
    <?= htmlspecialchars($tituloRisco) ?>
</strong>

<p id="riskText">
    <?= htmlspecialchars($textoRisco) ?>
</p>
<small>
    Receita: R$ <?= number_format($receitasMes, 2, ',', '.') ?>
    &nbsp; | &nbsp;
    Gastos: R$ <?= number_format($despesasMes, 2, ',', '.') ?>
</small>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--orange">
            <i class="bi bi-bell"></i>
          </div>
          <div class="summary-card__content">
            <span>Alertas ativos</span>
            <strong id="activeAlertsCount"><?= count($alertas) ?></strong>
            <p>Quantidade de alertas relevantes no cenário atual.</p>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-card__icon summary-card__icon--blue">
            <i class="bi bi-eye"></i>
          </div>
          <div class="summary-card__content">
            <span>Não vistos</span>
            <strong id="unreadAlertsCount">
    <?= count(array_filter($alertas, function ($alerta) {
        return (int)$alerta['lido'] === 0;
    })) ?>
</strong>
            <p>Alertas que ainda precisam da sua atenção.</p>
          </div>
        </article>
      </section>

      <section class="alerts-main-grid">
        <section class="alerts-panel alerts-panel--wide">
          <div class="alerts-panel__header">
            <div>
              <h3>Seus alertas</h3>
              <p>Identificações feitas pelo FinMap antes de um possível problema financeiro.</p>
            </div>

            <div class="alerts-panel__filters">
              <button class="filter-chip active" type="button" data-filter="all">Todos</button>
              <button class="filter-chip" type="button" data-filter="high">Alto risco</button>
              <button class="filter-chip" type="button" data-filter="medium">Médio risco</button>
              <button class="filter-chip" type="button" data-filter="low">Baixo risco</button>
            </div>
          </div>

          <div class="alerts-list" id="alertsList">
            <?php if (empty($alertas)): ?>
              <p class="text-muted px-3 py-4 mb-0">Nenhum alerta no momento. Assim que o FinMap identificar algum risco, ele vai aparecer aqui.</p>
            <?php endif; ?>

            <?php foreach ($alertas as $a):
              $classe = severidadeParaClasse($a['severidade']);
              $label = severidadeParaLabel($a['severidade']);
              $iconeSeveridade = match ($classe) {
                  'high'   => 'bi-exclamation-octagon',
                  'medium' => 'bi-graph-down-arrow',
                  'low'    => 'bi-info-circle',
              };
            ?>
              <article class="alert-item alert-item--<?= $classe ?>" data-id="<?= (int)$a['id'] ?>" data-severity="<?= $classe ?>">
                <div class="alert-item__left">
                  <div class="alert-item__icon alert-item__icon--<?= $classe ?>">
                    <i class="bi <?= $iconeSeveridade ?>"></i>
                  </div>

                  <div class="alert-item__content">
                    <div class="alert-item__top">
                      <h4><?= htmlspecialchars($a['titulo']) ?></h4>
                      <span class="alert-badge alert-badge--<?= $classe ?>"><?= $label ?></span>
                    </div>

                    <p><?= htmlspecialchars($a['descricao']) ?></p>

                    <div class="alert-item__meta">
                      <span><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($a['horizonte'] ?? '-') ?></span>
                      <span><i class="bi bi-wallet2"></i> Impacto estimado: R$ <?= number_format((float)$a['impacto_estimado'], 2, ',', '.') ?></span>
                    </div>

                    <div class="alert-item__actions">
                      <button class="alert-action-btn alert-action-btn--ghost" type="button" data-open-details="<?= (int)$a['id'] ?>">
                        <i class="bi bi-eye"></i>
                        Ver detalhes
                      </button>

                      <button class="alert-action-btn alert-action-btn--blue" type="button" data-open-simulate="<?= (int)$a['id'] ?>">
                        <i class="bi bi-bar-chart-line"></i>
                        Simular impacto
                      </button>

                      <!-- Formulário simples: sem JS, sem fetch. Envia direto pro topo desta página. -->
                      <form method="post" action="alertas-preditivos.php" style="display:inline;">
                        <input type="hidden" name="acao" value="marcar_lido">
                        <input type="hidden" name="alerta_id" value="<?= (int)$a['id'] ?>">
                        <button class="alert-action-btn alert-action-btn--green" type="submit">
                          <i class="bi bi-check2-circle"></i>
                          Marcar como visto
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <aside class="alerts-side-column">
          <section class="alerts-panel">
            <div class="alerts-panel__header">
              <div>
                <h3>Leitura rápida</h3>
                <p>Resumo prático do cenário detectado</p>
              </div>
            </div>

           <?php
if ($riscoFinanceiro === 'alto') {
    $horizonteCritico = 'Agora';
} elseif ($riscoFinanceiro === 'medio') {
    $horizonteCritico = 'Próximos dias';
} elseif ($riscoFinanceiro === 'baixo') {
    $horizonteCritico = 'Acompanhar este mês';
} else {
    $horizonteCritico = 'Sem urgência crítica';
}

// =========================================================
// BUSCAR NOTIFICAÇÕES DO USUÁRIO
// =========================================================

$sqlNotificacoes = "
    SELECT id, categoria, titulo, mensagem, lida, criado_em
    FROM notificacoes
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
    LIMIT 20
";

$stmtNotificacoes = $conn->prepare($sqlNotificacoes);
$stmtNotificacoes->bind_param("i", $usuario_id);
$stmtNotificacoes->execute();

$resultNotificacoes = $stmtNotificacoes->get_result();

$notificacoes = [];

while ($notificacao = $resultNotificacoes->fetch_assoc()) {
    $notificacoes[] = $notificacao;
}

$stmtNotificacoes->close();
?>

<div class="quick-reading-list">

    <article class="quick-reading-card">
        <span>Status geral</span>
        <strong id="quickRiskStatus">
            <?= htmlspecialchars($tituloRisco) ?>
        </strong>
    </article>

    <article class="quick-reading-card">
        <span>Maior pressão</span>
        <strong id="quickMainPressure">
            <?= htmlspecialchars($pressaoFinanceira) ?>
        </strong>
    </article>

    <article class="quick-reading-card">
        <span>Horizonte crítico</span>
        <strong id="quickCriticalWindow">
            <?= htmlspecialchars($horizonteCritico) ?>
        </strong>
    </article>

    <article class="quick-reading-card">
        <span>Melhor ação</span>
        <strong id="quickBestAction">
            <?= htmlspecialchars($acaoFinanceira) ?>
        </strong>
    </article>

</div>
          </section>

          <section class="alerts-panel">
            <div class="alerts-panel__header">
              <div>
                <h3>Sensibilidade atual</h3>
                <p>Como o FinMap está reagindo ao seu comportamento</p>
              </div>
            </div>

            <?php
if ($sensibilidade === 'baixo') {

    $sensibilidadeLabel = 'Baixo';
    $sensibilidadeWidth = 36;
    $sensibilidadeTexto =
        'Sensibilidade menor, focada apenas em desvios mais evidentes.';

} elseif ($sensibilidade === 'alto') {

    $sensibilidadeLabel = 'Alto';
    $sensibilidadeWidth = 100;
    $sensibilidadeTexto =
        'Sensibilidade alta para detectar movimentos pequenos antes que cresçam.';

} else {

    $sensibilidadeLabel = 'Equilibrado';
    $sensibilidadeWidth = 66;
    $sensibilidadeTexto =
        'Sensibilidade intermediária para detectar desvios antes de se tornarem críticos.';
}
?>

<div class="sensitivity-card">

    <div class="sensitivity-card__top">
        <span>Nível configurado</span>

        <strong id="sensitivityLabel">
            <?= $sensibilidadeLabel ?>
        </strong>
    </div>

    <div class="sensitivity-card__bar">
        <div
            class="sensitivity-card__fill"
            id="sensitivityFill"
            style="width: <?= $sensibilidadeWidth ?>%;">
        </div>
    </div>

    <p id="sensitivityText">
        <?= $sensibilidadeTexto ?>
    </p>

</div>
          </section>
        </aside>
      </section>
    </section>
  </main>

  <!-- MODAL DETALHES (só leitura, não precisa de form) -->
  <div class="alert-modal-overlay" id="detailsModal">
    <div class="alert-modal">
      <div class="alert-modal__header">
        <div class="alert-modal__title-group">
          <div class="alert-modal__icon">
            <i class="bi bi-exclamation-triangle"></i>
          </div>
          <div>
            <h3>Detalhes do alerta</h3>
            <p>Entenda por que esse aviso foi gerado.</p>
          </div>
        </div>

        <button class="alert-modal__close" id="closeDetailsModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="alert-modal__body">
        <div class="details-grid">
          <article class="details-box">
            <span>Título</span>
            <strong id="detailTitle">-</strong>
          </article>

          <article class="details-box">
            <span>Severidade</span>
            <strong id="detailSeverity">-</strong>
          </article>

          <article class="details-box">
            <span>Impacto estimado</span>
            <strong id="detailImpact">-</strong>
          </article>

          <article class="details-box">
            <span>Horizonte</span>
            <strong id="detailHorizon">-</strong>
          </article>

          <article class="details-box details-box--full">
            <span>Descrição</span>
            <strong id="detailDescription">-</strong>
          </article>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL SIMULAÇÃO: agora é um <form method="post"> de verdade -->
  <div class="alert-modal-overlay" id="simulateModal">
    <div class="alert-modal alert-modal--medium">
      <div class="alert-modal__header">
        <div class="alert-modal__title-group">
          <div class="alert-modal__icon">
            <i class="bi bi-bar-chart-line"></i>
          </div>
          <div>
            <h3>Simular impacto</h3>
            <p>Veja como uma redução muda o cenário previsto.</p>
          </div>
        </div>

        <button class="alert-modal__close" id="closeSimulateModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form method="post" action="alertas-preditivos.php">
        <input type="hidden" name="acao" value="aplicar_simulacao">
        <input type="hidden" name="alerta_id" id="simulateAlertIdInput" value="">

        <div class="alert-modal__body">
          <div class="simulate-grid">
            <label class="alert-field">
              <span>Redução simulada (R$)</span>
              <input type="number" name="reducao" id="simulateValueInput" min="0" step="0.01" placeholder="100">
            </label>

            <article class="simulate-result-card">
              <span>Novo impacto estimado</span>
              <strong id="simulatedImpactValue">R$ 0,00</strong>
              <p id="simulatedImpactText">Insira um valor para ver como esse alerta mudaria.</p>
            </article>
          </div>
        </div>

        <div class="alert-modal__footer">
          <button class="alert-footer-btn alert-footer-btn--secondary" id="cancelSimulateModal" type="button">
            Cancelar
          </button>
          <button class="alert-footer-btn alert-footer-btn--primary" type="submit">
            Aplicar simulação
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL CONFIGURAÇÕES: também um <form method="post"> normal (por enquanto) -->
  <div class="alert-modal-overlay" id="settingsModalPred">
    <div class="alert-modal alert-modal--medium">
      <div class="alert-modal__header">
        <div class="alert-modal__title-group">
          <div class="alert-modal__icon">
            <i class="bi bi-sliders"></i>
          </div>
          <div>
            <h3>Ajustar alertas</h3>
            <p>Defina como o FinMap deve antecipar riscos financeiros.</p>
          </div>
        </div>

        <button class="alert-modal__close" id="closeSettingsModalPred" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form method="post" action="alertas-preditivos.php">
        <input type="hidden" name="acao" value="salvar_config">

        <div class="alert-modal__body">
          <div class="settings-list">
            <div class="settings-row">
              <div>
                <h4>Ativar alertas de orçamento</h4>
                <p>Avisos quando o mês começa a sair da faixa ideal.</p>
              </div>
              <label class="switch switch--green">
                <input type="checkbox" name="alertas_orcamento_ativo" <?= $alertasOrcamentoAtivo ? 'checked' : '' ?>>
                <span class="switch-slider"></span>
              </label>
            </div>

            <div class="settings-row">
              <div>
                <h4>Ativar alertas por categoria</h4>
                <p>Detecta desvios em grupos como alimentação e extras.</p>
              </div>
              <label class="switch switch--green">
                <input type="checkbox" name="alertas_categoria_ativo" <?= $alertasCategoriaAtivo ? 'checked' : '' ?>>
                <span class="switch-slider"></span>
              </label>
            </div>

            <label class="alert-field">
              <span>Sensibilidade</span>
              <select name="sensibilidade">
                <option value="baixo" <?= $sensibilidade === 'baixo' ? 'selected' : '' ?>>Baixo</option>
                <option value="equilibrado" <?= $sensibilidade === 'equilibrado' ? 'selected' : '' ?>>Equilibrado</option>
                <option value="alto" <?= $sensibilidade === 'alto' ? 'selected' : '' ?>>Alto</option>
              </select>
            </label>
          </div>
        </div>

        <div class="alert-modal__footer">
          <button class="alert-footer-btn alert-footer-btn--secondary" id="cancelSettingsModalPred" type="button">
            Cancelar
          </button>
          <button class="alert-footer-btn alert-footer-btn--primary" type="submit">
            Salvar ajustes
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
      <button class="notif-tab" type="button">Alertas</button>
      <button class="notif-tab" type="button">Risco</button>
      <button class="notif-tab" type="button">IA</button>
    </div>

   <div class="notif-panel__list">

    <?php if (empty($notificacoes)): ?>

        <div class="text-center p-4 text-muted">
            <i class="bi bi-bell-slash fs-3"></i>

            <p class="mt-2 mb-0">
                Nenhuma notificação no momento.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($notificacoes as $notificacao): ?>

            <?php

            $categoria = $notificacao['categoria'];

            if ($categoria === 'meta') {

                $classeNotif = 'success';
                $iconeNotif = 'bi-bullseye';

            } elseif ($categoria === 'gasto') {

                $classeNotif = 'warning';
                $iconeNotif = 'bi-wallet2';

            } elseif ($categoria === 'ia') {

                $classeNotif = 'info';
                $iconeNotif = 'bi-stars';

            } else {

                $classeNotif = 'danger';
                $iconeNotif = 'bi-exclamation-triangle';
            }

            $dataNotif = date(
                'd/m/Y H:i',
                strtotime($notificacao['criado_em'])
            );

            ?>

            <article class="notif-card notif-card--<?= $classeNotif ?>">

                <div class="notif-card__icon">
                    <i class="bi <?= $iconeNotif ?>"></i>
                </div>

                <div class="notif-card__content">

                    <h4>
                        <?= htmlspecialchars($notificacao['titulo']) ?>
                    </h4>

                    <p>
                        <?= htmlspecialchars($notificacao['mensagem']) ?>
                    </p>

                    <span>
                        <?= $dataNotif ?>
                    </span>

                </div>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

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
            <strong>Risco moderado detectado</strong>
            <p>Seu cenário ainda é controlável, mas já existem sinais claros de pressão no fechamento do mês.</p>
          </div>

          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button">Analisar meus alertas</button>
            <button class="ai-shortcut" type="button">Onde está o maior risco?</button>
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

    const alerts = <?= json_encode($alertasParaJs, JSON_UNESCAPED_UNICODE) ?>;

    const financialData = {
      income: <?= json_encode($receitasMes) ?>,
      expenses: <?= json_encode($despesasMes) ?>,
      balance: <?= json_encode($saldoMes) ?>,
      spentPercent: <?= json_encode(round($percentualGasto, 1)) ?>,
      risk: <?= json_encode($riscoFinanceiro) ?>,
      riskTitle: <?= json_encode($tituloRisco, JSON_UNESCAPED_UNICODE) ?>,
      riskText: <?= json_encode($textoRisco, JSON_UNESCAPED_UNICODE) ?>,
      pressure: <?= json_encode($pressaoFinanceira, JSON_UNESCAPED_UNICODE) ?>,
      action: <?= json_encode($acaoFinanceira, JSON_UNESCAPED_UNICODE) ?>
    };

    const quickReadingData = <?= json_encode($resumoRapido, JSON_UNESCAPED_UNICODE) ?>;
    const sensitivityData = <?= json_encode($dadosSensibilidade, JSON_UNESCAPED_UNICODE) ?>;

    function fillSidebarSummary() {
      const fields = {
        quickRiskStatus: quickReadingData.status,
        quickMainPressure: quickReadingData.pressao,
        quickCriticalWindow: quickReadingData.horizonte,
        quickBestAction: quickReadingData.acao,
        sensitivityLabel: sensitivityData.nivel,
        sensitivityText: sensitivityData.texto
      };

      Object.entries(fields).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '-';
      });

      const sensitivityFill = document.getElementById('sensitivityFill');
      if (sensitivityFill) {
        sensitivityFill.style.width = `${Number(sensitivityData.largura) || 0}%`;
      }
    }

    fillSidebarSummary();

    function formatBRL(value) {
      return value.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }

    function hasClassSafe(element, className) {
      return element && element.classList.contains(className);
    }

    function lockBody() { body.style.overflow = "hidden"; }

    function unlockBody() {
      const hasOpenModal =
        hasClassSafe(document.getElementById("detailsModal"), "active") ||
        hasClassSafe(document.getElementById("simulateModal"), "active") ||
        hasClassSafe(document.getElementById("settingsModalPred"), "active") ||
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

    document.querySelectorAll(".filter-chip").forEach((button) => {
      button.addEventListener("click", () => {
        const filtro = button.getAttribute("data-filter");

        document.querySelectorAll(".filter-chip").forEach((b) => {
          b.classList.toggle("active", b === button);
        });

        document.querySelectorAll(".alert-item").forEach((item) => {
          const severidade = item.getAttribute("data-severity");
          item.style.display = (filtro === "all" || severidade === filtro) ? "" : "none";
        });
      });
    });

    document.querySelectorAll("[data-open-details]").forEach((button) => {
      button.addEventListener("click", () => {
        const id = Number(button.getAttribute("data-open-details"));
        const alert = alerts.find(item => item.id === id);
        if (!alert) return;

        document.getElementById("detailTitle").textContent = alert.title;
        document.getElementById("detailSeverity").textContent = alert.severityLabel;
        document.getElementById("detailImpact").textContent = formatBRL(alert.impact);
        document.getElementById("detailHorizon").textContent = alert.horizon;
        document.getElementById("detailDescription").textContent = alert.description;

        openModal("detailsModal");
      });
    });

    document.querySelectorAll("[data-open-simulate]").forEach((button) => {
      button.addEventListener("click", () => {
        const id = Number(button.getAttribute("data-open-simulate"));
        const alert = alerts.find(item => item.id === id);
        if (!alert) return;

        document.getElementById("simulateAlertIdInput").value = id;
        document.getElementById("simulateValueInput").value = "";
        document.getElementById("simulatedImpactValue").textContent = formatBRL(alert.impact);
        document.getElementById("simulatedImpactText").textContent = "Insira um valor para ver como esse alerta mudaria.";

        openModal("simulateModal");
      });
    });

    document.getElementById("simulateValueInput").addEventListener("input", (e) => {
      const id = Number(document.getElementById("simulateAlertIdInput").value);
      const alert = alerts.find(item => item.id === id);
      if (!alert) return;

      const reduction = Number(e.target.value) || 0;
      const newImpact = Math.max(alert.impact - reduction, 0);

      document.getElementById("simulatedImpactValue").textContent = formatBRL(newImpact);

      if (reduction <= 0) {
        document.getElementById("simulatedImpactText").textContent = "Insira um valor para ver como esse alerta mudaria.";
      } else if (newImpact === 0) {
        document.getElementById("simulatedImpactText").textContent = "Essa redução eliminaria o impacto previsto deste alerta.";
      } else {
        document.getElementById("simulatedImpactText").textContent = "Com essa redução, o alerta perderia parte da pressão prevista.";
      }
    });

    document.getElementById("openSettingsModal").addEventListener("click", () => {
      openModal("settingsModalPred");
    });

    document.getElementById("closeDetailsModal").addEventListener("click", () => closeModal("detailsModal"));
    document.getElementById("closeSimulateModal").addEventListener("click", () => closeModal("simulateModal"));
    document.getElementById("cancelSimulateModal").addEventListener("click", () => closeModal("simulateModal"));
    document.getElementById("closeSettingsModalPred").addEventListener("click", () => closeModal("settingsModalPred"));
    document.getElementById("cancelSettingsModalPred").addEventListener("click", () => closeModal("settingsModalPred"));

    document.querySelectorAll(".alert-modal-overlay").forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
          overlay.classList.remove("active");
          unlockBody();
        }
      });
    });

    document.getElementById("openNotificationsPanel").addEventListener("click", openNotifications);
    document.getElementById("closeNotificationsPanel").addEventListener("click", closeNotifications);
    document.getElementById("notifOverlay").addEventListener("click", closeNotifications);

    document.getElementById("openAiModal").addEventListener("click", openAiModal);
    document.getElementById("closeAiModal").addEventListener("click", closeAiModal);
    document.getElementById("aiModal").addEventListener("click", (e) => {
      if (e.target.id === "aiModal") closeAiModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal("detailsModal");
        closeModal("simulateModal");
        closeModal("settingsModalPred");
        closeNotifications();
        closeAiModal();
      }
    });



    // Resumo baseado nos gastos REAIS do banco.
    

 
    
  </script>

</body>
</html>
