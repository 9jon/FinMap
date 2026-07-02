<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Origem dos Dados - FinMap</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <link rel="stylesheet" href="./FinMap_Projeto/css/fonte-dados.css">
</head>
<body>

  <main class="data-source-page">
    <section class="data-source-shell container">

      <div class="topbar">
        <a href="index.html" class="brand">
          <span class="brand-mark"></span>
          <span class="brand-text">FinMap</span>
        </a>

        <div class="step-pill">Etapa 2 de 2</div>
      </div>

      <div class="hero-card">
        <div class="hero-glow hero-glow-1"></div>
        <div class="hero-glow hero-glow-2"></div>

        <div class="hero-content">
          <span class="mini-badge">Configuração inteligente</span>
          <h1>Como você quer alimentar o FinMap?</h1>
          <p>
            Escolha a forma mais prática para trazer seus dados financeiros.
            Você poderá alterar isso depois dentro da plataforma.
          </p>
        </div>

        <form id="fonteDadosForm">
          <div class="row g-4 cards-grid">

            <div class="col-12 col-lg-4">
              <label class="option-card w-100">
                <input type="radio" name="fonteDados" value="api-bancaria">

                <div class="option-card-content">
                  <div class="option-top">
                    <div class="option-icon">
                      <i class="bi bi-bank2"></i>
                    </div>
                    <span class="option-tag">Automático</span>
                  </div>

                  <div class="option-text">
                    <h3>API bancária</h3>
                    <p>
                      Conecte sua conta para importar movimentações e extratos automaticamente.
                    </p>
                  </div>

                  <ul class="option-list">
                    <li>Leitura automática do extrato</li>
                    <li>Mais agilidade no acompanhamento</li>
                    <li>Ideal para uso contínuo</li>
                  </ul>
                </div>
              </label>
            </div>

            <div class="col-12 col-lg-4">
              <label class="option-card w-100">
                <input type="radio" name="fonteDados" value="ocr-notas">

                <div class="option-card-content">
                  <div class="option-top">
                    <div class="option-icon">
                      <i class="bi bi-receipt-cutoff"></i>
                    </div>
                    <span class="option-tag">Inteligente</span>
                  </div>

                  <div class="option-text">
                    <h3>OCR de notas fiscais</h3>
                    <p>
                      Envie comprovantes e notas para que o sistema leia e registre os gastos.
                    </p>
                  </div>

                  <ul class="option-list">
                    <li>Captura por imagem ou arquivo</li>
                    <li>Leitura inteligente dos dados</li>
                    <li>Bom para controle de compras</li>
                  </ul>
                </div>
              </label>
            </div>

            <div class="col-12 col-lg-4">
              <label class="option-card w-100">
                <input type="radio" name="fonteDados" value="manual">

                <div class="option-card-content">
                  <div class="option-top">
                    <div class="option-icon">
                      <i class="bi bi-pencil-square"></i>
                    </div>
                    <span class="option-tag">Flexível</span>
                  </div>

                  <div class="option-text">
                    <h3>Lançamento manual</h3>
                    <p>
                      Cadastre receitas e despesas manualmente para ter total controle desde o início.
                    </p>
                  </div>

                  <ul class="option-list">
                    <li>Mais liberdade de organização</li>
                    <li>Ideal para começar rapidamente</li>
                    <li>Sem precisar conectar contas</li>
                  </ul>
                </div>
              </label>
            </div>

          </div>

          <div id="selectedInfo" class="selected-info d-none">
            <div class="selected-info-box">
              <strong>Opção selecionada:</strong>
              <span id="selectedLabel">—</span>
            </div>
          </div>

          <div class="actions">
            <a href="config-renda.html" class="ghost-btn">Voltar</a>
            <button type="submit" class="primary-btn">Continuar</button>
          </div>
        </form>
      </div>

    </section>
  </main>

  <script>
    const radios = document.querySelectorAll('input[name="fonteDados"]');
    const selectedInfo = document.getElementById("selectedInfo");
    const selectedLabel = document.getElementById("selectedLabel");

    const labels = {
      "api-bancaria": "API bancária",
      "ocr-notas": "OCR de notas fiscais",
      "manual": "Lançamento manual"
    };

    radios.forEach((radio) => {
      radio.addEventListener("change", function () {
        selectedInfo.classList.remove("d-none");
        selectedLabel.textContent = labels[this.value];
      });
    });

    document.getElementById("fonteDadosForm").addEventListener("submit", function (e) {
      e.preventDefault();

      const selected = document.querySelector('input[name="fonteDados"]:checked');

      if (!selected) {
        alert("Selecione uma opção para continuar.");
        return;
      }

      window.location.href = "dashboard.html";
    });
  </script>

</body>
</html>