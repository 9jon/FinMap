<?php
require_once __DIR__ . '/../includes/autenticacao.php';
$usuarioId = exigirUsuarioAutenticado(true);
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../includes/ai-config.php';
set_time_limit(160);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function aiJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function aiTitle(string $text): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    return function_exists('mb_substr') ? mb_substr($text, 0, 80, 'UTF-8') : substr($text, 0, 80);
}

function aiRespostaParecePensamento(string $text): bool
{
    return preg_match('/\b(?:okay,\s+the user|the user (?:sent|is|has|might)|i need to (?:respond|answer)|let me (?:check|think)|wait,\s+the user|the assistant should)\b/i', $text) === 1;
}

try {
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) aiJson(400, ['erro' => 'Requisição inválida.']);

    $csrf = (string)($data['csrf'] ?? '');
    if (!isset($_SESSION['csrf_importacao']) || !hash_equals($_SESSION['csrf_importacao'], $csrf)) {
        aiJson(403, ['erro' => 'Sessão expirada. Atualize a página e tente novamente.']);
    }

    $action = (string)($data['action'] ?? '');

    if ($action === 'list') {
        $stmt = $conn->prepare('SELECT id,titulo,updated_at FROM assistente_conversas WHERE usuario_id=? ORDER BY updated_at DESC LIMIT 50');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        aiJson(200, ['conversas' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }

    if ($action === 'load') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) aiJson(400, ['erro' => 'Conversa inválida.']);
        $stmt = $conn->prepare('SELECT id,titulo FROM assistente_conversas WHERE id=? AND usuario_id=?');
        $stmt->bind_param('ii', $id, $usuarioId);
        $stmt->execute();
        $chat = $stmt->get_result()->fetch_assoc();
        if (!$chat) aiJson(404, ['erro' => 'Conversa não encontrada.']);

        $stmt = $conn->prepare('SELECT id,papel,conteudo FROM assistente_mensagens WHERE conversa_id=? ORDER BY id ASC');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $messages = [];
        foreach ($rows as $row) {
            if ($row['papel'] === 'assistant' && aiRespostaParecePensamento((string)$row['conteudo'])) {
                $remove = $conn->prepare('DELETE FROM assistente_mensagens WHERE id=? AND conversa_id=?');
                $remove->bind_param('ii', $row['id'], $id);
                $remove->execute();
                continue;
            }
            unset($row['id']);
            $messages[] = $row;
        }
        aiJson(200, ['conversa' => $chat, 'mensagens' => $messages]);
    }

    if ($action === 'delete') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) aiJson(400, ['erro' => 'Conversa inválida.']);
        $stmt = $conn->prepare('DELETE FROM assistente_conversas WHERE id=? AND usuario_id=?');
        $stmt->bind_param('ii', $id, $usuarioId);
        $stmt->execute();
        aiJson(200, ['ok' => true]);
    }

    if ($action !== 'send') aiJson(400, ['erro' => 'Ação inválida.']);
    if (!function_exists('curl_init')) aiJson(503, ['erro' => 'A extensão cURL do PHP não está habilitada.']);

    $message = trim((string)($data['message'] ?? ''));
    $messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
    if ($message === '' || $messageLength > 2000) aiJson(422, ['erro' => 'Escreva uma pergunta de até 2.000 caracteres.']);

    // Confirma a conectividade e a presença do modelo antes de gravar a pergunta.
    $config = finmapAiConfig();
    $check = curl_init($config['ollama_url'] . '/api/tags');
    curl_setopt_array($check, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => finmapOllamaHeaders(),
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
    ]);
    $tagsBody = curl_exec($check);
    $tagsStatus = (int)curl_getinfo($check, CURLINFO_HTTP_CODE);
    $tagsError = curl_error($check);
    curl_close($check);
    $tags = is_string($tagsBody) ? json_decode($tagsBody, true) : null;
    $modelFound = false;
    foreach (($tags['models'] ?? []) as $modelInfo) {
        if (($modelInfo['name'] ?? '') === $config['ollama_model']) { $modelFound = true; break; }
    }
    if ($tagsStatus < 200 || $tagsStatus >= 300) {
        error_log('FinMap Ollama availability check failed: ' . $tagsError . ' HTTP ' . $tagsStatus);
        aiJson(503, ['erro' => 'O servidor não conseguiu conectar ao serviço de IA. Confira FINMAP_OLLAMA_URL e o acesso de rede.']);
    }
    if (!$modelFound) aiJson(503, ['erro' => 'O modelo configurado não foi encontrado no Ollama. Confira FINMAP_OLLAMA_MODEL e instale esse modelo.']);

    $chatId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $conn->begin_transaction();
    if ($chatId) {
        $stmt = $conn->prepare('SELECT id FROM assistente_conversas WHERE id=? AND usuario_id=? FOR UPDATE');
        $stmt->bind_param('ii', $chatId, $usuarioId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $conn->rollback();
            aiJson(404, ['erro' => 'Conversa não encontrada.']);
        }
    } else {
        $title = aiTitle($message);
        $stmt = $conn->prepare('INSERT INTO assistente_conversas (usuario_id,titulo) VALUES (?,?)');
        $stmt->bind_param('is', $usuarioId, $title);
        $stmt->execute();
        $chatId = (int)$conn->insert_id;
    }

    $stmt = $conn->prepare("INSERT INTO assistente_mensagens (conversa_id,papel,conteudo) VALUES (?, 'user', ?)");
    $stmt->bind_param('is', $chatId, $message);
    $stmt->execute();
    $stmt = $conn->prepare('SELECT papel,conteudo FROM assistente_mensagens WHERE conversa_id=? ORDER BY id DESC LIMIT 8');
    $stmt->bind_param('i', $chatId);
    $stmt->execute();
    $rows = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $rows = array_values(array_filter($rows, static function (array $row): bool {
        return ($row['papel'] ?? '') !== 'assistant' || !aiRespostaParecePensamento((string)($row['conteudo'] ?? ''));
    }));
    $conn->commit();
    $stmt = $conn->prepare('SELECT saldo_total FROM usuarios WHERE id=?');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $balance = (float)($user['saldo_total'] ?? 0);

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) receitas, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) despesas FROM transacoes WHERE usuario_id=? AND status='aprovado' AND data_transacao BETWEEN ? AND ?");
    $stmt->bind_param('iss', $usuarioId, $monthStart, $monthEnd);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc() ?: ['receitas' => 0, 'despesas' => 0];

    $systemPrompt = 'Você é o assistente financeiro do FinMap. Responda em português brasileiro, com clareza e objetividade, em no máximo 3 frases. Mostre somente a resposta final; nunca mostre raciocínio interno, análise passo a passo ou notas para você mesmo. Para uma saudação simples, responda apenas com uma saudação. Use somente os dados fornecidos; se faltarem, diga isso. Não prometa resultados nem dê aconselhamento de investimento. Dados do usuário: saldo atual R$ ' . number_format($balance, 2, ',', '.') . '; receitas aprovadas neste mês R$ ' . number_format((float)$totals['receitas'], 2, ',', '.') . '; despesas aprovadas neste mês R$ ' . number_format((float)$totals['despesas'], 2, ',', '.') . '.';
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($rows as $row) $messages[] = ['role' => $row['papel'] === 'assistant' ? 'assistant' : 'user', 'content' => $row['conteudo']];

    $payload = json_encode([
        'model' => $config['ollama_model'],
        'stream' => false,
        'think' => false,
        'keep_alive' => '10m',
        'options' => ['temperature' => 0.35, 'num_predict' => 220],
        'messages' => $messages,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init($config['ollama_url'] . '/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => finmapOllamaHeaders(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 90,
    ]);
    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    $answer = trim((string)($decoded['message']['content'] ?? ''));

    if ($http === 0) aiJson(503, ['id' => $chatId, 'erro' => 'O Ollama não devolveu resposta HTTP. cURL ' . $curlErrno . ': ' . $curlError]);
    if ($http < 200 || $http >= 300) aiJson(503, ['id' => $chatId, 'erro' => 'O Ollama respondeu com HTTP ' . $http . '. Confira o serviço e tente novamente.']);

    // Ollama separa o campo message.thinking do texto final message.content.
    $answer = trim((string)preg_replace('/<think>.*?<\/think>/is', '', $answer));
    if ($answer === '' || aiRespostaParecePensamento($answer)) {
        error_log('FinMap Ollama returned no clean final answer; thinking field present=' . (trim((string)($decoded['message']['thinking'] ?? '')) !== '' ? 'yes' : 'no'));
        aiJson(503, ['id' => $chatId, 'erro' => 'O modelo retornou raciocínio interno em vez da resposta final. Nenhum pensamento foi salvo. Atualize o Ollama e tente novamente.']);
    }

    $stmt = $conn->prepare("INSERT INTO assistente_mensagens (conversa_id,papel,conteudo) VALUES (?, 'assistant', ?)");
    $stmt->bind_param('is', $chatId, $answer);
    $stmt->execute();
    $stmt = $conn->prepare('UPDATE assistente_conversas SET updated_at=CURRENT_TIMESTAMP WHERE id=? AND usuario_id=?');
    $stmt->bind_param('ii', $chatId, $usuarioId);
    $stmt->execute();

    aiJson(200, ['id' => $chatId, 'titulo' => aiTitle($message), 'resposta' => $answer]);
} catch (Throwable $e) {
    if ($conn->errno) $conn->rollback();
    error_log('Assistente FinMap: ' . $e->getMessage());
    aiJson(500, ['erro' => 'Não foi possível acessar o histórico ou gerar a resposta do assistente. Confira os logs do servidor.']);
}