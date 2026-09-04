<?php


session_start();
include '../config/conn.php';

header('Content-Type: application/json'); 

$usuario_id = $_SESSION['usuario_id'] ?? 1;
$periodo = $_GET['periodo'] ?? 'this-month';

$periodosValidos = ['this-month', 'last-30', 'last-month', 'last-3-months'];
if (!in_array($periodo, $periodosValidos, true)) {
    $periodo = 'this-month';
}

// Importações entram no painel assim que são aprovadas. Para elas, usa-se a
// data da aprovação; nos lançamentos manuais, preserva-se a data informada.
$dataReferencia = "CASE WHEN t.origem = 'importacao' THEN DATE(t.atualizado_em) ELSE t.data_transacao END";

switch ($periodo) {
    case 'last-30':
        $condicaoData = "$dataReferencia >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'last-month':
        $condicaoData = "MONTH($dataReferencia) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                          AND YEAR($dataReferencia) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;
    case 'last-3-months':
        $condicaoData = "$dataReferencia >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        break;
    default: 
        $condicaoData = "MONTH($dataReferencia) = MONTH(CURDATE())
                          AND YEAR($dataReferencia) = YEAR(CURDATE())";
        break;
}

$sql = "
    SELECT COALESCE(c.id, 0) AS id,
           COALESCE(c.nome, 'Sem categoria') AS nome,
           COALESCE(c.icone, 'tag') AS icone,
           COALESCE(c.cor, 'green') AS cor,
           COALESCE(SUM(t.valor), 0) AS total
    FROM transacoes t
    LEFT JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND t.tipo = 'despesa' AND t.status = 'aprovado'
      AND $condicaoData
    GROUP BY c.id, c.nome, c.icone, c.cor
    ORDER BY total DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalGeral = array_sum(array_column($linhas, 'total'));

$categorias = array_map(function ($l) use ($totalGeral) {
    $valor = (float) $l['total'];
    return [
        'id'      => (int) $l['id'],
        'nome'    => $l['nome'],
        'icone'   => $l['icone'] ?: 'tag',
        'cor'     => $l['cor'] ?: 'green',
        'valor'   => $valor,
        'percent' => $totalGeral > 0 ? round(($valor / $totalGeral) * 100) : 0
    ];
}, $linhas);

$labelsPeriodo = [
    'this-month'     => 'Este mês',
    'last-30'        => 'Últimos 30 dias',
    'last-month'     => 'Último mês',
    'last-3-months'  => 'Últimos 3 meses'
];

echo json_encode([
    'sucesso'    => true,
    'periodo'    => $periodo,
    'label'      => $labelsPeriodo[$periodo],
    'categorias' => $categorias
]);

$conn->close();
