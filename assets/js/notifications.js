(() => {
  const bell = document.querySelector(".notification-btn");
  const panel = document.getElementById("notifPanel");
  const list = panel?.querySelector(".notif-panel__list");
  if (!bell || !panel || !list) return;

  const endpoint = "buscar-notificacao.php";
  const markEndpoint = "marcar-notificacao.php";
  let loading = false;

  function updateDot(count) {
    let dot = bell.querySelector(".notification-dot");
    if (count > 0 && !dot) {
      dot = document.createElement("span");
      dot.className = "notification-dot";
      bell.appendChild(dot);
    } else if (count <= 0 && dot) {
      dot.remove();
    }
  }

  function formatDate(value) {
    if (!value) return "";
    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "";
    return new Intl.DateTimeFormat("pt-BR", {
      dateStyle: "short",
      timeStyle: "short"
    }).format(date);
  }

  function renderNotifications(items) {
    list.replaceChildren();
    if (!items?.length) {
      const empty = document.createElement("div");
      empty.className = "notif-empty";
      const title = document.createElement("h4");
      title.textContent = "Tudo em dia";
      const message = document.createElement("p");
      message.textContent = "Quando surgir algo importante nas suas finanças, você encontra aqui.";
      empty.append(title, message);
      list.appendChild(empty);
      return;
    }

    const styles = {
      meta: ["success", "bullseye"],
      gasto: ["warning", "receipt"],
      ia: ["info", "stars"]
    };
    items.forEach((item) => {
      const [kind, icon] = styles[item.categoria] || ["danger", "exclamation-triangle"];
      const card = document.createElement("article");
      card.className = `notif-card notif-card--${kind}${item.lida ? " is-read" : ""}`;
      card.dataset.id = String(item.id);
      card.dataset.tipo = item.categoria || "";
      card.dataset.lida = String(item.lida ? 1 : 0);

      const iconWrap = document.createElement("div");
      iconWrap.className = "notif-card__icon";
      const iconElement = document.createElement("i");
      iconElement.className = `bi bi-${icon}`;
      iconWrap.appendChild(iconElement);

      const content = document.createElement("div");
      content.className = "notif-card__content";
      const title = document.createElement("h4");
      title.textContent = item.titulo || "Notificação";
      const message = document.createElement("p");
      message.textContent = item.mensagem || "";
      const date = document.createElement("span");
      date.textContent = formatDate(item.criado_em);
      content.append(title, message, date);
      card.append(iconWrap, content);
      list.appendChild(card);
    });
  }

  async function refreshNotifications() {
    if (loading) return;
    loading = true;
    try {
      const response = await fetch(endpoint, { headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      if (!data.sucesso) return;
      updateDot(Number(data.nao_lidas) || 0);
      renderNotifications(data.notificacoes);
    } catch (error) {
      console.error("Erro ao carregar notificações:", error);
    } finally {
      loading = false;
    }
  }

  bell.addEventListener("click", refreshNotifications);
  refreshNotifications();

  list.addEventListener("click", async (event) => {
    const card = event.target.closest(".notif-card[data-id]");
    if (!card || card.dataset.lida === "1") return;
    const form = new FormData();
    form.append("id", card.dataset.id);
    try {
      const response = await fetch(markEndpoint, { method: "POST", body: form });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const result = await response.json();
      if (!result.sucesso) return;
      card.dataset.lida = "1";
      card.classList.add("is-read");
      updateDot(Number(result.nao_lidas) || 0);
    } catch (error) {
      console.error("Erro ao marcar notificação como lida:", error);
    }
  });
})();