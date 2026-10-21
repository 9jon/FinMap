(() => {
  const root = document.getElementById("aiModal");
  if (!root) return;

  const csrf = root.dataset.csrf || "";
  const api = "assistente-chat-api.php";
  const history = document.getElementById("aiHistoryList");
  const messages = document.getElementById("aiMessages");
  const form = document.getElementById("aiChatForm");
  const input = document.getElementById("aiChatInput");
  const status = document.getElementById("aiChatStatus");
  const title = document.getElementById("aiCurrentChatTitle");
  const deleteButton = document.getElementById("aiDeleteChat");
  let current = null;
  let busy = false;

  async function call(action, extra = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 155000);
    try {
      const response = await fetch(api, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action, csrf, ...extra }),
        signal: controller.signal
      });
      const data = await response.json();
      if (!response.ok) {
        const error = new Error(data.erro || "Ocorreu um erro.");
        error.id = data.id;
        throw error;
      }
      return data;
    } catch (error) {
      if (error.name === "AbortError") {
        throw new Error("A IA demorou demais para responder. Confira se o serviço está ativo e tente novamente.");
      }
      throw error;
    } finally {
      clearTimeout(timer);
    }
  }

  function addMessage(role, text) {
    const row = document.createElement("div");
    row.className = `ai-message ${role === "assistant" ? "ai-message--bot" : "ai-message--user"}`;
    const bubble = document.createElement("div");
    bubble.className = "ai-message__bubble";
    bubble.textContent = text;
    row.appendChild(bubble);
    messages.appendChild(row);
  }

  function render(items) {
    messages.replaceChildren();
    if (!items.length) {
      const empty = document.createElement("div");
      empty.className = "ai-empty-state";
      const heading = document.createElement("h4");
      heading.textContent = "Como posso ajudar com suas finanças?";
      const description = document.createElement("p");
      description.textContent = "Envie uma pergunta ou escolha uma sugestão. Suas conversas ficam salvas nesta conta.";
      empty.append(heading, description);
      messages.appendChild(empty);
      return;
    }
    items.forEach((item) => addMessage(item.papel, item.conteudo));
    messages.scrollTop = messages.scrollHeight;
  }

  async function refresh() {
    try {
      const data = await call("list");
      history.replaceChildren();
      if (!data.conversas.length) {
        const empty = document.createElement("p");
        empty.className = "ai-history__empty";
        empty.textContent = "Suas conversas salvas aparecerão aqui.";
        history.appendChild(empty);
        return;
      }
      data.conversas.forEach((conversation) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = `ai-history-item${Number(conversation.id) === Number(current) ? " is-active" : ""}`;
        button.textContent = conversation.titulo;
        button.title = conversation.titulo;
        button.addEventListener("click", () => load(conversation.id));
        history.appendChild(button);
      });
    } catch (error) {
      status.textContent = error.message;
    }
  }

  async function load(id) {
    try {
      const data = await call("load", { id });
      current = Number(id);
      title.textContent = data.conversa.titulo;
      deleteButton.hidden = false;
      render(data.mensagens);
      await refresh();
    } catch (error) {
      status.textContent = error.message;
    }
  }

  function newChat() {
    current = null;
    title.textContent = "Nova conversa";
    deleteButton.hidden = true;
    status.textContent = "As respostas usam os dados financeiros registrados no FinMap.";
    render([]);
    input.focus();
    refresh();
  }

  async function send(text) {
    if (busy || !text.trim()) return;
    busy = true;
    input.value = "";
    input.style.height = "";
    status.textContent = "Analisando seus dados... a primeira resposta pode levar alguns segundos.";
    addMessage("user", text);
    const waiting = document.createElement("div");
    waiting.className = "ai-message ai-message--bot";
    waiting.innerHTML = '<div class="ai-message__bubble"><span class="ai-typing"><i></i><i></i><i></i></span></div>';
    messages.appendChild(waiting);
    messages.scrollTop = messages.scrollHeight;
    try {
      const data = await call("send", { id: current, message: text });
      current = Number(data.id);
      waiting.remove();
      addMessage("assistant", data.resposta);
      title.textContent = data.titulo || "Conversa";
      deleteButton.hidden = false;
      status.textContent = "As respostas são geradas com dados financeiros da sua conta.";
      await refresh();
    } catch (error) {
      waiting.remove();
      if (error.id) {
        current = Number(error.id);
        title.textContent = "Conversa";
        deleteButton.hidden = false;
      }
      status.textContent = error.message;
      await refresh();
    } finally {
      busy = false;
      input.focus();
    }
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    send(input.value);
  });
  input.addEventListener("input", () => {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 140)}px`;
  });
  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  document.getElementById("aiNewChat").addEventListener("click", newChat);
  deleteButton.addEventListener("click", async () => {
    if (!current || !confirm("Excluir esta conversa e todas as mensagens?")) return;
    try {
      await call("delete", { id: current });
      newChat();
    } catch (error) {
      status.textContent = error.message;
    }
  });
  root.querySelectorAll(".ai-shortcut[data-prompt]").forEach((button) => {
    button.addEventListener("click", () => send(button.dataset.prompt));
  });
  document.getElementById("openAiModal")?.addEventListener("click", refresh);
  refresh();
})();