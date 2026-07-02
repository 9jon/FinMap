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

  <link rel="stylesheet" href="./FinMap_Projeto/css/config-renda.css">
</head>
<body>

  <main class="income-page">
    <section class="income-shell">

      <div class="income-topbar">
        <a href="index.html" class="brand">
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

        <form id="rendaForm" class="income-form">

          <div class="main-income-block">
            <label for="rendaMensal" class="main-income-label">Qual é sua renda principal mensal?</label>
            <input
              type="text"
              id="rendaMensal"
              name="rendaMensal"
              class="form-control main-income-input money"
              placeholder="R$ 0,00"
            >
            <span class="field-helper">Informe o valor da sua renda mensal.</span>
          </div>

          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label-custom">Tipo de vínculo</label>

              <div class="dropdown w-100 custom-dropdown">
                <button class="btn dropdown-toggle custom-dropdown-btn w-100" type="button" data-bs-toggle="dropdown">
                  Selecione
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

                <input type="hidden" name="tipoRenda" id="tipoRenda">
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-custom">Tipo de renda</label>

              <div class="dropdown w-100 custom-dropdown">
                <button class="btn dropdown-toggle custom-dropdown-btn w-100" type="button" data-bs-toggle="dropdown">
                  Selecione
                </button>

                <ul class="dropdown-menu w-100 custom-dropdown-menu">
                  <li><a class="dropdown-item" href="#" data-value="fixa">Fixa</a></li>
                  <li><a class="dropdown-item" href="#" data-value="variavel">Variável</a></li>
                </ul>

                <input type="hidden" name="tipoRecebimento" id="tipoRecebimento">
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
                <input type="radio" name="rendaExtra" value="nao">
                <div class="choice-card-content">
                  <strong>Não</strong>
                  <span>Tenho apenas minha renda principal.</span>
                </div>
              </label>

              <label class="choice-card">
                <input type="radio" name="rendaExtra" value="sim">
                <div class="choice-card-content">
                  <strong>Sim</strong>
                  <span>Também recebo valores por fora ou extras.</span>
                </div>
              </label>
            </div>
          </div>

          <div id="extraIncomeFields" class="row g-4 extra-fields d-none">
            <div class="col-md-6">
              <label for="valorExtra" class="form-label-custom">Valor médio da renda extra</label>
              <input
                type="text"
                id="valorExtra"
                name="valorExtra"
                class="form-control custom-input-finmap money"
                placeholder="R$ 0,00"
              >
            </div>

            <div class="col-md-6">
              <label class="form-label-custom">Origem da renda extra</label>

              <div class="dropdown w-100 custom-dropdown">
                <button class="btn dropdown-toggle custom-dropdown-btn w-100" type="button" data-bs-toggle="dropdown">
                  Selecione
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

                <input type="hidden" name="origemExtra" id="origemExtra">
              </div>
            </div>
          </div>

          <div class="form-actions">
            <a href="dashboard.html" class="ghost-btn">Pular por enquanto</a>
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

    function preencherDadosSalvos() {
      const dadosSalvos = JSON.parse(localStorage.getItem("finmapConfiguracaoRenda"));

      if (!dadosSalvos) return;

      document.getElementById("rendaMensal").value = dadosSalvos.rendaMensal || "";
      document.getElementById("tipoRenda").value = dadosSalvos.tipoRenda || "";
      document.getElementById("tipoRecebimento").value = dadosSalvos.tipoRecebimento || "";
      document.getElementById("diaRecebimento").value = dadosSalvos.diaRecebimento || "";
      document.getElementById("saldoAtual").value = dadosSalvos.saldoAtual || "";
      document.getElementById("valorExtra").value = dadosSalvos.valorExtra || "";
      document.getElementById("origemExtra").value = dadosSalvos.origemExtra || "";

      if (dadosSalvos.rendaExtra) {
        const radio = document.querySelector(`input[name="rendaExtra"][value="${dadosSalvos.rendaExtra}"]`);
        if (radio) {
          radio.checked = true;

          if (dadosSalvos.rendaExtra === "sim") {
            extraFields.classList.remove("d-none");
          }
        }
      }

      document.querySelectorAll(".custom-dropdown").forEach(dropdown => {
        const button = dropdown.querySelector(".dropdown-toggle");
        const hiddenInput = dropdown.querySelector("input[type='hidden']");
        const selectedItem = dropdown.querySelector(`.dropdown-item[data-value="${hiddenInput.value}"]`);

        if (selectedItem) {
          button.innerText = selectedItem.innerText;
        }
      });
    }

    document.getElementById("rendaForm").addEventListener("submit", function(e){
      e.preventDefault();

      const rendaMensal = document.getElementById("rendaMensal").value.trim();
      const tipoRenda = document.getElementById("tipoRenda").value.trim();
      const tipoRecebimento = document.getElementById("tipoRecebimento").value.trim();
      const diaRecebimento = document.getElementById("diaRecebimento").value.trim();
      const saldoAtual = document.getElementById("saldoAtual").value.trim();
      const rendaExtraSelecionada = document.querySelector('input[name="rendaExtra"]:checked');

      if (!rendaMensal || parseBRL(rendaMensal) <= 0) {
        alert("Informe sua renda principal mensal.");
        return;
      }

      if (!tipoRenda) {
        alert("Selecione o tipo de vínculo.");
        return;
      }

      if (!tipoRecebimento) {
        alert("Selecione o tipo de renda.");
        return;
      }

      if (!diaRecebimento || Number(diaRecebimento) < 1 || Number(diaRecebimento) > 31) {
        alert("Informe um dia de recebimento válido entre 1 e 31.");
        return;
      }

      if (!saldoAtual || parseBRL(saldoAtual) < 0) {
        alert("Informe o valor que você tem na conta neste momento.");
        return;
      }

      if (!rendaExtraSelecionada) {
        alert("Selecione se você possui renda extra.");
        return;
      }

      const rendaExtra = rendaExtraSelecionada.value;
      const valorExtra = document.getElementById("valorExtra").value.trim();
      const origemExtra = document.getElementById("origemExtra").value.trim();

      if (rendaExtra === "sim") {
        if (!valorExtra || parseBRL(valorExtra) <= 0) {
          alert("Informe o valor médio da renda extra.");
          return;
        }

        if (!origemExtra) {
          alert("Selecione a origem da renda extra.");
          return;
        }
      }

      const dadosConfiguracaoRenda = {
        rendaMensal,
        rendaMensalNumero: parseBRL(rendaMensal),
        tipoRenda,
        tipoRecebimento,
        diaRecebimento,
        saldoAtual,
        saldoAtualNumero: parseBRL(saldoAtual),
        rendaExtra,
        valorExtra: rendaExtra === "sim" ? valorExtra : "",
        valorExtraNumero: rendaExtra === "sim" ? parseBRL(valorExtra) : 0,
        origemExtra: rendaExtra === "sim" ? origemExtra : ""
      };

      localStorage.setItem("finmapConfiguracaoRenda", JSON.stringify(dadosConfiguracaoRenda));

      window.location.href = "fonte-dados.html";
    });

    preencherDadosSalvos();
  </script>

</body>
</html>