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
        die('Nenhum usuário de teste encontrado na tabela usuarios. Rode o INSERT do usuário de exemplo (joao@email.com) do schema antes de testar esta tela.');
    }
}

$usuario_id = (int)$_SESSION['usuario_id'];
$erro = '';

function parseBRLParaFloat(string $valor): float
{
    $limpo = str_replace(['R$', ' '], '', $valor);
    $limpo = str_replace('.', '', $limpo);   
    $limpo = str_replace(',', '.', $limpo); 
    return (float) $limpo;
}

$tiposVinculoValidos = ['clt', 'pj', 'autonomo', 'freelancer', 'empresario', 'servidor-publico', 'outro'];
$tiposRecebimentoValidos = ['fixa', 'variavel'];
$origensExtraValidas = ['freelance', 'comissoes', 'investimentos', 'vendas', 'aluguel', 'prestacao-servico', 'outro'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rendaMensal = parseBRLParaFloat($_POST['rendaMensal'] ?? '');
    $tipoVinculo = $_POST['tipoRenda'] ?? '';
    $tipoRecebimento = $_POST['tipoRecebimento'] ?? '';
    $diaRecebimento = (int)($_POST['diaRecebimento'] ?? 0);
    $saldoInicial = parseBRLParaFloat($_POST['saldoAtual'] ?? '');
    $possuiRendaExtra = (($_POST['rendaExtra'] ?? 'nao') === 'sim') ? 1 : 0;
    $valorRendaExtra = parseBRLParaFloat($_POST['valorExtra'] ?? '');
    $origemRendaExtra = $_POST['origemExtra'] ?? '';

    if ($rendaMensal <= 0) {
        $erro = 'Informe sua renda principal mensal.';
    } elseif (!in_array($tipoVinculo, $tiposVinculoValidos, true)) {
        $erro = 'Selecione o tipo de vínculo.';
    } elseif (!in_array($tipoRecebimento, $tiposRecebimentoValidos, true)) {
        $erro = 'Selecione o tipo de renda.';
    } elseif ($diaRecebimento < 1 || $diaRecebimento > 31) {
        $erro = 'Informe um dia de recebimento válido entre 1 e 31.';
    } elseif ($saldoInicial < 0) {
        $erro = 'Informe o valor que você tem na conta neste momento.';
    } elseif ($possuiRendaExtra && $valorRendaExtra <= 0) {
        $erro = 'Informe o valor médio da renda extra.';
    } elseif ($possuiRendaExtra && !in_array($origemRendaExtra, $origensExtraValidas, true)) {
        $erro = 'Selecione a origem da renda extra.';
    }

    if ($erro === '') {
        $origemParaSalvar = $possuiRendaExtra ? $origemRendaExtra : null;
        $valorExtraParaSalvar = $possuiRendaExtra ? $valorRendaExtra : 0;

        $sql = "INSERT INTO configuracoes_renda
                    (usuario_id, renda_mensal, tipo_vinculo, tipo_recebimento, dia_recebimento,
                     saldo_inicial, possui_renda_extra, valor_renda_extra, origem_renda_extra)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    renda_mensal = VALUES(renda_mensal),
                    tipo_vinculo = VALUES(tipo_vinculo),
                    tipo_recebimento = VALUES(tipo_recebimento),
                    dia_recebimento = VALUES(dia_recebimento),
                    saldo_inicial = VALUES(saldo_inicial),
                    possui_renda_extra = VALUES(possui_renda_extra),
                    valor_renda_extra = VALUES(valor_renda_extra),
                    origem_renda_extra = VALUES(origem_renda_extra)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "idssidids",
            $usuario_id,
            $rendaMensal,
            $tipoVinculo,
            $tipoRecebimento,
            $diaRecebimento,
            $saldoInicial,
            $possuiRendaExtra,
            $valorExtraParaSalvar,
            $origemParaSalvar
        );
        $stmt->execute();
        $stmt->close();

        $stmtSaldo = $conn->prepare("UPDATE usuarios SET saldo_total = ? WHERE id = ?");
        $stmtSaldo->bind_param("di", $saldoInicial, $usuario_id);
        $stmtSaldo->execute();
        $stmtSaldo->close();

        header('Location: fonte-dados.php');
        exit;
    }
}


$configuracaoExistente = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sqlBusca = "SELECT * FROM configuracoes_renda WHERE usuario_id = ?";
    $stmtBusca = $conn->prepare($sqlBusca);
    $stmtBusca->bind_param("i", $usuario_id);
    $stmtBusca->execute();
    $configuracaoExistente = $stmtBusca->get_result()->fetch_assoc();
    $stmtBusca->close();
}

function formatarBRL(?float $valor): string
{
    if ($valor === null) return '';
    return 'R$ ' . number_format($valor, 2, ',', '.');
}


$vRendaMensal      = $_POST['rendaMensal']      ?? formatarBRL(isset($configuracaoExistente['renda_mensal']) ? (float)$configuracaoExistente['renda_mensal'] : null);
$vTipoVinculo      = $_POST['tipoRenda']         ?? ($configuracaoExistente['tipo_vinculo'] ?? '');
$vTipoRecebimento  = $_POST['tipoRecebimento']   ?? ($configuracaoExistente['tipo_recebimento'] ?? '');
$vDiaRecebimento   = $_POST['diaRecebimento']    ?? ($configuracaoExistente['dia_recebimento'] ?? '');
$vSaldoAtual       = $_POST['saldoAtual']        ?? formatarBRL(isset($configuracaoExistente['saldo_inicial']) ? (float)$configuracaoExistente['saldo_inicial'] : null);
$vRendaExtra       = $_POST['rendaExtra']         ?? (isset($configuracaoExistente['possui_renda_extra']) ? ($configuracaoExistente['possui_renda_extra'] ? 'sim' : 'nao') : '');
$vValorExtra       = $_POST['valorExtra']         ?? formatarBRL(isset($configuracaoExistente['valor_renda_extra']) ? (float)$configuracaoExistente['valor_renda_extra'] : null);
$vOrigemExtra      = $_POST['origemExtra']        ?? ($configuracaoExistente['origem_renda_extra'] ?? '');


$labelsVinculo = [
    'clt' => 'CLT', 'pj' => 'PJ', 'autonomo' => 'Autônomo', 'freelancer' => 'Freelancer',
    'empresario' => 'Empresário', 'servidor-publico' => 'Servidor público', 'outro' => 'Outro',
];
$labelsRecebimento = ['fixa' => 'Fixa', 'variavel' => 'Variável'];
$labelsOrigemExtra = [
    'freelance' => 'Freelance', 'comissoes' => 'Comissões', 'investimentos' => 'Investimentos',
    'vendas' => 'Vendas', 'aluguel' => 'Aluguel', 'prestacao-servico' => 'Prestação de serviço', 'outro' => 'Outro',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuração de Renda - FinMap</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/config-renda.css">
</head>
<body>

  <main class="income-page">
    <section class="income-shell">

      <div class="income-topbar">
        <a href="../index.php" class="brand">
          <span class="brand-mark"></span>
          <span class="brand-text">FinMap</span>
        </a>

        <div class="topbar-pill">Etapa 1 de 2</div>
      </div>

      <div class="income-card">
        <div class="income-header">
          <div class="header-badge">Configuração Principal</div>
          <h1>Vamos configurar sua renda</h1>
          <p>
            Informe o essencial para que o FinMap personalize sua experiência financeira.
          </p>
        </div>

        <?php if ($erro): ?>
          <div class="alert alert-danger py-2">
            <?= htmlspecialchars($erro) ?>
          </div>
        <?php endif; ?>

        <form id="rendaForm" class="income-form" method="post" action="config-renda.php">

          <div class="main-income-block">
            <label for="rendaMensal" class="main-income-label">Qual é sua renda principal mensal?</label>
            <input
              type="text"
              id="rendaMensal"
              name="rendaMensal"
              class="form-control main-income-input money"
              placeholder="R$ 0,00"
              value="<?= htmlspecialchars($vRendaMensal) ?>"
            >
            <span class="field-helper">Informe o valor da sua renda mensal.</span>
          </div>

          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label-custom">Tipo de vínculo</label>

              <div class="dropdown w-100 custom-dropdown">
                <button class="btn dropdown-toggle custom-dropdown-btn w-100" type="button" data-bs-toggle="dropdown">
                  <?= $vTipoVinculo !== '' ? htmlspecialchars($labelsVinculo[$vTipoVinculo] ?? 'Selecione') : 'Selecione' ?>
                </button>

                <ul class="dropdown-menu w-100 custom-dropdown-menu">
                  <li><a class="dropdown-item" href="#" data-value="clt">CLT</a></li>
                  <li><a class="dropdown-item" href="#" data-value="pj">PJ</a></li>
                  <li><a class="dropdown-item" href="#" data-value="autonomo">Autônomo</a></li>
                  <li><a class="dropdown-item" href="#" data-value="freelancer">Freelancer</a></li>
                  <li><a class="dropdown-item" href="#" data-value="empresario">Empresário</a></li>
                  <li><a class="dropdown-item" href="#" data-value="servidor-publico">Servidor público</a></li>
                  <li><a class="dropdown-item" href="#" data-value="outro">Outro</a></li>
                </ul>

                <input type="hidden" name="tipoRenda" id="tipoRenda" value="<?= htmlspecialchars($vTipoVinculo) ?>">
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-custom">Tipo de renda</label>

              <div class="dropdown w-100 custom-dropdown">
                <button class="btn dropdown-toggle custom-dropdown-btn w-100" type="button" data-bs-toggle="dropdown">
                  <?= $vTipoRecebimento !== '' ? htmlspecialchars($labelsRecebimento[$vTipoRecebimento] ?? 'Selecione') : 'Selecione' ?>
                </button>

                <ul class="dropdown-menu w-100 custom-dropdown-menu">
                  <li><a class="dropdown-item" href="#" data-value="fixa">Fixa</a></li>
                  <li><a class="dropdown-item" href="#" data-value="variavel">Variável</a></li>
                </ul>

                <input type="hidden" name="tipoRecebimento" id="tipoRecebimento" value="<?= htmlspecialchars($vTipoRecebimento) ?>">
              </div>
            </div>
          </div>

          <div class="row g-4 mt-1">
            <div class="col-md-6">
              <label for="diaRecebimento" class="form-label-custom">Que dia você recebe?</label>
              <input
                type="number"
                id="diaRecebimento"
                name="diaRecebimento"
                class="form-control custom-input-finmap"
                min="1"
                max="31"
                placeholder="Ex.: 5"
                value="<?= htmlspecialchars((string)$vDiaRecebimento) ?>"
              >
            </div>

            <div class="col-md-6">
              <label for="saldoAtual" class="form-label-custom">Qual o valor que você tem na conta neste momento?</label>
              <input
                type="text"
                id="saldoAtual"
                name="saldoAtual"
                class="form-control custom-input-finmap money"
                placeholder="R$ 0,00"
                value="<?= htmlspecialchars($vSaldoAtual) ?>"
              >
            </div>
          </div>

          <div class="extra-income-section">
            <div class="section-title-wrap">
              <h2>Você possui renda extra?</h2>
              <span>Selecione a opção que melhor representa sua situação.</span>
            </div>

            <div class="extra-income-cards">
              <label class="choice-card">
                <input type="radio" name="rendaExtra" value="nao" <?= $vRendaExtra === 'nao' ? 'checked' : '' ?>>
                <div class="choice-card-content">
                  <strong>Não</strong>
                  <span>Tenho apenas minha renda principal.</span>
                </div>
              </label>

              <label class="choice-card">
                <input type="radio" name="rendaExtra" value="sim" <?= $vRendaExtra === 'sim' ? 'checked' : '' ?>>
                <div class="choice-card-content">
                  <strong>Sim</strong>
                  <span>Também recebo valores por fora ou extras.</span>
                </div>
              </label>
            </div>
          </div>

          <div id="extraIncomeFields" class="row g-4 extra-fields <?= $vRendaExtra === 'sim' ? '' : 'd-none' ?>">
            <div class="col-md-6">
              <label for="valorExtra" class="form-label-custom">Valor médio da renda extra</label>
              <input
                type="text"
                id="valorExtra"
                name="valorExtra"
                class="form-control custom-input-finmap money"
                placeholder="R$ 0,00"
                value="<?= htmlspecialchars($vValorExtra) ?>"
              >
            </div>

            <div class="col-md-6">
              <label class="form-label-custom">Origem da renda extra</label>

              <div class="dropdown w-100 custom-dropdown">
                <button class="btn dropdown-toggle custom-dropdown-btn w-100" type="button" data-bs-toggle="dropdown">
                  <?= $vOrigemExtra !== '' ? htmlspecialchars($labelsOrigemExtra[$vOrigemExtra] ?? 'Selecione') : 'Selecione' ?>
                </button>

                <ul class="dropdown-menu w-100 custom-dropdown-menu">
                  <li><a class="dropdown-item" href="#" data-value="freelance">Freelance</a></li>
                  <li><a class="dropdown-item" href="#" data-value="comissoes">Comissões</a></li>
                  <li><a class="dropdown-item" href="#" data-value="investimentos">Investimentos</a></li>
                  <li><a class="dropdown-item" href="#" data-value="vendas">Vendas</a></li>
                  <li><a class="dropdown-item" href="#" data-value="aluguel">Aluguel</a></li>
                  <li><a class="dropdown-item" href="#" data-value="prestacao-servico">Prestação de serviço</a></li>
                  <li><a class="dropdown-item" href="#" data-value="outro">Outro</a></li>
                </ul>

                <input type="hidden" name="origemExtra" id="origemExtra" value="<?= htmlspecialchars($vOrigemExtra) ?>">
              </div>
            </div>
          </div>

          <div class="form-actions">
            <a href="dashboard.php" class="ghost-btn">Pular por enquanto</a>
            <button type="submit" class="primary-btn">Salvar e continuar</button>
          </div>

        </form>
      </div>

    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    function formatBRL(value) {
      value = value.replace(/\D/g, "");
      value = (Number(value) / 100).toFixed(2) + "";
      value = value.replace(".", ",");
      value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      return "R$ " + value;
    }

    function parseBRL(value) {
      if (!value) return 0;
      return Number(
        value
          .replace(/[R$\s]/g, "")
          .replace(/\./g, "")
          .replace(",", ".")
      ) || 0;
    }

    document.querySelectorAll(".money").forEach((input) => {
      input.addEventListener("input", function () {
        this.value = formatBRL(this.value);
      });
    });

    document.querySelectorAll(".custom-dropdown").forEach(dropdown => {
      const button = dropdown.querySelector(".dropdown-toggle");
      const hiddenInput = dropdown.querySelector("input[type='hidden']");
      const items = dropdown.querySelectorAll(".dropdown-item");

      items.forEach(item => {
        item.addEventListener("click", function(e) {
          e.preventDefault();
          button.innerText = this.innerText;
          hiddenInput.value = this.dataset.value;
        });
      });
    });

    const radiosRendaExtra = document.querySelectorAll('input[name="rendaExtra"]');
    const extraFields = document.getElementById("extraIncomeFields");

    radiosRendaExtra.forEach((radio) => {
      radio.addEventListener("change", function () {
        if (this.value === "sim") {
          extraFields.classList.remove("d-none");
        } else {
          extraFields.classList.add("d-none");
          document.getElementById("valorExtra").value = "";
          document.getElementById("origemExtra").value = "";

          const origemBtn = document.querySelector("#origemExtra").closest(".custom-dropdown").querySelector(".dropdown-toggle");
          origemBtn.innerText = "Selecione";
        }
      });
    });

    document.getElementById("rendaForm").addEventListener("submit", function (e) {
      const rendaMensal = document.getElementById("rendaMensal").value.trim();
      const tipoRenda = document.getElementById("tipoRenda").value.trim();
      const tipoRecebimento = document.getElementById("tipoRecebimento").value.trim();
      const diaRecebimento = document.getElementById("diaRecebimento").value.trim();
      const saldoAtual = document.getElementById("saldoAtual").value.trim();
      const rendaExtraSelecionada = document.querySelector('input[name="rendaExtra"]:checked');

      if (!rendaMensal || parseBRL(rendaMensal) <= 0) {
        e.preventDefault();
        alert("Informe sua renda principal mensal.");
        return;
      }

      if (!tipoRenda) {
        e.preventDefault();
        alert("Selecione o tipo de vínculo.");
        return;
      }

      if (!tipoRecebimento) {
        e.preventDefault();
        alert("Selecione o tipo de renda.");
        return;
      }

      if (!diaRecebimento || Number(diaRecebimento) < 1 || Number(diaRecebimento) > 31) {
        e.preventDefault();
        alert("Informe um dia de recebimento válido entre 1 e 31.");
        return;
      }

      if (!saldoAtual || parseBRL(saldoAtual) < 0) {
        e.preventDefault();
        alert("Informe o valor que você tem na conta neste momento.");
        return;
      }

      if (!rendaExtraSelecionada) {
        e.preventDefault();
        alert("Selecione se você possui renda extra.");
        return;
      }

      if (rendaExtraSelecionada.value === "sim") {
        const valorExtra = document.getElementById("valorExtra").value.trim();
        const origemExtra = document.getElementById("origemExtra").value.trim();

        if (!valorExtra || parseBRL(valorExtra) <= 0) {
          e.preventDefault();
          alert("Informe o valor médio da renda extra.");
          return;
        }

        if (!origemExtra) {
          e.preventDefault();
          alert("Selecione a origem da renda extra.");
          return;
        }
      }

     
    });
  </script>

</body>
</html>