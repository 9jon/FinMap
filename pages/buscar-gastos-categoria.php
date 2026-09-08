<?php


require_once __DIR__ . '/../includes/autenticacao.php';
$usuario_id = exigirUsuarioAutenticado(true);
include '../config/conn.php';
require_once __DIR__ . '/../includes/percentuais-categorias.php';

header('Content-Type: application/json'); 

$periodo = $_GET['periodo'] ?? 'this-month';

$periodosValidos = ['this-month', 'last-month', 'last-3-months', 'custom'];
if (!in_array($periodo, $periodosValidos, true)) {
    $periodo = 'this-month';
}

// Totais seguem a data do lancamento, independentemente da importacao ou edicao.
$dataReferencia = 't.data_transacao';
$inicioMesAtual = "DATE_FORMAT(CURDATE(), '%Y-%m-01')";
$inicioMesAnterior = "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')";
$inicioTresMeses = "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')";
$fimHoje = 'CURDATE()';

if ($periodo === 'custom') {
    $inicioInformado = $_GET['inicio'] ?? '';
    $fimInformado = $_GET['fim'] ?? '';
    $inicioData = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $inicioInformado);
    $fimData = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $fimInformado);
    $errosInicio = DateTimeImmutable::getLastErrors();
    $errosFim = DateTimeImmutable::getLastErrors();
    $datasValidas = $inicioData && $fimData
        && ($errosInicio === false || ($errosInicio['warning_count'] === 0 && $errosInicio['error_count'] === 0))
        && ($errosFim === false || ($errosFim['warning_count'] === 0 && $errosFim['error_count'] === 0))
        && $inicioData <= $fimData
        && $fimData <= new DateTimeImmutable('today');
    if (!$datasValidas) {
        $periodo = 'this-month';
    } else {
        $inicioSeguro = $inicioData->format('Y-m-d');
        $fimSeguro = $fimData->format('Y-m-d');
        $condicaoData = "$dataReferencia >= '$inicioSeguro' AND $dataReferencia <= '$fimSeguro'";
    }
}

if ($periodo !== 'custom') {
    switch ($periodo) {
        case 'last-month':
            $condicaoData = "$dataReferencia >= $inicioMesAnterior AND $dataReferencia < $inicioMesAtual";
            break;
        case 'last-3-months':
            $condicaoData = "$dataReferencia >= $inicioTresMeses AND $dataReferencia <= $fimHoje";
            break;
        default:
            $condicaoData = "$dataReferencia >= $inicioMesAtual AND $dataReferencia <= $fimHoje";
            break;
    }
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

$categorias = array_map(function ($l) {
    $valor = (float) $l['total'];
    return [
        'id'      => (int) $l['id'],
        'nome'    => $l['nome'],
        'icone'   => $l['icone'] ?: 'tag',
        'cor'     => $l['cor'] ?: 'green',
        'valor'   => $valor,
        'percent' => 0
    ];
}, $linhas);

aplicarPercentuaisInteiros($categorias, 'valor', 'percent');

$agora = new DateTimeImmutable('today');
$inicioPeriodo = match ($periodo) {
    'last-month' => $agora->modify('first day of last month'),
    'last-3-months' => $agora->modify('first day of -2 months'),
    'custom' => $inicioData,
    default => $agora->modify('first day of this month'),
};
$fimPeriodo = $periodo === 'last-month' ? $agora->modify('last day of last month') : ($periodo === 'custom' ? $fimData : $agora);
$mesesAbreviados = [1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez'];
$formatarDataPeriodo = static function (DateTimeImmutable $data) use ($mesesAbreviados): string {
    return $data->format('d') . ' ' . $mesesAbreviados[(int) $data->format('n')];
};
$intervaloPeriodo = $inicioPeriodo->format('Y-m') === $fimPeriodo->format('Y-m')
    ? $inicioPeriodo->format('d') . '–' . $fimPeriodo->format('d') . ' ' . $mesesAbreviados[(int) $fimPeriodo->format('n')]
    : $formatarDataPeriodo($inicioPeriodo) . '–' . $formatarDataPeriodo($fimPeriodo);

$labelsPeriodo = [
    'this-month'     => 'Este mês',
    'last-month'     => 'Mês anterior',
    'last-3-months'  => 'Últimos 3 meses'
];
$labelsPeriodo['custom'] = 'Período personalizado';

echo json_encode([
    'sucesso'    => true,
    'periodo'    => $periodo,
    'label'      => $labelsPeriodo[$periodo],
    'intervalo'  => $intervaloPeriodo,
    'categorias' => $categorias
]);

$conn->close();
