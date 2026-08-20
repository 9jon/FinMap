<?php
// pages/buscar-gastos-categoria.php
// Retorna via GET os gastos agrupados por categoria, filtrados por período.

session_start();
include '../config/conn.php';

header('Content-Type: application/json'); 

$usuario_id = $_SESSION['usuario_id'] ?? 1;
$periodo = $_GET['periodo'] ?? 'this-month';

$periodosValidos = ['this-month', 'last-30', 'last-month', 'last-3-months'];
if (!in_array($periodo, $periodosValidos, true)) {
    $periodo = 'this-month';
}

switch ($periodo) {
    case 'last-30':
        $condicaoData = "t.data_transacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'last-month':
        $condicaoData = "MONTH(t.data_transacao) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                          AND YEAR(t.data_transacao) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;
    case 'last-3-months':
        $condicaoData = "t.data_transacao >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        break;
    default: // this-month
        $condicaoData = "MONTH(t.data_transacao) = MONTH(CURDATE())
                          AND YEAR(t.data_transacao) = YEAR(CURDATE())";
        break;
}

$sql = "
    SELECT c.id, c.nome, c.icone, c.cor, COALESCE(SUM(t.valor), 0) AS total
    FROM transacoes t
    INNER JOIN categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND t.tipo = 'despesa' AND t.status IN ('pendente', 'aprovado')
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
