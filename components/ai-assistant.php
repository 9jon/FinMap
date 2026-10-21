<?php
if (empty($_SESSION['csrf_importacao'])) {
    $_SESSION['csrf_importacao'] = bin2hex(random_bytes(32));
}
$csrfAssistente = $_SESSION['csrf_importacao'];
?>
<div class="ai-modal-overlay" id="aiModal" data-csrf="<?= htmlspecialchars($csrfAssistente, ENT_QUOTES, 'UTF-8') ?>">
  <section class="ai-modal" role="dialog" aria-modal="true" aria-labelledby="aiAssistantTitle">
    <header class="ai-modal__header">
      <div class="ai-modal__title-group">
        <div class="ai-modal__icon" aria-hidden="true"><i class="bi bi-chat-square-text"></i></div>
        <div>
          <h3 id="aiAssistantTitle">Assistente financeiro</h3>
          <p>Pergunte sobre seus gastos, seu orçamento ou suas metas.</p>
        </div>
      </div>
      <button class="ai-modal__close" id="closeAiModal" type="button" aria-label="Fechar assistente"><i class="bi bi-x-lg"></i></button>
    </header>
    <div class="ai-modal__body ai-workspace">
      <aside class="ai-modal__sidebar ai-workspace__sidebar" aria-label="Histórico e sugestões">
        <button class="ai-new-chat" id="aiNewChat" type="button"><i class="bi bi-plus-lg"></i> Nova conversa</button>
        <div class="ai-history">
          <div class="ai-workspace__section-label">RECENTES</div>
          <div id="aiHistoryList" class="ai-history__list" aria-live="polite"><p class="ai-history__empty">Suas conversas salvas aparecerão aqui.</p></div>
        </div>
        <div class="ai-shortcut-group">
          <div class="ai-workspace__section-label">COMECE POR AQUI</div>
          <div class="ai-shortcuts">
            <button class="ai-shortcut" type="button" data-prompt="Analise meus gastos deste mês e destaque os principais pontos."><i class="bi bi-calendar3"></i><span>Analisar meus gastos</span></button>
            <button class="ai-shortcut" type="button" data-prompt="Onde posso economizar com base nas minhas despesas recentes?"><i class="bi bi-piggy-bank"></i><span>Onde posso economizar?</span></button>
            <button class="ai-shortcut" type="button" data-prompt="Faça uma previsão do meu saldo considerando meu ritmo recente de receitas e despesas."><i class="bi bi-graph-up-arrow"></i><span>Prever meu saldo</span></button>
            <button class="ai-shortcut" type="button" data-prompt="Como está o andamento das minhas metas financeiras?"><i class="bi bi-bullseye"></i><span>Acompanhar minhas metas</span></button>
          </div>
        </div>
      </aside>
      <section class="ai-chat-area ai-workspace__chat" aria-label="Conversa com o assistente">
        <div class="ai-chat-toolbar"><span id="aiCurrentChatTitle">Nova conversa</span><button id="aiDeleteChat" class="ai-delete-chat" type="button" aria-label="Excluir conversa" title="Excluir conversa" hidden><i class="bi bi-trash3"></i></button></div>
        <div class="ai-chat-area__messages" id="aiMessages" aria-live="polite"><div class="ai-empty-state"><h4>Como posso ajudar com suas finanças?</h4><p>Envie uma pergunta ou escolha uma sugestão. Suas conversas ficam salvas nesta conta.</p></div></div>
        <form class="ai-chat-area__input" id="aiChatForm"><textarea id="aiChatInput" rows="1" aria-label="Escreva sua pergunta" placeholder="Pergunte sobre suas finanças..."></textarea><button class="ai-send-btn" type="submit" aria-label="Enviar pergunta"><i class="bi bi-arrow-up"></i></button></form>
        <p class="ai-chat-area__hint" id="aiChatStatus">As respostas usam os dados financeiros registrados no FinMap.</p>
      </section>
    </div>
  </section>
</div>