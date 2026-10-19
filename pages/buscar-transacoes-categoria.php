<?php

require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado(true);
include '../config/conn.php';

header('Content-Type: application/json; charset=utf-8');

$categoriaId = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT);
$periodo = $_GET['periodo'] ?? 'this-month';
$hoje = new DateTimeImmutable('today');

if ($categoriaId === false || $categoriaId === null || $categoriaId < 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria inválida.']);
    exit;
}

switch ($periodo) {
    case 'last-month':
        $inicio = $hoje->modify('first day of last month');
        $fim = $hoje->modify('last day of last month');
        break;
    case 'last-3-months':
        $inicio = $hoje->modify('first day of -2 months');
        $fim = $hoje;
        break;
    case 'custom':
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['inicio'] ?? ''));
        $fim = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['fim'] ?? ''));
        $errosInicio = DateTimeImmutable::getLastErrors();
        $errosFim = DateTimeImmutable::getLastErrors();
        $datasValidas = $inicio && $fim
            && ($errosInicio === false || ($errosInicio['warning_count'] === 0 && $errosInicio['error_count'] === 0))
            && ($errosFim === false || ($errosFim['warning_count'] === 0 && $errosFim['error_count'] === 0))
            && $inicio <= $fim && $fim <= $hoje;
        if (!$datasValidas) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Período inválido.']);
            exit;
        }
        break;
    case 'this-month':
    default:
        $inicio = $hoje->modify('first day of this month');
        $fim = $hoje;
        break;
}

$dataInicio = $inicio->format('Y-m-d');
$dataFim = $fim->format('Y-m-d');
$filtroCategoria = $categoriaId === 0 ? 'c.id IS NULL' : 'c.id = ?';
$stmt = $conn->prepare("
    SELECT t.descricao, t.valor, t.data_transacao
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id AND c.usuario_id = t.usuario_id
    WHERE t.usuario_id = ?
      AND $filtroCategoria
      AND t.tipo = 'despesa'
      AND t.status = 'aprovado'
      AND t.data_transacao >= ?
      AND t.data_transacao < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY t.data_transacao DESC, t.atualizado_em DESC, t.id DESC
    LIMIT 5
");
if ($categoriaId === 0) {
    $stmt->bind_param('iss', $usuario_id, $dataInicio, $dataFim);
} else {
    $stmt->bind_param('iiss', $usuario_id, $categoriaId, $dataInicio, $dataFim);
}
$stmt->execute();
$transacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$nomesMeses = [1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez'];
foreach ($transacoes as &$transacao) {
    $timestamp = strtotime($transacao['data_transacao']);
    $transacao['data_formatada'] = $timestamp === false
        ? (string) $transacao['data_transacao']
        : date('d', $timestamp) . ' ' . $nomesMeses[(int) date('n', $timestamp)];
    $transacao['valor'] = (float) $transacao['valor'];
}
unset($transacao);

echo json_encode(['sucesso' => true, 'transacoes' => $transacoes], JSON_UNESCAPED_UNICODE);
