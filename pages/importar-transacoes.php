<?php

session_start();
require_once '../config/conn.php';
require_once '../includes/importador-transacoes.php';

function responderErroImportacao(string $mensagem): void
{
    $_SESSION['importacao_feedback'] = ['tipo' => 'erro', 'mensagem' => $mensagem];
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErroImportacao('Envie o arquivo pelo formulário de importação.');
}

$token = $_POST['csrf_importacao'] ?? '';
if (!isset($_SESSION['csrf_importacao']) || !is_string($token) || !hash_equals($_SESSION['csrf_importacao'], $token)) {
    responderErroImportacao('Sua sessão de importação expirou. Tente novamente.');
}

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    responderErroImportacao('Faça login novamente antes de importar um arquivo.');
}

$upload = $_FILES['arquivo_importacao'] ?? null;
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    responderErroImportacao('Selecione um arquivo válido para importar.');
}
if (($upload['size'] ?? 0) <= 0 || $upload['size'] > 10 * 1024 * 1024) {
    responderErroImportacao('O arquivo deve ter no máximo 10 MB.');
}
if (!is_uploaded_file($upload['tmp_name'])) {
    responderErroImportacao('Não foi possível validar o upload do arquivo.');
}

$nomeOriginal = importacaoTextoCurto(basename((string) $upload['name']), 255);
$extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
if (!in_array($extensao, ['csv', 'ofx', 'qfx', 'xlsx', 'xls'], true)) {
    responderErroImportacao('Formato não suportado. Use CSV, OFX ou Excel (.xlsx).');
}

$transacaoIniciada = false;

try {
    $linhasBrutas = importacaoLerArquivo($upload['tmp_name'], $extensao);
    if (!$linhasBrutas) {
        throw new RuntimeException('Nenhuma transação foi encontrada no arquivo.');
    }

    $stmt = $conn->prepare('SELECT id, nome, tipo FROM categorias WHERE usuario_id = ?');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $categoriasTipo = importacaoCategoriasPorTipo($categorias);
    $aprendizado = importacaoMapaAprendizado($conn, $usuarioId);
    $normalizadas = [];
    $invalidas = 0;
    $hashesNoArquivo = [];

    foreach ($linhasBrutas as $linhaBruta) {
        try {
            $linha = importacaoNormalizarLinha($linhaBruta, $categoriasTipo, $aprendizado);
            if (isset($hashesNoArquivo[$linha['hash']])) {
                $invalidas++;
                continue;
            }
            $hashesNoArquivo[$linha['hash']] = true;
            $normalizadas[] = $linha;
        } catch (RuntimeException $erroLinha) {
            $invalidas++;
        }
    }

    if (!$normalizadas) {
        throw new RuntimeException('Nenhuma linha válida foi encontrada. Verifique se o arquivo possui descrição e valor.');
    }

    $conn->begin_transaction();
    $transacaoIniciada = true;
    $hashArquivo = hash_file('sha256', $upload['tmp_name']);
    $tipoArquivo = strtoupper($extensao === 'qfx' ? 'OFX' : $extensao);
    $totalLidos = count($linhasBrutas);
    $stmtImportacao = $conn->prepare(
        'INSERT INTO importacoes (usuario_id, nome_arquivo, tipo_arquivo, hash_arquivo, total_lidos, total_invalidos) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmtImportacao->bind_param('isssii', $usuarioId, $nomeOriginal, $tipoArquivo, $hashArquivo, $totalLidos, $invalidas);
    if (!$stmtImportacao->execute()) {
        throw new RuntimeException('Não foi possível iniciar a importação.');
    }
    $importacaoId = (int) $stmtImportacao->insert_id;
    $stmtImportacao->close();

    $stmtDuplicado = $conn->prepare('SELECT id FROM transacoes WHERE usuario_id = ? AND hash_importacao = ? LIMIT 1');
    $stmtTransacao = $conn->prepare(
        "INSERT INTO transacoes\n"
        . "(usuario_id, importacao_id, categoria_id, descricao, valor, tipo, origem, status, confianca_percentual, observacao_captura, data_transacao, hash_importacao)\n"
        . "VALUES (?, ?, ?, ?, ?, ?, 'importacao', 'pendente', ?, ?, ?, ?)"
    );

    $importadas = 0;
    $duplicadas = 0;
    foreach ($normalizadas as $linha) {
        $hash = $linha['hash'];
        $stmtDuplicado->bind_param('is', $usuarioId, $hash);
        $stmtDuplicado->execute();
        if ($stmtDuplicado->get_result()->fetch_assoc()) {
            $duplicadas++;
            continue;
        }

        $categoriaId = $linha['categoria_id'];
        $descricao = $linha['descricao'];
        $valor = $linha['valor'];
        $tipo = $linha['tipo'];
        $confianca = $linha['confianca'];
        $observacao = $linha['observacao'];
        $data = $linha['data'];
        $stmtTransacao->bind_param(
            'iiisdsisss',
            $usuarioId,
            $importacaoId,
            $categoriaId,
            $descricao,
            $valor,
            $tipo,
            $confianca,
            $observacao,
            $data,
            $hash
        );
        if (!$stmtTransacao->execute()) {
            throw new RuntimeException('Não foi possível salvar os lançamentos importados.');
        }
        $importadas++;
    }
    $stmtDuplicado->close();
    $stmtTransacao->close();

    $stmtAtualizar = $conn->prepare('UPDATE importacoes SET total_importados = ?, total_duplicados = ? WHERE id = ?');
    $stmtAtualizar->bind_param('iii', $importadas, $duplicadas, $importacaoId);
    $stmtAtualizar->execute();
    $stmtAtualizar->close();
    $conn->commit();
    $transacaoIniciada = false;

    $_SESSION['importacao_feedback'] = [
        'tipo' => 'sucesso',
        'mensagem' => sprintf('%d lançamento(s) foram enviados para revisão. %d duplicado(s) e %d linha(s) inválida(s) foram ignorados.', $importadas, $duplicadas, $invalidas),
    ];
    header('Location: revisar-lancamentos.php?importacao=' . $importacaoId);
    exit;
} catch (Throwable $erro) {
    if ($transacaoIniciada) {
        try {
            $conn->rollback();
        } catch (Throwable $ignorar) {
        }
    }
    responderErroImportacao($erro->getMessage());
}
