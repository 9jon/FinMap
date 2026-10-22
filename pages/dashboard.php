<?php
  
require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado();
include '../config/conn.php';

require_once __DIR__ . '/../includes/insights-financeiros.php';
$insightSemanal = finmapBuscarOuGerarInsightSemanal($conn, (int)$usuario_id);
$rotuloInsightToast = 'Resumo financeiro';
require_once __DIR__ . '/../includes/percentuais-categorias.php';
require_once __DIR__ . '/../includes/cores-categorias.php';



if (empty($_SESSION['csrf_importacao'])) {
    $_SESSION['csrf_importacao'] = bin2hex(random_bytes(32));
}
$csrfImportacao = $_SESSION['csrf_importacao'];
if(empty($_SESSION['csrf_conta']))$_SESSION['csrf_conta']=bin2hex(random_bytes(32));
$csrfConta=$_SESSION['csrf_conta'];
$importacaoFeedback = $_SESSION['importacao_feedback'] ?? null;
unset($_SESSION['importacao_feedback']);


$stmt = $conn->prepare("SELECT nome, email, telefone, avatar_iniciais, foto_perfil, saldo_total FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$configuracoesPadrao = ['notificar_orcamento'=>1,'notificar_metas'=>1,'notificar_gastos'=>1,'notificar_revisao'=>1,'notificar_agendamentos'=>1,'notificar_economia'=>1,'ocultar_valores'=>0];
$stmt = $conn->prepare('SELECT notificar_orcamento, notificar_metas, notificar_gastos, notificar_revisao, notificar_agendamentos, notificar_economia, ocultar_valores FROM preferencias_usuario WHERE usuario_id = ? LIMIT 1');
$stmt->bind_param('i', $usuario_id); $stmt->execute(); $preferenciasUsuario = $stmt->get_result()->fetch_assoc() ?: $configuracoesPadrao; $stmt->close();

$primeiroNome = explode(' ', $usuario['nome'] ?? 'Usuário')[0];
$iniciais = $usuario['avatar_iniciais'] ?? 'US';
$saldoTotal = (float) ($usuario['saldo_total'] ?? 0);


function formatarDataCurta(string $data): string
{
    $timestamp = strtotime($data);
    if ($timestamp === false) {
        return $data;
    }

    $meses = [
        1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez'
    ];

    return date('d', $timestamp) . ' ' . $meses[(int) date('n', $timestamp)];
}

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE
            WHEN tipo = 'receita'
             AND data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
             AND data_transacao <= CURDATE()
            THEN valor ELSE 0 END), 0) AS receitas_atual,
        COALESCE(SUM(CASE
            WHEN tipo = 'despesa'
             AND data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
             AND data_transacao <= CURDATE()
            THEN valor ELSE 0 END), 0) AS despesas_atual,
        COALESCE(SUM(CASE
            WHEN tipo = 'receita'
             AND data_transacao < DATE_FORMAT(CURDATE(), '%Y-%m-01')
            THEN valor ELSE 0 END), 0) AS receitas_anterior,
        COALESCE(SUM(CASE
            WHEN tipo = 'despesa'
             AND data_transacao < DATE_FORMAT(CURDATE(), '%Y-%m-01')
            THEN valor ELSE 0 END), 0) AS despesas_anterior,
        SUM(CASE
            WHEN data_transacao < DATE_FORMAT(CURDATE(), '%Y-%m-01')
            THEN 1 ELSE 0 END) AS lancamentos_anterior
    FROM transacoes
    WHERE usuario_id = ?
      AND status = 'aprovado'
      AND data_transacao >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
      AND data_transacao <= CURDATE()
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$comparativoMensal = $stmt->get_result()->fetch_assoc();
$stmt->close();

$receitasMes = (float) $comparativoMensal['receitas_atual'];
$despesasMes = (float) $comparativoMensal['despesas_atual'];
$totalDisponivelMes = $saldoTotal + $despesasMes;
$percentualGastosSaldo = $totalDisponivelMes > 0
    ? ($despesasMes / $totalDisponivelMes) * 100
    : (($saldoTotal < 0 || $despesasMes > 0) ? null : 0);
$percentualBarraGastos = $percentualGastosSaldo === null ? 100 : min(100, $percentualGastosSaldo);
$receitasMesAnterior = (float) $comparativoMensal['receitas_anterior'];
$despesasMesAnterior = (float) $comparativoMensal['despesas_anterior'];
$temRegistroMesAnterior = (int) $comparativoMensal['lancamentos_anterior'] > 0;

function calcularVariacaoMensal(float $valorAtual, float $valorAnterior, bool $temRegistroAnterior): ?float
{
    if (!$temRegistroAnterior || abs($valorAnterior) < 0.00001) {
        return null;
    }

    return (($valorAtual - $valorAnterior) / abs($valorAnterior)) * 100;
}

function textoVariacaoMensal(?float $variacao): string
{
    if ($variacao === null) {
        return 'Sem dados para comparar';
    }

    $sinal = $variacao > 0 ? '+' : '';
    return $sinal . number_format($variacao, 1, ',', '.') . '% vs mês anterior';
}

function iconeVariacaoMensal(?float $variacao): string
{
    if ($variacao === null) {
        return 'dash-lg';
    }

    if ($variacao > 0) {
        return 'arrow-up-right';
    }

    if ($variacao < 0) {
        return 'arrow-down-right';
    }

    return 'dash-lg';
}

$variacaoReceitas = calcularVariacaoMensal($receitasMes, $receitasMesAnterior, $temRegistroMesAnterior);
$variacaoDespesas = calcularVariacaoMensal($despesasMes, $despesasMesAnterior, $temRegistroMesAnterior);

$classeVariacaoReceitas = $variacaoReceitas === null
    ? 'neutral'
    : ($variacaoReceitas < 0 ? 'negative' : 'positive');
$classeVariacaoDespesas = $variacaoDespesas === null
    ? 'neutral'
    : ($variacaoDespesas > 0 ? 'negative' : 'positive');

$maiorReceitaComparativo = max($receitasMes, $receitasMesAnterior);
$maiorDespesaComparativo = max($despesasMes, $despesasMesAnterior);
$alturaMaximaComparativo = 36;
$alturaReceitaAnterior = $maiorReceitaComparativo > 0
    ? max(4, (int) round(($receitasMesAnterior / $maiorReceitaComparativo) * $alturaMaximaComparativo))
    : 4;
$alturaReceitaAtual = $maiorReceitaComparativo > 0
    ? max(4, (int) round(($receitasMes / $maiorReceitaComparativo) * $alturaMaximaComparativo))
    : 4;
$alturaDespesaAnterior = $maiorDespesaComparativo > 0
    ? max(4, (int) round(($despesasMesAnterior / $maiorDespesaComparativo) * $alturaMaximaComparativo))
    : 4;
$alturaDespesaAtual = $maiorDespesaComparativo > 0
    ? max(4, (int) round(($despesasMes / $maiorDespesaComparativo) * $alturaMaximaComparativo))
    : 4;


$stmt = $conn->prepare("
    SELECT t.id, t.descricao, t.valor, t.tipo, t.status, t.observacao_captura,
           t.data_transacao, c.nome AS categoria_nome
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ?
      AND (t.status = 'aprovado' OR (t.status = 'pendente' AND t.observacao_captura IN ('Fatura agendada', 'Receita agendada')))
    -- Recentes deve refletir os lançamentos incluídos ou aprovados por último.
    -- A data do extrato pode ser antiga, mas a aprovação acabou de acontecer.
    ORDER BY t.atualizado_em DESC, t.id DESC
    LIMIT 5
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$transacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT t.id, t.descricao, t.valor, t.tipo, t.status, t.observacao_captura,
           t.data_transacao, c.nome AS categoria_nome
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ?
      AND (t.status = 'aprovado' OR (t.status = 'pendente' AND t.observacao_captura IN ('Fatura agendada', 'Receita agendada')))
    ORDER BY t.atualizado_em DESC, t.id DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$todasTransacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$stmt = $conn->prepare("
    SELECT id, nome, valor_meta, valor_guardado, icone, cor
    FROM metas_financeiras
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$metas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$stmt = $conn->prepare("
    SELECT COALESCE(c.id, 0) AS id,
           COALESCE(c.nome, 'Sem categoria') AS nome,
           COALESCE(c.icone, 'tag') AS icone,
           COALESCE(c.cor, 'green') AS cor,
           COALESCE(SUM(t.valor), 0) AS total
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND t.tipo = 'despesa' AND t.status = 'aprovado'
      AND t.data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND t.data_transacao <= CURDATE()
    GROUP BY c.id, c.nome, c.icone, c.cor
    ORDER BY total DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$gastosCategorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($gastosCategorias as &$categoriaGasto) {
    $categoriaGasto['cor'] = finmapCorVisualCategoria(
        (int) $categoriaGasto['id'],
        (string) $categoriaGasto['nome'],
        $categoriaGasto['cor'] !== null ? (string) $categoriaGasto['cor'] : null
    );
}
unset($categoriaGasto);

aplicarPercentuaisInteiros($gastosCategorias, 'total', 'percentual');

$stmt = $conn->prepare("SELECT id, nome, tipo FROM categorias WHERE usuario_id = ? ORDER BY tipo, nome");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$categoriasUsuario = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$categoriasDespesa = array_filter($categoriasUsuario, fn($c) => $c['tipo'] === 'despesa');
$categoriasReceita = array_filter($categoriasUsuario, fn($c) => $c['tipo'] === 'receita');


/* =========================================================
   SISTEMA DE NOTIFICAÇÕES
   ========================================================= */

function criarNotificacao(
    mysqli $conn,
    int $usuario_id,
    string $categoria,
    string $titulo,
    string $mensagem
): void {

    // Evita criar a mesma notificação várias vezes
    $stmt = $conn->prepare("
        SELECT id
        FROM notificacoes
        WHERE usuario_id = ?
          AND categoria = ?
          AND titulo = ?
        ORDER BY criado_em DESC, id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "iss",
        $usuario_id,
        $categoria,
        $titulo
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $notificacaoAtual = $resultado->fetch_assoc();

    $stmt->close();

    if ($notificacaoAtual) {
        $notificacaoId = (int) $notificacaoAtual['id'];

        $stmt = $conn->prepare("DELETE FROM notificacoes WHERE usuario_id = ? AND categoria = ? AND titulo = ? AND id <> ?");
        if ($stmt) {
            $stmt->bind_param("issi", $usuario_id, $categoria, $titulo, $notificacaoId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("UPDATE notificacoes SET mensagem = ?, lida = 0, criado_em = CURRENT_TIMESTAMP WHERE id = ? AND usuario_id = ? AND mensagem <> ?");
        if ($stmt) {
            $stmt->bind_param("siis", $mensagem, $notificacaoId, $usuario_id, $mensagem);
            $stmt->execute();
            $stmt->close();
        }

        return;
    }

    // Insere a nova notificação
    $stmt = $conn->prepare("
        INSERT INTO notificacoes
        (
            usuario_id,
            categoria,
            titulo,
            mensagem,
            lida
        )
        VALUES (?, ?, ?, ?, 0)
    ");

    if (!$stmt) {
        return;
    }
$stmt->bind_param(
    "isss",
    $usuario_id,
    $categoria,
    $titulo,
    $mensagem
);

if (!$stmt->execute()) {
    die("ERRO AO CRIAR NOTIFICAÇÃO: " . $stmt->error);
}

$stmt->close();
}


/* =========================================================
   ALERTAS PREDITIVOS
   ========================================================= */

// Busca os alertas preditivos do usuário
$stmtAlertas = $conn->prepare("
    SELECT
        titulo,
        descricao,
        severidade
    FROM alertas_preditivos
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
    LIMIT 10
");

if ($stmtAlertas) {

    $stmtAlertas->bind_param("i", $usuario_id);
    $stmtAlertas->execute();

    $resultadoAlertas = $stmtAlertas->get_result();

    while ($alerta = $resultadoAlertas->fetch_assoc()) {

        $tituloAlerta = trim((string)($alerta['titulo'] ?? 'Alerta financeiro'));

        $descricaoAlerta = trim(
            (string)($alerta['descricao'] ?? 'Foi identificado um possível risco financeiro.')
        );

        if ($tituloAlerta === '') {
            $tituloAlerta = 'Alerta financeiro';
        }

        if ($descricaoAlerta === '') {
            $descricaoAlerta = 'Foi identificado um possível risco financeiro.';
        }

        criarNotificacao(
            $conn,
            $usuario_id,
            'alerta',
            $tituloAlerta,
            $descricaoAlerta
        );
    }

    $stmtAlertas->close();
}

/* =========================================================
   NOTIFICAÇÕES DE GASTOS
   ========================================================= */

$stmtGastosNotif = $conn->prepare("
    SELECT
        COALESCE(c.nome, 'Sem categoria') AS categoria_nome,
        COALESCE(SUM(t.valor), 0) AS total
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ?
      AND t.tipo = 'despesa'
      AND t.status = 'aprovado'
      AND t.data_transacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND t.data_transacao < DATE_ADD(
          DATE_FORMAT(CURDATE(), '%Y-%m-01'),
          INTERVAL 1 MONTH
      )
    GROUP BY c.id, c.nome
    ORDER BY total DESC
");

if ($stmtGastosNotif) {

    $stmtGastosNotif->bind_param("i", $usuario_id);
    $stmtGastosNotif->execute();

    $resultadoGastosNotif = $stmtGastosNotif->get_result();

    while ($gastoNotif = $resultadoGastosNotif->fetch_assoc()) {

        $categoriaNome = trim(
            (string) ($gastoNotif['categoria_nome'] ?? 'Sem categoria')
        );

        $totalGasto = (float) ($gastoNotif['total'] ?? 0);

        if ($totalGasto <= 0) {
            continue;
        }

        $valorFormatado = number_format(
            $totalGasto,
            2,
            ',',
            '.'
        );

        criarNotificacao(
            $conn,
            $usuario_id,
            'gasto',
            "Gastos: {$categoriaNome}",
            "Você já gastou R$ {$valorFormatado} na categoria {$categoriaNome} neste mês."
        );
    }

    $stmtGastosNotif->close();
}

/* =========================================================
   ALERTAS DAS METAS
   ========================================================= */

foreach ($metas as $meta) {

    $valorMeta = (float) $meta['valor_meta'];
    $valorGuardado = (float) $meta['valor_guardado'];
    $nomeMeta = trim((string) $meta['nome']);

    if ($nomeMeta === '') {
        $nomeMeta = 'sem nome';
    }

    if ($valorMeta <= 0) {
        continue;
    }

    $percentual =
        ($valorGuardado / $valorMeta) * 100;

    if ($percentual >= 100) {

        criarNotificacao(
            $conn,
            $usuario_id,
            'meta',
            "Meta concluída: {$nomeMeta}",
            "Parabéns! Você atingiu 100% da meta \"{$nomeMeta}\"."
        );

    } elseif ($percentual >= 75) {

        criarNotificacao(
            $conn,
            $usuario_id,
            'meta',
            "Meta avançando muito bem: {$nomeMeta}",
            "Sua meta \"{$nomeMeta}\" já atingiu " .
            round($percentual) .
            "% do valor planejado."
        );

    } elseif ($percentual >= 50) {

    criarNotificacao(
        $conn,
        $usuario_id,
        'meta',
        "Meta avançando bem: {$nomeMeta}",
        "Sua meta \"{$nomeMeta}\" atingiu " .
        round($percentual) .
        "% do valor planejado."
    );

} else {

    criarNotificacao(
        $conn,
        $usuario_id,
        'meta',
        "Acompanhe sua meta: {$nomeMeta}",
        "Sua meta \"{$nomeMeta}\" está em " .
        round($percentual) .
        "% do valor planejado."
    );
}
}


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
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=features-43">
  <link rel="stylesheet" href="../assets/css/page-entrance.css?v=2">
  <link rel="stylesheet" href="../assets/css/app-topbar.css?v=13">
  </head>
<body class="dashboard-page">

<?php if (is_array($importacaoFeedback)): ?>
  <?php $importacaoSucesso = ($importacaoFeedback['tipo'] ?? '') === 'sucesso'; ?>
  <div class="import-feedback import-feedback--<?= $importacaoSucesso ? 'success' : 'error' ?>" role="<?= $importacaoSucesso ? 'status' : 'alert' ?>">
    <i class="bi bi-<?= $importacaoSucesso ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <span><?= htmlspecialchars((string) ($importacaoFeedback['mensagem'] ?? 'Não foi possível importar o arquivo.')) ?></span>
  </div>
<?php endif; ?>

  <?php require __DIR__ . '/../components/app-topbar.php'; ?>

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

            <strong class="finance-card__value" data-count-up="<?= number_format($saldoTotal, 2, '.', '') ?>">R$ <?= number_format($saldoTotal, 2, ',', '.') ?></strong>
          </div>

          <div class="finance-card__icon finance-card__icon--glass">
            <i class="bi bi-wallet2"></i>
          </div>
        </div>


          <div class="finance-card__progress">
            <div class="finance-card__progress-heading">
              <span>Do total dispon&iacute;vel, j&aacute; gasto</span>
              <strong id="incomeUsagePercent"><?= $percentualGastosSaldo === null ? 'Saldo negativo' : number_format($percentualGastosSaldo, 0, ',', '.') . '%' ?></strong>
            </div>
            <div
              class="finance-card__progress-track"
              id="incomeUsageProgress"
              role="progressbar"
              aria-label="Proporcao dos gastos do mes em relacao ao saldo atual"
              aria-valuemin="0"
              aria-valuemax="100"
              aria-valuenow="<?= number_format($percentualBarraGastos, 1, '.', '') ?>"
              aria-valuetext="<?= $percentualGastosSaldo === null ? 'Saldo negativo; ' : number_format($percentualGastosSaldo, 1, ',', '.') . '% gasto; ' ?>R$ <?= number_format($despesasMes, 2, ',', '.') ?> gastos; saldo atual de R$ <?= number_format($saldoTotal, 2, ',', '.') ?>"
              data-gastos-mes="<?= number_format($despesasMes, 2, '.', '') ?>"
            >
              <span class="finance-card__progress-fill" style="width: <?= number_format($percentualBarraGastos, 2, '.', '') ?>%"></span>
            </div>
          </div>

      </article>

      <article class="finance-card">
        <div class="finance-card__top">
          <div>
            <span class="finance-card__label">Receitas</span>
            <strong class="finance-card__value" data-count-up="<?= number_format($receitasMes, 2, '.', '') ?>">R$ <?= number_format($receitasMes, 2, ',', '.') ?></strong>
          </div>

          <div
            class="finance-card__mini-chart finance-card__mini-chart--income"
            role="group"
            aria-label="Comparação das receitas: mês anterior R$ <?= number_format($receitasMesAnterior, 2, ',', '.') ?>; este mês R$ <?= number_format($receitasMes, 2, ',', '.') ?>"
          >
            <button class="finance-card__mini-hit" type="button" aria-expanded="false" aria-label="Receitas no mês anterior: R$ <?= number_format($receitasMesAnterior, 2, ',', '.') ?>" data-tooltip="Mês anterior · R$ <?= number_format($receitasMesAnterior, 2, ',', '.') ?>">
              <span class="finance-card__mini-bar finance-card__mini-bar--previous" style="height: <?= $alturaReceitaAnterior ?>px" aria-hidden="true"></span>
            </button>
            <button class="finance-card__mini-hit" type="button" aria-expanded="false" aria-label="Receitas neste mês: R$ <?= number_format($receitasMes, 2, ',', '.') ?>" data-tooltip="Este mês · R$ <?= number_format($receitasMes, 2, ',', '.') ?>">
              <span class="finance-card__mini-bar finance-card__mini-bar--current" style="height: <?= $alturaReceitaAtual ?>px" aria-hidden="true"></span>
            </button>
          </div>
        </div>

        <div class="finance-card__bottom">
          <span class="finance-card__trend finance-card__trend--<?= $classeVariacaoReceitas ?>">
            <i class="bi bi-<?= iconeVariacaoMensal($variacaoReceitas) ?>"></i>
            <?= textoVariacaoMensal($variacaoReceitas) ?>
          </span>
        </div>
      </article>

      <article class="finance-card">
        <div class="finance-card__top">
          <div>
            <span class="finance-card__label">Despesas</span>
            <strong class="finance-card__value" data-count-up="<?= number_format($despesasMes, 2, '.', '') ?>">R$ <?= number_format($despesasMes, 2, ',', '.') ?></strong>
          </div>

          <div
            class="finance-card__mini-chart finance-card__mini-chart--expense"
            role="group"
            aria-label="Comparação das despesas: mês anterior R$ <?= number_format($despesasMesAnterior, 2, ',', '.') ?>; este mês R$ <?= number_format($despesasMes, 2, ',', '.') ?>"
          >
            <button class="finance-card__mini-hit" type="button" aria-expanded="false" aria-label="Despesas no mês anterior: R$ <?= number_format($despesasMesAnterior, 2, ',', '.') ?>" data-tooltip="Mês anterior · R$ <?= number_format($despesasMesAnterior, 2, ',', '.') ?>">
              <span class="finance-card__mini-bar finance-card__mini-bar--previous" style="height: <?= $alturaDespesaAnterior ?>px" aria-hidden="true"></span>
            </button>
            <button class="finance-card__mini-hit" type="button" aria-expanded="false" aria-label="Despesas neste mês: R$ <?= number_format($despesasMes, 2, ',', '.') ?>" data-tooltip="Este mês · R$ <?= number_format($despesasMes, 2, ',', '.') ?>">
              <span class="finance-card__mini-bar finance-card__mini-bar--current" style="height: <?= $alturaDespesaAtual ?>px" aria-hidden="true"></span>
            </button>
          </div>
        </div>

        <div class="finance-card__bottom">
          <span class="finance-card__trend finance-card__trend--<?= $classeVariacaoDespesas ?>">
            <i class="bi bi-<?= iconeVariacaoMensal($variacaoDespesas) ?>"></i>
            <?= textoVariacaoMensal($variacaoDespesas) ?>
          </span>
        </div>
      </article>
    </div>
  </section>

  <div class="dashboard-panels">
      <section class="recent-transactions-panel">
        <div class="recent-transactions-panel__header">
          <div>
            <h3>Transações recentes</h3>
          </div>

          <a href="#" class="recent-transactions-panel__link" id="openAllTransactionsModal">
            <span class="recent-transactions-panel__link-desktop">Ver todas as transações</span>
            <span class="recent-transactions-panel__link-mobile">Ver todas</span>
            <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <div class="recent-transactions-panel__list">
          <?php if (empty($transacoes)): ?>
            <p style="padding: 16px 0; color: #888;">Nenhuma transação registrada ainda.</p>
          <?php else: ?>
            <?php foreach ($transacoes as $t): ?>
              <?php
                $isReceita = $t['tipo'] === 'receita';
                $isAgendado = $t['status'] === 'pendente'
                    && in_array($t['observacao_captura'], ['Fatura agendada', 'Receita agendada'], true);
              ?>
              <article class="transaction-item" data-transaction-type="<?= $isReceita ? 'receita' : 'despesa' ?>">
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
                  <span class="transaction-item__date"><?= htmlspecialchars(formatarDataCurta($t['data_transacao'])) ?></span>
                  <?php if ($isAgendado): ?>
                    <span class="transaction-item__status transaction-item__status--scheduled"><?= htmlspecialchars($t['observacao_captura']) ?></span>
                  <?php endif; ?>
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
          </div>

          <button class="financial-goals-panel__menu" id="openGoalsMenuModal" type="button" aria-label="Mais opções" title="Opções das metas">
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
          Criar meta
        </button>
      </section>
    </div>

    <div class="dashboard-bottom-row">
      <section class="category-expenses-panel">
        <div class="category-expenses-panel__header">
          <div>
            <h3>Gastos por categoria</h3>
          </div>

          <p id="categoryPeriodDescription">Do primeiro dia do mês atual até hoje</p>

          <button class="category-expenses-panel__filter" id="openCategoryPeriodModal" type="button">
            <i class="bi bi-sliders"></i>
            <span id="categoryPeriodLabel">Este mês</span>
          </button>
        </div>

        <div class="category-expenses-list" id="categoryExpensesList">
          <?php if (empty($gastosCategorias)): ?>
            <p style="padding: 16px 0; color: #888;">Nenhum gasto registrado neste período ainda.</p>
          <?php else: ?>
            <?php foreach ($gastosCategorias as $g):
              $percentualCat = (int) ($g['percentual'] ?? 0);
            ?>
              <article class="category-expense-card" data-category-id="<?= (int) $g['id'] ?>">
                <div class="category-expense-card__top">
                  <div class="category-expense-card__left">
                    <div class="category-expense-card__icon category-expense-card__icon--<?= htmlspecialchars($g['cor'] ?: 'green') ?>">
                      <i class="bi bi-<?= htmlspecialchars($g['icone'] ?: 'tag') ?>"></i>
                    </div>

                    <div class="category-expense-card__info">
                      <h4><?= htmlspecialchars($g['nome']) ?></h4>
                      <p>Total de gastos nesta categoria no período</p>
                    </div>
                  </div>

                  <div class="category-expense-card__right">
                    <div class="category-expense-card__numbers">
                      <strong>R$ <?= number_format($g['total'], 2, ',', '.') ?></strong>
                      <span><?= $percentualCat ?>%</span>
                    </div>
                    <button class="category-expense-card__details-toggle" type="button" aria-expanded="false" aria-label="Ver movimentações de <?= htmlspecialchars($g['nome'], ENT_QUOTES) ?>">
                      <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>

                <div class="category-expense-card__bar">
                  <div class="category-expense-card__fill category-expense-card__fill--<?= htmlspecialchars($g['cor'] ?: 'green') ?>" style="width: <?= $percentualCat ?>%;"></div>
                </div>
                <div class="category-expense-card__details" hidden></div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </section>

  <?php if (!empty($insightSemanal)): ?>
  <aside
    class="weekly-insight-toast"
    id="weeklyInsightToast"
    data-insight-key="<?= htmlspecialchars((string)($insightSemanal['periodo_inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    aria-labelledby="weeklyInsightToastTitle"
    role="status"
  >
    <div class="weekly-insight-toast__icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></div>
    <div class="weekly-insight-toast__content">
      <p class="weekly-insight-toast__eyebrow"><?= htmlspecialchars($rotuloInsightToast, ENT_QUOTES, 'UTF-8') ?></p>
      <h2 id="weeklyInsightToastTitle"><?= htmlspecialchars((string)($insightSemanal['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="weekly-insight-toast__message"><?= htmlspecialchars((string)($insightSemanal['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <button class="weekly-insight-toast__close" type="button" aria-label="Fechar insight">
      <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
  </aside>
  <?php endif; ?>
  <button class="floating-transaction-btn" id="openTransactionModal" type="button" aria-label="Adicionar transação">
    <span class="floating-transaction-btn__icon">
      <i class="bi bi-plus-lg"></i>
    </span>
    <span class="floating-transaction-btn__content">
      <strong>Adicionar transação</strong>
    </span>
  </button>

  <script>
  (() => {
    const toast = document.getElementById("weeklyInsightToast");
    if (!toast) return;

    const key = "finmap-insight-fechado:" + (toast.dataset.insightKey || "semana");
    try {
      if (window.localStorage.getItem(key) === "1") {
        toast.remove();
        return;
      }
    } catch (error) {}

    toast.querySelector(".weekly-insight-toast__close")?.addEventListener("click", () => {
      try { window.localStorage.setItem(key, "1"); } catch (error) {}
      toast.classList.add("is-closing");
      window.setTimeout(() => toast.remove(), 220);
    });
  })();
  </script>
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

    <button
        class="notif-tab active"
        type="button"
        data-filter="todos"
    >
        Todos
    </button>

    <button
        class="notif-tab"
        type="button"
        data-filter="alerta"
    >
        Alertas
    </button>

    <button
        class="notif-tab"
        type="button"
        data-filter="meta"
    >
        Metas
    </button>

    <button
        class="notif-tab"
        type="button"
        data-filter="gasto"
    >
        Gastos
    </button>

</div>

   <div class="notif-panel__list" id="notificationsList">

<?php

$stmt = $conn->prepare("
    SELECT
        id,
        categoria,
        titulo,
        mensagem,
        lida,
        criado_em
    FROM notificacoes
    WHERE usuario_id = ?
      AND NOT (categoria = 'meta' AND ? = 0)
      AND NOT (categoria = 'gasto' AND ? = 0)
      AND NOT (categoria = 'alerta' AND ? = 0)
      AND NOT (categoria = 'ia' AND ? = 0)
    ORDER BY criado_em DESC
    LIMIT 30
");

$notificarMetas=(int)$preferenciasUsuario['notificar_metas'];
$notificarGastos=(int)$preferenciasUsuario['notificar_gastos'];
$notificarOrcamento=(int)$preferenciasUsuario['notificar_orcamento'];
$notificarEconomia=(int)$preferenciasUsuario['notificar_economia'];
$stmt->bind_param("iiiii", $usuario_id, $notificarMetas, $notificarGastos, $notificarOrcamento, $notificarEconomia);
$stmt->execute();

$notificacoes =
    $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

?>

<?php if (empty($notificacoes)): ?>

    <div class="notif-empty" role="status" aria-live="polite">
        <h4>Tudo em dia</h4>
        <p>Quando surgir algo importante nas suas finanças, você encontra aqui.</p>
    </div>

<?php else: ?>

    <?php foreach ($notificacoes as $n): ?>

        <?php

        if ($n['categoria'] === 'meta') {

            $classe = 'success';
            $icone = 'bullseye';

        } elseif ($n['categoria'] === 'gasto') {

            $classe = 'warning';
            $icone = 'receipt';

        } elseif ($n['categoria'] === 'ia') {

            $classe = 'info';
            $icone = 'stars';

        } else {

            $classe = 'danger';
            $icone = 'exclamation-triangle';
        }

        ?>

        <article
            class="notif-card notif-card--<?= $classe ?><?= (int) $n['lida'] === 1 ? ' is-read' : '' ?>"
            data-tipo="<?= htmlspecialchars($n['categoria']) ?>"
            data-id="<?= (int) $n['id'] ?>"
            data-lida="<?= (int) $n['lida'] ?>"
        >

            <div class="notif-card__icon">

                <i class="bi bi-<?= $icone ?>"></i>

            </div>

            <div class="notif-card__content">

                <h4>
                    <?= htmlspecialchars($n['titulo']) ?>
                </h4>

                <p>
                    <?= htmlspecialchars($n['mensagem']) ?>
                </p>

                <span>
                    <?= date('d/m/Y H:i', strtotime($n['criado_em'])) ?>
                </span>

            </div>

        </article>

    <?php endforeach; ?>

<?php endif; ?>

</div>
  </aside>

  <!-- ASSISTENTE FINANCEIRO -->
  <?php require __DIR__ . '/../components/ai-assistant.php'; ?>

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
        <button class="transaction-option-card" id="openManualTransactionOption" type="button">
          <div class="transaction-option-card__icon transaction-option-card__icon--green">
            <i class="bi bi-pencil-square"></i>
          </div>

          <div class="transaction-option-card__content">
            <h4>Adicionar manualmente</h4>
            <p>Preencha os dados da transação de forma personalizada e imediata.</p>
          </div>
        </button>

        <button class="transaction-option-card" id="openImportTransactionOption" type="button">
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

  <!-- MODAL: NOVA TRANSAÇÃO MANUAL -->
  <div class="dashboard-popout-overlay" id="manualTransactionModal">
    <div class="dashboard-popout dashboard-popout--form dashboard-popout--manual">
      <div class="dashboard-popout__header">
        <div class="dashboard-popout__title-group">
          <div class="dashboard-popout__icon dashboard-popout__icon--green">
            <i class="bi bi-pencil-square"></i>
          </div>
          <div>
            <h3>Nova transação manual</h3>
            <p>Registre uma receita ou despesa diretamente no FinMap.</p>
          </div>
        </div>

        <button class="dashboard-popout__close" id="closeManualTransactionModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="dashboard-popout__body">
        <form method="post" action="criar-transacao.php" id="manualTransactionForm">

          <div class="mb-3">
            <label class="form-label fw-semibold">Tipo</label>
            <div class="d-flex gap-3 manual-transaction-type">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo" id="tipoDespesa" value="despesa" checked>
                <label class="form-check-label" for="tipoDespesa">Despesa</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo" id="tipoReceita" value="receita">
                <label class="form-check-label" for="tipoReceita">Receita</label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label for="manualDescricao" class="form-label fw-semibold">Descrição</label>
            <input type="text" class="form-control" id="manualDescricao" name="descricao" placeholder="Ex: Supermercado" required>
          </div>

          <div class="mb-3">
            <label for="manualValor" class="form-label fw-semibold">Valor</label>
            <input type="text" class="form-control finmap-money-input" id="manualValor" name="valor" inputmode="numeric" placeholder="R$ 0,00" autocomplete="off" required>
          </div>

          
          <div class="mb-3" id="categoriaDespesaWrapper">
            <label for="categoriaDespesaSelect" class="form-label fw-semibold">Categoria</label>
            <select class="form-select" id="categoriaDespesaSelect" name="categoria_id_despesa">
              <option value="">Sem categoria</option>
              <?php foreach ($categoriasDespesa as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3 d-none" id="categoriaReceitaWrapper">
            <label for="categoriaReceitaSelect" class="form-label fw-semibold">Categoria</label>
            <select class="form-select" id="categoriaReceitaSelect" name="categoria_id_receita">
              <option value="">Sem categoria</option>
              <?php foreach ($categoriasReceita as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label for="manualData" class="form-label fw-semibold">Data</label>
            <input type="date" class="form-control" id="manualData" name="data_transacao" value="<?= date('Y-m-d') ?>" required>
            <div class="form-text">Datas futuras ficam como lançamento agendado e não alteram o saldo até a aprovação.</div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="settings-footer-btn settings-footer-btn--secondary" id="cancelManualTransactionModal">Cancelar</button>
            <button type="submit" class="settings-footer-btn settings-footer-btn--primary">Salvar transação</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: IMPORTAR ARQUIVO -->
  <div class="dashboard-popout-overlay" id="importTransactionModal">
    <div class="dashboard-popout dashboard-popout--form import-popout">
      <div class="dashboard-popout__header">
        <div class="dashboard-popout__title-group">
          <div class="dashboard-popout__icon dashboard-popout__icon--orange">
            <i class="bi bi-file-earmark-arrow-up"></i>
          </div>
          <div>
            <h3>Importar transações</h3>
            <p>Envie seu extrato para identificar lançamentos e preparar a revisão.</p>
          </div>
        </div>

        <button class="dashboard-popout__close" id="closeImportTransactionModal" type="button" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="dashboard-popout__body">
        <form class="import-form" method="post" action="importar-transacoes.php" enctype="multipart/form-data" id="importTransactionForm">
          <input type="hidden" name="csrf_importacao" value="<?= htmlspecialchars($csrfImportacao) ?>">

          <div class="import-file-callout import-file-callout--minimal">
            <i class="bi bi-shield-check"></i>
            <div>
              <strong>Importação automática</strong>
              <p>Lançamentos com data passada ou atual são aprovados automaticamente. Apenas datas futuras ficam agendadas; o saldo atual considera somente este mês.</p>
            </div>
          </div>

          <div class="mb-3 import-file-field">
            <label for="importFile" class="form-label fw-semibold">Arquivo do extrato</label>
            <input class="form-control import-file-input" id="importFile" name="arquivo_importacao" type="file" accept=".csv,.ofx,.qfx,.xlsx" required aria-describedby="importFileStatus">
            <label class="import-file-picker" for="importFile">
              <span class="import-file-picker__icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
              <span>Escolher arquivo</span>
            </label>
            <span class="import-file-status" id="importFileStatus" aria-live="polite">Nenhum arquivo selecionado</span>
            <div class="form-text">Formatos aceitos: CSV, OFX e Excel (.xlsx). Máximo de 10 MB e 5.000 lançamentos.</div>
          </div>

          <div class="import-file-tips import-file-tips--minimal">
            <div><i class="bi bi-check2"></i><span>Leitura automática</span></div>
            <div><i class="bi bi-check2"></i><span>Categorias sugeridas</span></div>
            <div><i class="bi bi-check2"></i><span>Duplicados ignorados</span></div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4 import-form__actions">
            <button type="button" class="settings-footer-btn settings-footer-btn--secondary" id="cancelImportTransactionModal">Cancelar</button>
            <button type="submit" class="settings-footer-btn settings-footer-btn--primary" id="submitImportTransaction">
              <i class="bi bi-file-earmark-arrow-up me-1"></i> Importar e revisar
            </button>
          </div>
        </form>
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

        <button class="settings-sidebar__item" type="button" data-target="settings-security">
          <span class="settings-sidebar__icon settings-sidebar__icon--purple">
            <i class="bi bi-shield-lock-fill"></i>
          </span>
          <span class="settings-sidebar__text">Proteção</span>
        </button>
<button class="settings-sidebar__item" type="button" data-target="settings-account">
          <span class="settings-sidebar__icon settings-sidebar__icon--green">
            <i class="bi bi-person-circle"></i>
          </span>
          <span class="settings-sidebar__text">Conta</span>
        </button>
      </aside>

      <div class="settings-content">
        <!-- NOTIFICAÇÕES -->
        <section class="settings-group is-active" id="settings-notifications">
          <button class="settings-group__trigger active" type="button">
            <div class="settings-group__trigger-left">
              <span class="settings-group__badge settings-group__badge--yellow">
                <i class="bi bi-bell-fill"></i>
              </span>
              <div>
                <h4>Notificações</h4>
                <p>Avisos sobre seus gastos, metas e lançamentos.</p>
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
                  <h5 id="notification-budget-label">Risco no orçamento</h5>
                  <p id="notification-budget-description">Alertas quando os gastos comprometerem a renda do mês.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="notificar_orcamento" aria-labelledby="notification-budget-label" aria-describedby="notification-budget-description" <?= !empty($preferenciasUsuario['notificar_orcamento']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5 id="notification-goals-label">Progresso das metas</h5>
                  <p id="notification-goals-description">Avisos ao atingir 50%, 75% e 100% do valor de uma meta.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="notificar_metas" aria-labelledby="notification-goals-label" aria-describedby="notification-goals-description" <?= !empty($preferenciasUsuario['notificar_metas']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5 id="notification-spending-label">Gastos por categoria</h5>
                  <p id="notification-spending-description">Atualizações dos totais gastos em alimentação, moradia e outras categorias no mês.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="notificar_gastos" aria-labelledby="notification-spending-label" aria-describedby="notification-spending-description" <?= !empty($preferenciasUsuario['notificar_gastos']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5 id="notification-review-label">Lançamentos para revisar</h5>
                  <p id="notification-review-description">Lembretes de transações pendentes de aprovação ou com categoria ainda não definida.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="notificar_revisao" aria-labelledby="notification-review-label" aria-describedby="notification-review-description" <?= !empty($preferenciasUsuario['notificar_revisao']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5 id="notification-scheduled-label">Despesas e receitas agendadas</h5>
                  <p id="notification-scheduled-description">Lembretes na data do lançamento para você revisar e aprovar a movimentação.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="notificar_agendamentos" aria-labelledby="notification-scheduled-label" aria-describedby="notification-scheduled-description" <?= !empty($preferenciasUsuario['notificar_agendamentos']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>

              <div class="settings-row">
                <div class="settings-row__info">
                  <h5 id="notification-savings-label">Oportunidades de economia</h5>
                  <p id="notification-savings-description">Sugestões da Poupança Invisível para reduzir gastos com assinaturas, lanches, compras e transporte por aplicativo.</p>
                </div>
                <label class="switch switch--green">
                  <input type="checkbox" name="notificar_economia" aria-labelledby="notification-savings-label" aria-describedby="notification-savings-description" <?= !empty($preferenciasUsuario['notificar_economia']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>
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
                  <h5>Senha de acesso</h5>
                  <p>Atualize sua senha para proteger sua conta.</p>
                </div>
                <button class="settings-action-btn settings-action-btn--purple" id="changePasswordBtn" type="button"><i class="bi bi-key-fill"></i> Alterar senha</button>
              </div>
              <div class="settings-row">
                <div class="settings-row__info">
                  <h5>Ocultar valores</h5>
                  <p>Esconde os valores em ambientes públicos.</p>
                </div>
                <label class="switch switch--red">
                  <input type="checkbox" name="ocultar_valores" <?= !empty($preferenciasUsuario['ocultar_valores']) ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>

          </div>
        </section>
<!-- CONTA -->
        <section class="settings-group" id="settings-account">
          <button class="settings-group__trigger" type="button">
            <div class="settings-group__trigger-left">
              <span class="settings-group__badge settings-group__badge--green">
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
            <div class="settings-account-grid settings-account-grid--clean">
              <article class="settings-profile-card settings-profile-card--edit">
                <div class="profile-edit-heading">
                  <div class="profile-avatar-wrap">
                    <div class="profile-edit-avatar<?= !empty($usuario['foto_perfil']) ? ' has-photo' : '' ?>" id="profilePreview" data-initials="<?= htmlspecialchars($iniciais,ENT_QUOTES,'UTF-8') ?>"><?php if(!empty($usuario['foto_perfil'])): ?><img src="../<?= htmlspecialchars($usuario['foto_perfil'],ENT_QUOTES,'UTF-8') ?>" alt=""><?php else: ?><?= htmlspecialchars($iniciais) ?><?php endif; ?></div>
                    <button class="profile-photo-pencil" id="profilePhotoToggle" type="button" aria-label="Editar foto de perfil" aria-haspopup="true" aria-expanded="false"><i class="bi bi-pencil-fill" aria-hidden="true"></i></button>
                    <div class="profile-photo-menu" id="profilePhotoMenu" hidden>
                      <button class="profile-photo-menu__item" id="chooseProfilePhoto" type="button"><i class="bi bi-images"></i><span>Escolher da galeria</span></button>
                      <button class="profile-photo-menu__item profile-photo-menu__item--remove" id="removeProfilePhoto" type="button" <?= empty($usuario['foto_perfil']) ? 'hidden' : '' ?>><i class="bi bi-trash3"></i><span>Remover foto</span></button>
                    </div>
                  </div>
                  <div><h5>Seu perfil</h5><p>Gerencie seus dados pessoais e sua foto no FinMap.</p></div>
                </div>
                <form id="profileForm" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfConta,ENT_QUOTES,'UTF-8') ?>">
                  <input type="hidden" name="acao" value="salvar_perfil">
                  <div class="profile-form-row">
                    <label class="profile-field-label" for="profileName">Nome completo</label>
                    <input class="profile-field" id="profileName" name="nome" maxlength="120" value="<?= htmlspecialchars($usuario['nome']??'',ENT_QUOTES,'UTF-8') ?>" required>
                  </div>
                  <div class="profile-form-row">
                    <label class="profile-field-label" for="profileEmail">E-mail</label>
                    <input class="profile-field" id="profileEmail" name="email" type="email" maxlength="190" value="<?= htmlspecialchars($usuario['email']??'',ENT_QUOTES,'UTF-8') ?>" required>
                  </div>
                  <div class="profile-form-row">
                    <label class="profile-field-label" for="profilePhone">Telefone</label>
                    <input class="profile-field" id="profilePhone" name="telefone" type="tel" maxlength="25" autocomplete="tel" placeholder="+55 11 99999-9999" value="<?= htmlspecialchars($usuario['telefone']??'',ENT_QUOTES,'UTF-8') ?>">
                  </div>
                  <input type="file" class="profile-file-input" id="profilePhoto" name="foto_perfil" accept="image/jpeg,image/png,image/webp">
                  <input type="hidden" id="removeProfilePhotoFlag" name="remover_foto" value="0">

                  <div class="profile-form-actions">
                    <p id="profileFeedback" class="profile-feedback" role="status" aria-live="polite"></p>
                    <button class="settings-action-btn settings-action-btn--save" type="submit"><i class="bi bi-check2"></i> Salvar perfil</button>
                  </div>
                </form>
              </article>
              <article class="settings-profile-card settings-profile-card--logout-row">
                <div class="profile-logout-copy"><span class="profile-logout-icon"><i class="bi bi-box-arrow-right"></i></span><div><h5>Sair da conta</h5><p>Encerre sua sessão neste dispositivo.</p></div></div>
                <form action="../login/logout.php" method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfConta,ENT_QUOTES,'UTF-8') ?>">
                  <button class="settings-action-btn settings-action-btn--logout" type="submit">Desconectar</button>
                </form>
              </article>
              <article class="settings-profile-card settings-profile-card--danger settings-profile-card--danger-row">
                <div class="profile-danger-copy"><span class="profile-danger-icon"><i class="bi bi-trash3"></i></span><div><h5>Excluir conta</h5><p>A exclusão é permanente e remove seus dados de acesso.</p></div></div>
                <button class="settings-action-btn settings-action-btn--danger" id="deleteAccountBtn" type="button">Excluir conta</button>
              </article>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="settings-modal__footer">
      <button class="settings-footer-btn settings-footer-btn--secondary" id="cancelSettingsModal" type="button">Cancelar</button>
      <button class="settings-footer-btn settings-footer-btn--primary" type="button">Salvar altera&#231;&#245;es</button>
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
          <h3>Todas as transações</h3>
          <p>Histórico completo das movimentações, ordenado pela atividade mais recente.</p>
        </div>
      </div>

      <button class="dashboard-popout__close" id="closeAllTransactionsModal" type="button" aria-label="Fechar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="dashboard-popout__body" style="max-height: 70vh; overflow: hidden;">
      
      <label class="all-transactions-search" for="allTransactionsSearch">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input id="allTransactionsSearch" type="search" placeholder="Buscar despesa ou receita" autocomplete="off">
      </label>
      <div class="all-transactions-list" style="max-height: 100%; overflow-y: auto; padding-right: 4px;">
        <?php if (empty($todasTransacoes)): ?>
          <p style="padding: 16px 0; color: #888;">Nenhuma transação registrada ainda.</p>
        <?php else: ?>
          <?php foreach ($todasTransacoes as $t): ?>
            <?php
              $isReceita = $t['tipo'] === 'receita';
              $isAgendado = $t['status'] === 'pendente'
                  && in_array($t['observacao_captura'], ['Fatura agendada', 'Receita agendada'], true);
            ?>
            <article class="transaction-item" data-transaction-type="<?= $isReceita ? 'receita' : 'despesa' ?>">
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
                <span class="transaction-item__date"><?= htmlspecialchars(formatarDataCurta($t['data_transacao'])) ?></span>
                <?php if ($isAgendado): ?>
                  <span class="transaction-item__status transaction-item__status--scheduled"><?= htmlspecialchars($t['observacao_captura']) ?></span>
                <?php endif; ?>
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
      <p class="dashboard-period-help">Os valores consideram apenas despesas aprovadas e com data até hoje.</p>
      <div class="dashboard-period-list">
        <button class="dashboard-period-option active" type="button" data-period="this-month">Este mês</button>
        <button class="dashboard-period-option" type="button" data-period="last-month">Mês anterior</button>
        <button class="dashboard-period-option" type="button" data-period="last-3-months">Últimos 3 meses</button>
        <button class="dashboard-period-option" type="button" data-period="custom">Período personalizado</button>
      </div>
      <div class="dashboard-period-custom" id="customPeriodFields" hidden>
        <label for="customPeriodStart">De</label>
        <input type="date" id="customPeriodStart">
        <label for="customPeriodEnd">Até</label>
        <input type="date" id="customPeriodEnd">
        <button class="dashboard-period-apply" type="button" id="applyCustomPeriod">Aplicar período</button>
        <small id="customPeriodError" role="alert"></small>
      </div>
    </div>
  </div>
</div>


 <script>
  const body = document.body;
  function formatDatabaseBRL(value){
    return new Intl.NumberFormat("pt-BR",{style:"currency",currency:"BRL",minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value)||0);
  }

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
  const profileOpener=document.getElementById("openProfileSettings"), profileForm=document.getElementById("profileForm"), photoInput=document.getElementById("profilePhoto"), preview=document.getElementById("profilePreview"), feedback=document.getElementById("profileFeedback"), deleteBtn=document.getElementById("deleteAccountBtn");
  const photoToggle=document.getElementById("profilePhotoToggle"), photoMenu=document.getElementById("profilePhotoMenu"), choosePhoto=document.getElementById("chooseProfilePhoto"), removePhoto=document.getElementById("removeProfilePhoto"), removePhotoFlag=document.getElementById("removeProfilePhotoFlag"), photoStatus=document.getElementById("profilePhotoStatus");
  const settingsSaveButton=settingsModal?.querySelector(".settings-modal__footer .settings-footer-btn--primary");
  const csrfSettings=document.querySelector('#profileForm [name="csrf_token"]')?.value||"";
  const privateValueSelector=".finance-card__value, .transaction-item__value, .goal-card__bottom, .goal-card__info p, .category-item strong";
  const setValuesVisibility=hidden=>{
    document.body.classList.toggle("finmap-values-hidden",hidden);
    document.querySelectorAll(privateValueSelector).forEach(value=>{
      value.classList.remove("is-value-revealed");
      if(hidden){value.tabIndex=0;value.setAttribute("role","button");value.setAttribute("aria-label","Valor oculto. Clique para revelar.");value.setAttribute("aria-pressed","false");}
      else{value.removeAttribute("tabindex");value.removeAttribute("role");value.removeAttribute("aria-label");value.removeAttribute("aria-pressed");}
    });
  };
  const togglePrivateValue=value=>{
    const revealed=value.classList.toggle("is-value-revealed");
    value.setAttribute("aria-pressed",String(revealed));
    if(revealed)value.removeAttribute("aria-label");
    else value.setAttribute("aria-label","Valor oculto. Clique para revelar.");
  };
  document.addEventListener("click",event=>{
    if(!document.body.classList.contains("finmap-values-hidden"))return;
    const value=event.target.closest(privateValueSelector);
    if(value)togglePrivateValue(value);
  });
  document.addEventListener("keydown",event=>{
    if((event.key!=="Enter"&&event.key!==" ")||!document.body.classList.contains("finmap-values-hidden"))return;
    const value=event.target.closest(privateValueSelector);
    if(value){event.preventDefault();togglePrivateValue(value);}
  });
  setValuesVisibility(<?= !empty($preferenciasUsuario['ocultar_valores']) ? 'true' : 'false' ?>);
  if(settingsSaveButton)settingsSaveButton.addEventListener("click",async()=>{
    const fields=[...settingsModal.querySelectorAll('#settings-notifications input[type="checkbox"], #settings-security input[name="ocultar_valores"]')];
    const data=new FormData();data.append("csrf_token",csrfSettings);fields.forEach(input=>data.append(input.name,input.checked?"1":"0"));
    const originalLabel="Salvar altera\u00e7\u00f5es";settingsSaveButton.disabled=true;settingsSaveButton.textContent="Salvando...";
    try{
      const response=await fetch("../pages/salvar-configuracoes.php",{method:"POST",body:data});const result=await response.json();
      if(!response.ok||!result.sucesso)throw Error(result.erro||"Nao foi possivel salvar as configuracoes.");
      setValuesVisibility(fields.find(input=>input.name==="ocultar_valores")?.checked||false);
      settingsSaveButton.textContent="Salvo";setTimeout(()=>{settingsSaveButton.textContent=originalLabel;},1300);
    }catch(error){alert(error.message);settingsSaveButton.textContent=originalLabel;}
    finally{settingsSaveButton.disabled=false;}
  });
  const changePasswordBtn=document.getElementById("changePasswordBtn");
  if(changePasswordBtn)changePasswordBtn.addEventListener("click",async()=>{const atual=prompt("Digite sua senha atual:");if(atual===null)return;const nova=prompt("Digite a nova senha (mínimo de 8 caracteres):");if(nova===null)return;const confirmacao=prompt("Digite novamente a nova senha:");if(confirmacao===null)return;const data=new FormData();data.append("acao","alterar_senha");data.append("csrf_token",csrfSettings);data.append("senha_atual",atual);data.append("nova_senha",nova);data.append("confirmar_senha",confirmacao);try{const response=await fetch("../pages/atualizar-conta.php",{method:"POST",body:data});const result=await response.json();if(!response.ok||!result.sucesso)throw Error(result.erro||"Não foi possível alterar a senha.");alert(result.mensagem);}catch(error){alert(error.message);}});
  if(profileOpener)profileOpener.addEventListener("click",()=>{openModal(settingsModal);openSettingsSection("settings-account",false);});
  if(photoToggle)photoToggle.addEventListener("click",()=>{const open=photoMenu.hidden;photoMenu.hidden=!open;photoToggle.setAttribute("aria-expanded",String(open));});
  if(choosePhoto)choosePhoto.addEventListener("click",()=>{photoMenu.hidden=true;photoToggle.setAttribute("aria-expanded","false");photoInput.click();});
  if(removePhoto)removePhoto.addEventListener("click",()=>{photoInput.value="";removePhotoFlag.value="1";preview.innerHTML=preview.dataset.initials||"US";preview.classList.remove("has-photo");removePhoto.hidden=true;photoMenu.hidden=true;photoToggle.setAttribute("aria-expanded","false");});
  document.addEventListener("click",e=>{if(photoMenu&&!e.target.closest(".profile-avatar-wrap")){photoMenu.hidden=true;if(photoToggle)photoToggle.setAttribute("aria-expanded","false");}});
  if(photoInput)photoInput.addEventListener("change",()=>{const f=photoInput.files[0];if(f&&f.size>3145728){photoInput.value="";feedback.textContent="A foto deve ter até 3 MB.";return;}if(f){removePhotoFlag.value="0";removePhoto.hidden=false;const rd=new FileReader();rd.onload=()=>{preview.innerHTML='<img src="'+rd.result+'" alt="">';preview.classList.add("has-photo");};rd.readAsDataURL(f);}});
  if(profileForm)profileForm.addEventListener("submit",async e=>{
    e.preventDefault();feedback.textContent="Salvando...";
    try{
      const r=await fetch("../pages/atualizar-conta.php",{method:"POST",body:new FormData(profileForm)}),d=await r.json();
      if(!r.ok||!d.sucesso)throw Error(d.erro);
      feedback.textContent=d.mensagem;
      const avatar=document.getElementById("globalProfileLink"),bust="?v="+Date.now();
      if(avatar)avatar.innerHTML=d.foto?'<img src="'+d.foto+bust+'" alt="">':d.iniciais;
      preview.innerHTML=d.foto?'<img src="'+d.foto+bust+'" alt="">':d.iniciais;preview.dataset.initials=d.iniciais;preview.classList.toggle("has-photo",!!d.foto);
      removePhotoFlag.value="0";removePhoto.hidden=!d.foto;photoInput.value="";
      
      document.querySelector(".overview-title-group h2").textContent="Olá, "+d.nome.split(/\s+/)[0]+"!";
    }catch(x){feedback.textContent=x.message;}
  });
  if(deleteBtn)deleteBtn.addEventListener("click",async()=>{if(prompt("Digite EXCLUIR para confirmar a exclusão permanente.")!=="EXCLUIR")return;const f=new FormData();f.append("csrf_token",document.querySelector('#profileForm [name="csrf_token"]').value);f.append("acao","excluir_conta");f.append("confirmacao","EXCLUIR");try{const r=await fetch("../pages/atualizar-conta.php",{method:"POST",body:f}),d=await r.json();if(!r.ok||!d.sucesso)throw Error(d.erro);location.href=d.redirecionar;}catch(x){alert(x.message);}});


  const openAllTransactionsModal = document.getElementById("openAllTransactionsModal");
  const closeAllTransactionsModal = document.getElementById("closeAllTransactionsModal");
  const allTransactionsModal = document.getElementById("allTransactionsModal");
  const allTransactionsSearch=document.getElementById("allTransactionsSearch");
  if(allTransactionsSearch&&allTransactionsModal){
    const transactionList=allTransactionsModal.querySelector(".all-transactions-list");
    const transactionItems=[...allTransactionsModal.querySelectorAll(".all-transactions-list .transaction-item")];
    const noTransactionResults=document.createElement("p");
    noTransactionResults.className="all-transactions-no-results";
    noTransactionResults.textContent="Nenhuma transa\u00e7\u00e3o encontrada.";
    transactionList?.after(noTransactionResults);
    const normalizeSearch=value=>value.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLocaleLowerCase("pt-BR");
    allTransactionsSearch.addEventListener("input",()=>{
      const query=normalizeSearch(allTransactionsSearch.value.trim());
      let visibleCount=0;
      transactionItems.forEach(item=>{
        const content=normalizeSearch(`${item.textContent} ${item.dataset.transactionType||""}`);
        const matches=content.includes(query);
        const wasHidden=item.style.display==="none";
        item.style.display=matches?"":"none";
        if(matches&&wasHidden&&typeof item.animate==="function"&&!window.matchMedia("(prefers-reduced-motion: reduce)").matches){
          item.animate([{opacity:0,transform:"translateY(5px)"},{opacity:1,transform:"translateY(0)"}],{duration:170,easing:"ease-out"});
        }
        if(matches)visibleCount++;
      });
      if(noTransactionResults)noTransactionResults.style.display=query&&visibleCount===0?"block":"none";
    });
  }
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

  const openManualTransactionOption = document.getElementById("openManualTransactionOption");
  const closeManualTransactionModal = document.getElementById("closeManualTransactionModal");
  const cancelManualTransactionModal = document.getElementById("cancelManualTransactionModal");
  const manualTransactionModal = document.getElementById("manualTransactionModal");
  const openImportTransactionOption = document.getElementById("openImportTransactionOption");
  const closeImportTransactionModal = document.getElementById("closeImportTransactionModal");
  const cancelImportTransactionModal = document.getElementById("cancelImportTransactionModal");
  const importTransactionModal = document.getElementById("importTransactionModal");
  const importTransactionForm = document.getElementById("importTransactionForm");
  const submitImportTransaction = document.getElementById("submitImportTransaction");
  const importFileInput = document.getElementById("importFile");
  const importFileStatus = document.getElementById("importFileStatus");

  const tipoDespesaRadio = document.getElementById("tipoDespesa");
  const tipoReceitaRadio = document.getElementById("tipoReceita");
  const categoriaDespesaWrapper = document.getElementById("categoriaDespesaWrapper");
  const categoriaReceitaWrapper = document.getElementById("categoriaReceitaWrapper");
  const categoriaReceitaSelect = document.getElementById("categoriaReceitaSelect");
  const categoriaDespesaSelect = document.getElementById("categoriaDespesaSelect");
  const manualValorInput = document.getElementById("manualValor");

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
      hasClassSafe(categoryPeriodModal, "active") ||
      hasClassSafe(manualTransactionModal, "active") ||
      hasClassSafe(importTransactionModal, "active");

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
      group.classList.remove("is-active");
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

    targetSection.classList.add("is-active");
    if (trigger) trigger.classList.add("active");
    if (content) content.classList.add("open");

    activateSidebarItemById(groupId);
    const settingsSaveButton = settingsModal?.querySelector(".settings-modal__footer .settings-footer-btn--primary");
    if (settingsSaveButton) settingsSaveButton.hidden = groupId === "settings-account";

    if (scrollToSection) {
      // Role somente o conteúdo, preservando cabeçalho, menu e rodapé.
      targetSection.closest(".settings-content").scrollTop = 0;
    }
  }

  function formatBRL(value) {
    return formatDatabaseBRL(value);
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    })[character]);
  }

  function getGoalColorClass(color) {
    return {
      green: "green",
      blue: "blue",
      orange: "orange",
      purple: "purple"
    }[color] || "green";
  }


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


  let categoryActivePeriod = { periodo: "this-month", inicio: "", fim: "" };

  async function renderCategoryExpenses(periodKey, inicio = "", fim = "") {
    const panelList = document.getElementById("categoryExpensesList");
    if (!panelList) return;

    try {
      const parametros = new URLSearchParams({ periodo: periodKey });
      if (periodKey === "custom") {
        parametros.set("inicio", inicio);
        parametros.set("fim", fim);
      }
      const response = await fetch(`buscar-gastos-categoria.php?${parametros.toString()}`);
      const resultado = await response.json();

      if (!resultado.sucesso) return;

      categoryActivePeriod = { periodo: periodKey, inicio, fim };

      categoryPeriodLabel.textContent = resultado.label;
      const categoryPeriodDescription = document.getElementById("categoryPeriodDescription");
      if (categoryPeriodDescription) {
        categoryPeriodDescription.textContent = `${resultado.intervalo} · somente despesas aprovadas`;
      }

      if (!resultado.categorias.length) {
        panelList.innerHTML = `<p style="padding: 16px 0; color: #888;">Nenhum gasto registrado neste período ainda.</p>`;
      } else {
        panelList.innerHTML = resultado.categorias.map((item) => `
          <article class="category-expense-card" data-category-id="${Number(item.id) || 0}">
            <div class="category-expense-card__top">
              <div class="category-expense-card__left">
                <div class="category-expense-card__icon category-expense-card__icon--${item.cor}">
                  <i class="bi bi-${item.icone}"></i>
                </div>
                <div class="category-expense-card__info">
                  <h4>${escapeHtml(item.nome)}</h4>
                  <p>Total de gastos nesta categoria no período</p>
                </div>
              </div>

              <div class="category-expense-card__right">
                <div class="category-expense-card__numbers">
                  <strong>${formatBRL(item.valor)}</strong>
                  <span>${item.percent}%</span>
                </div>
                <button class="category-expense-card__details-toggle" type="button" aria-expanded="false" aria-label="Ver movimentações de ${escapeHtml(item.nome)}">
                  <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <div class="category-expense-card__bar">
              <div class="category-expense-card__fill category-expense-card__fill--${item.cor}" style="width: ${item.percent}%;"></div>
            </div>
            <div class="category-expense-card__details" hidden></div>
          </article>
        `).join("");
      }
    } catch (error) {
      console.error("Não foi possível carregar os gastos por categoria:", error);
    }

    document.querySelectorAll(".dashboard-period-option").forEach((btn) => {
      btn.classList.toggle("active", btn.getAttribute("data-period") === periodKey);
    });
  }

  const categoryExpensesList = document.getElementById("categoryExpensesList");
  if (categoryExpensesList) {
    categoryExpensesList.addEventListener("click", async (event) => {
      const toggle = event.target.closest(".category-expense-card__details-toggle");
      if (!toggle) return;

      const card = toggle.closest(".category-expense-card");
      const details = card?.querySelector(".category-expense-card__details");
      if (!card || !details) return;

      const wasExpanded = toggle.getAttribute("aria-expanded") === "true";
      categoryExpensesList.querySelectorAll(".category-expense-card__details-toggle").forEach((button) => {
        button.setAttribute("aria-expanded", "false");
        button.innerHTML = '<i class="bi bi-chevron-down" aria-hidden="true"></i>';
        const otherDetails = button.closest(".category-expense-card")?.querySelector(".category-expense-card__details");
        if (otherDetails) otherDetails.hidden = true;
      });

      if (wasExpanded) return;

      toggle.setAttribute("aria-expanded", "true");
      toggle.innerHTML = '<i class="bi bi-chevron-up" aria-hidden="true"></i>';
      details.hidden = false;

      if (details.dataset.loaded === "true") return;

      details.innerHTML = '<p class="category-expense-card__details-status">Carregando movimentações...</p>';

      try {
        const parametros = new URLSearchParams({
          categoria_id: card.dataset.categoryId,
          periodo: categoryActivePeriod.periodo
        });
        if (categoryActivePeriod.periodo === "custom") {
          parametros.set("inicio", categoryActivePeriod.inicio);
          parametros.set("fim", categoryActivePeriod.fim);
        }

        const response = await fetch(`buscar-transacoes-categoria.php?${parametros.toString()}`);
        const resultado = await response.json();
        if (!resultado.sucesso) throw new Error(resultado.mensagem || "Não foi possível carregar as movimentações.");

        details.innerHTML = resultado.transacoes.length
          ? resultado.transacoes.map((transacao) => `
              <div class="category-expense-card__transaction">
                <span>
                  <strong>${escapeHtml(transacao.descricao)}</strong>
                  <small>${escapeHtml(transacao.data_formatada)}</small>
                </span>
                <b>${formatBRL(Number(transacao.valor))}</b>
              </div>
            `).join("")
          : '<p class="category-expense-card__details-status">Nenhuma movimentação neste período.</p>';
        details.dataset.loaded = "true";
      } catch (error) {
        details.innerHTML = '<p class="category-expense-card__details-status">Não foi possível carregar as movimentações.</p>';
        console.error("Não foi possível carregar as movimentações da categoria:", error);
      }
    });
  }

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
      // Recarrega a página para buscar novamente as metas gravadas no banco.
      window.location.reload();
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

  const requestedSettingsSection=new URLSearchParams(window.location.search).get("abrir");
  if(requestedSettingsSection==="configuracoes"||requestedSettingsSection==="perfil"){
    openModal(settingsModal);
    openSettingsSection(requestedSettingsSection==="perfil"?"settings-account":"settings-notifications",false);
    window.history.replaceState({},document.title,window.location.pathname);
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
      const content = group ? group.querySelector(".settings-group__content") : null;
      const isOpen = trigger.classList.contains("active") && content?.classList.contains("open");

      if (isOpen) {
        trigger.classList.remove("active");
        content.classList.remove("open");
        return;
      }

      const groupId = group ? group.getAttribute("id") : null;
      if (groupId) openSettingsSection(groupId, false);
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

  const customPeriodFields = document.getElementById("customPeriodFields");
  const customPeriodStart = document.getElementById("customPeriodStart");
  const customPeriodEnd = document.getElementById("customPeriodEnd");
  const customPeriodError = document.getElementById("customPeriodError");
  const applyCustomPeriod = document.getElementById("applyCustomPeriod");
  const formatarDataLocal = (data) => {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, "0");
    const dia = String(data.getDate()).padStart(2, "0");
    return `${ano}-${mes}-${dia}`;
  };
  const hojeParaFiltro = formatarDataLocal(new Date());

  if (customPeriodStart && customPeriodEnd) {
    customPeriodStart.max = hojeParaFiltro;
    customPeriodEnd.max = hojeParaFiltro;
    const primeiroDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    customPeriodStart.value = formatarDataLocal(primeiroDiaMes);
    customPeriodEnd.value = hojeParaFiltro;
  }

  document.querySelectorAll(".dashboard-period-option").forEach((button) => {
    button.addEventListener("click", () => {
      const period = button.getAttribute("data-period");

      if (period === "custom") {
        if (customPeriodFields) customPeriodFields.hidden = false;
        if (customPeriodError) customPeriodError.textContent = "";
        return;
      }

      if (customPeriodFields) customPeriodFields.hidden = true;
      renderCategoryExpenses(period);
      closeModal(categoryPeriodModal);
    });
  });

  if (applyCustomPeriod) {
    applyCustomPeriod.addEventListener("click", () => {
      const inicio = customPeriodStart ? customPeriodStart.value : "";
      const fim = customPeriodEnd ? customPeriodEnd.value : "";

      if (!inicio || !fim) {
        if (customPeriodError) customPeriodError.textContent = "Informe as duas datas para continuar.";
        return;
      }
      if (inicio > fim) {
        if (customPeriodError) customPeriodError.textContent = "A data inicial deve ser anterior à data final.";
        return;
      }
      if (fim > hojeParaFiltro) {
        if (customPeriodError) customPeriodError.textContent = "A data final não pode passar de hoje.";
        return;
      }

      if (customPeriodFields) customPeriodFields.hidden = true;
      renderCategoryExpenses("custom", inicio, fim);
      closeModal(categoryPeriodModal);
    });
  }

  renderCategoryExpenses("this-month");

  if (openManualTransactionOption) {
    openManualTransactionOption.addEventListener("click", () => {
      closeModal(transactionModal);
      openModal(manualTransactionModal);
    });
  }

  if (openImportTransactionOption) {
    openImportTransactionOption.addEventListener("click", () => {
      closeModal(transactionModal);
      openModal(importTransactionModal);
    });
  }

  if (closeManualTransactionModal) {
    closeManualTransactionModal.addEventListener("click", () => closeModal(manualTransactionModal));
  }

  if (cancelManualTransactionModal) {
    cancelManualTransactionModal.addEventListener("click", () => closeModal(manualTransactionModal));
  }

  if (manualTransactionModal) {
    manualTransactionModal.addEventListener("click", (e) => {
      if (e.target === manualTransactionModal) closeModal(manualTransactionModal);
    });
  }

  if (closeImportTransactionModal) {
    closeImportTransactionModal.addEventListener("click", () => closeModal(importTransactionModal));
  }

  if (cancelImportTransactionModal) {
    cancelImportTransactionModal.addEventListener("click", () => closeModal(importTransactionModal));
  }

  if (importTransactionModal) {
    importTransactionModal.addEventListener("click", (e) => {
      if (e.target === importTransactionModal) closeModal(importTransactionModal);
    });
  }

  if (importFileInput && importFileStatus) {
    importFileInput.addEventListener("change", () => {
      const arquivo = importFileInput.files && importFileInput.files[0];
      const campoArquivo = importFileInput.closest(".import-file-field");

      importFileStatus.textContent = arquivo
        ? arquivo.name
        : "Nenhum arquivo selecionado";
      campoArquivo?.classList.toggle("has-file", Boolean(arquivo));
    });
  }

  if (importTransactionForm && submitImportTransaction) {
    importTransactionForm.addEventListener("submit", () => {
      submitImportTransaction.disabled = true;
      submitImportTransaction.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Importando...';
    });
  }


  function toggleCategoriaWrapper() {
    if (!tipoDespesaRadio || !categoriaDespesaWrapper || !categoriaReceitaWrapper) return;

    const isDespesa = tipoDespesaRadio.checked;
    categoriaDespesaWrapper.classList.toggle("d-none", !isDespesa);
    categoriaReceitaWrapper.classList.toggle("d-none", isDespesa);


    if (isDespesa) {
      if (categoriaReceitaSelect) categoriaReceitaSelect.value = "";
    } else {
      if (categoriaDespesaSelect) categoriaDespesaSelect.value = "";
    }
  }

  if (tipoDespesaRadio && tipoReceitaRadio) {
    tipoDespesaRadio.addEventListener("change", toggleCategoriaWrapper);
    tipoReceitaRadio.addEventListener("change", toggleCategoriaWrapper);
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal(transactionModal);
      closeModal(balanceModal);
      closeModal(aiModal);
      closeModal(settingsModal);
      closeModal(allTransactionsModal);
      closeModal(goalsMenuModal);
      closeModal(categoryPeriodModal);
      closeModal(manualTransactionModal);
      closeModal(importTransactionModal);
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

    let formatado = amount.toFixed(2).replace(".", ",");
    formatado = formatado.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return "R$ " + formatado;
  }

  if (saldoEditInput) {
    saldoEditInput.addEventListener("input", () => {
      saldoEditInput.value = formatCurrencyBRL(saldoEditInput.value);
    });

    saldoEditInput.addEventListener("focus", () => {
      saldoEditInput.value = formatCurrencyBRL(saldoEditInput.value);
    });
  }


  if (manualValorInput) {
    manualValorInput.addEventListener("input", () => {
      manualValorInput.value = formatCurrencyBRL(manualValorInput.value);
    });

    manualValorInput.addEventListener("focus", () => {
      manualValorInput.value = formatCurrencyBRL(manualValorInput.value);
    });
  }

  if (openBalanceModal) {
    openBalanceModal.addEventListener("click", () => {
      const currentValue = saldoCardValue ? Number(saldoCardValue.dataset.countUp||0) : 0;
      if (saldoEditInput) {
        saldoEditInput.value = formatCurrencyBRL(String(Math.round(currentValue*100)));
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


  function parseCurrencyToNumber(value) {
  const digits = getDigitsOnly(value);
  return digits ? Number(digits) / 100 : 0;
}

if (balanceForm) {
  balanceForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const digitado = parseCurrencyToNumber(saldoEditInput ? saldoEditInput.value : "0");
    const valorNumerico = digitado;
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
          saldoCardValue.textContent = formatBRL(Number(resultado.novo_saldo));
          saldoCardValue.dataset.countUp = String(valorNumerico);
          const incomeUsageProgress = document.getElementById("incomeUsageProgress");
          const incomeUsagePercent = document.getElementById("incomeUsagePercent");
          if (incomeUsageProgress && incomeUsagePercent) {
            const monthExpenses = Number(incomeUsageProgress.dataset.gastosMes || 0);
            const currentBalance = valorNumerico;
            const totalAvailable = currentBalance + monthExpenses;
            const percentage = totalAvailable > 0 ? monthExpenses / totalAvailable * 100 : ((currentBalance < 0 || monthExpenses > 0) ? null : 0);
            const fill = incomeUsageProgress.querySelector(".finance-card__progress-fill");
            incomeUsagePercent.textContent = percentage === null ? "Saldo negativo" : Math.round(percentage) + "%";
            incomeUsageProgress.setAttribute("aria-valuenow", (percentage === null ? 100 : Math.min(100, percentage)).toFixed(1));
            incomeUsageProgress.setAttribute("aria-valuetext", (percentage === null ? "Saldo negativo; " : percentage.toFixed(1) + "% gasto; ") + "gastos do mes: " + formatCurrencyBRL(String(Math.round(monthExpenses * 100))) + "; saldo atual: " + formatCurrencyBRL(String(Math.round(valorNumerico * 100))));
            if (fill) fill.style.width = (percentage === null ? 100 : Math.min(100, percentage)).toFixed(2) + "%";
          }
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

/* =========================================================
   NOTIFICAÇÕES
   ========================================================= */

const notifTabs =
    document.querySelectorAll(".notif-tab");


/* FILTROS */

notifTabs.forEach((tab) => {

    tab.addEventListener("click", () => {

        const filtro =
            tab.getAttribute("data-filter");

        notifTabs.forEach((item) => {
            item.classList.remove("active");
        });

        tab.classList.add("active");

        document.querySelectorAll(".notif-card").forEach((card) => {

            const tipo =
                card.getAttribute("data-tipo");

            if (
                filtro === "todos" ||
                tipo === filtro
            ) {

                card.style.display = "";

            } else {

                card.style.display = "none";

            }

        });

    });

});


function animateFinanceCardValues() {
  const counters = Array.from(document.querySelectorAll(".finance-card__value[data-count-up]"));
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!counters.length || reduceMotion) return;

  const formatter = {format: value => formatBRL(value)};
  const duration = 1200;
  const startedAt = performance.now();

  counters.forEach((counter) => {
    const target = Number(counter.dataset.countUp);
    if (Number.isFinite(target)) counter.textContent = formatter.format(0);
  });

  function frame(now) {
    const linearProgress = Math.min((now - startedAt) / duration, 1);
    const easedProgress = 1 - Math.pow(1 - linearProgress, 3);

    counters.forEach((counter) => {
      const target = Number(counter.dataset.countUp);
      if (!Number.isFinite(target)) return;
      const current = target * easedProgress;
      counter.textContent = formatter.format(Math.abs(current) < 0.005 ? 0 : current);
    });

    if (linearProgress < 1) requestAnimationFrame(frame);
  }

  requestAnimationFrame(frame);
}

animateFinanceCardValues();

const financeMiniChartButtons = document.querySelectorAll(".finance-card__mini-hit");
function closeFinanceMiniChartDetails() {
  financeMiniChartButtons.forEach((button) => {
    button.classList.remove("is-active");
    button.setAttribute("aria-expanded", "false");
  });
}

financeMiniChartButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const shouldOpen = !button.classList.contains("is-active");
    closeFinanceMiniChartDetails();

    if (shouldOpen) {
      button.classList.add("is-active");
      button.setAttribute("aria-expanded", "true");
    }
  });
});

document.addEventListener("click", (event) => {
  if (!event.target.closest(".finance-card__mini-hit")) {
    closeFinanceMiniChartDetails();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") closeFinanceMiniChartDetails();
});

</script>




  <script src="../assets/js/page-entrance.js?v=3" defer></script>
  <script src="../assets/js/notifications.js?v=1" defer></script>
  <script src="../assets/js/ai-assistant.js?v=1" defer></script>
</body>
</html>


