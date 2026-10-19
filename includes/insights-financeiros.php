<?php
require_once __DIR__ . '/ai-config.php';
function finmapLimparContagemCaracteresInsight(string $descricao): string
{
    return trim((string)preg_replace('/\s*\(\s*\d+\s+caracteres?\s*\)\s*$/iu', '', trim($descricao)));
}
function finmapGarantirNotificacaoInsight(mysqli $conn, int $usuarioId, array $insight): void
{
    $inicio = (string)($insight['periodo_inicio'] ?? '');
    if ($inicio === '') return;
    $tituloNotificacao = 'Insight da semana';
    $mensagem = trim((string)($insight['titulo'] ?? '') . ': ' . (string)($insight['descricao'] ?? ''));
    $mensagem = function_exists('mb_substr') ? mb_substr($mensagem, 0, 500, 'UTF-8') : substr($mensagem, 0, 500);
    $stmt = $conn->prepare("SELECT id, mensagem FROM notificacoes WHERE usuario_id = ? AND categoria = 'ia' AND titulo = ? AND criado_em >= ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param('iss', $usuarioId, $tituloNotificacao, $inicio);
    if (!$stmt->execute()) { $stmt->close(); return; }
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existente) {
        if (($insight['fonte'] ?? '') === 'ia' && $existente['mensagem'] !== $mensagem) {
            $stmt = $conn->prepare("UPDATE notificacoes SET mensagem = ?, lida = 0 WHERE usuario_id = ? AND categoria = 'ia' AND titulo = ? AND criado_em >= ?");
            if ($stmt) { $stmt->bind_param('siss', $mensagem, $usuarioId, $tituloNotificacao, $inicio); $stmt->execute(); $stmt->close(); }
        }
        return;
    }
    $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, categoria, titulo, mensagem, lida) VALUES (?, 'ia', ?, ?, 0)");
    if ($stmt) { $stmt->bind_param('iss', $usuarioId, $tituloNotificacao, $mensagem); $stmt->execute(); $stmt->close(); }
}
function finmapOllamaModeloDisponivel(): bool
{
    if (!function_exists('curl_init')) return false;
    $curl = curl_init(finmapAiConfig()['ollama_url'] . '/api/tags');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => finmapOllamaHeaders(), CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 2]);
    $resposta = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    if (!is_string($resposta) || $status < 200 || $status >= 300) return false;
    $dados = json_decode($resposta, true);
    foreach (($dados['models'] ?? []) as $modelo) if (($modelo['name'] ?? '') === finmapAiConfig()['ollama_model']) return true;
    return false;
}
function finmapBuscarOuGerarInsightSemanal(mysqli $conn, int $usuarioId): ?array
{
    $hoje = new DateTimeImmutable('today');
    $inicioSemana = $hoje->modify('monday this week');
    $inicioPeriodo = $inicioSemana->format('Y-m-d');
    $fimPeriodo = $hoje->format('Y-m-d');
    $inicioAnterior = $inicioSemana->modify('-7 days')->format('Y-m-d');
    $fimAnterior = $hoje->modify('-7 days')->format('Y-m-d');

    $stmt = $conn->prepare('SELECT titulo, descricao, severidade, impacto_estimado, horizonte, fonte, periodo_inicio, periodo_fim FROM insights_financeiros WHERE usuario_id = ? AND tipo = ? AND periodo_inicio = ? LIMIT 1');
    if (!$stmt) return null;
    $tipo = 'resumo_semanal'; $stmt->bind_param('iss', $usuarioId, $tipo, $inicioPeriodo);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $existente = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($existente) {
        $descricaoLimpa = finmapLimparContagemCaracteresInsight((string)($existente['descricao'] ?? ''));
        if ($descricaoLimpa !== (string)($existente['descricao'] ?? '')) {
            $updateInsight = $conn->prepare('UPDATE insights_financeiros SET descricao = ? WHERE usuario_id = ? AND tipo = ? AND periodo_inicio = ?');
            if ($updateInsight) {
                $updateInsight->bind_param('siss', $descricaoLimpa, $usuarioId, $tipo, $inicioPeriodo);
                $updateInsight->execute();
                $updateInsight->close();
            }
            $existente['descricao'] = $descricaoLimpa;
        }
    }
    if ($existente && (($existente['fonte'] ?? '') === 'ia' || !finmapOllamaModeloDisponivel())) {
        finmapGarantirNotificacaoInsight($conn, $usuarioId, $existente); return $existente;
    }
    $stmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN tipo='despesa' AND data_transacao BETWEEN ? AND ? THEN valor ELSE 0 END),0) despesas_atual,
        COALESCE(SUM(CASE WHEN tipo='receita' AND data_transacao BETWEEN ? AND ? THEN valor ELSE 0 END),0) receitas_atual,
        COALESCE(SUM(CASE WHEN tipo='despesa' AND data_transacao BETWEEN ? AND ? THEN valor ELSE 0 END),0) despesas_anterior
        FROM transacoes WHERE usuario_id=? AND status='aprovado' AND data_transacao BETWEEN ? AND ?");
    if (!$stmt) return null;
    $stmt->bind_param('ssssssiss', $inicioPeriodo, $fimPeriodo, $inicioPeriodo, $fimPeriodo, $inicioAnterior, $fimAnterior, $usuarioId, $inicioAnterior, $fimPeriodo);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $dados = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
    $despesasAtual = (float)($dados['despesas_atual'] ?? 0); $receitasAtual = (float)($dados['receitas_atual'] ?? 0); $despesasAnterior = (float)($dados['despesas_anterior'] ?? 0);
    if ($despesasAtual <= 0 && $receitasAtual <= 0 && $despesasAnterior <= 0) return null;
    $categoriaDestaque = '';
    $stmt = $conn->prepare("SELECT c.nome FROM transacoes t LEFT JOIN categorias c ON c.id=t.categoria_id AND c.usuario_id=t.usuario_id WHERE t.usuario_id=? AND t.status='aprovado' AND t.tipo='despesa' AND t.data_transacao BETWEEN ? AND ? GROUP BY c.nome ORDER BY SUM(t.valor) DESC LIMIT 1");
    if ($stmt) { $stmt->bind_param('iss', $usuarioId, $inicioPeriodo, $fimPeriodo); if ($stmt->execute()) { $row=$stmt->get_result()->fetch_assoc(); $categoriaDestaque=trim((string)($row['nome'] ?? '')); } $stmt->close(); }
    $variacao = $despesasAnterior > 0 ? (($despesasAtual-$despesasAnterior)/$despesasAnterior)*100 : null;
    $severidade = ($variacao !== null && $variacao >= 25) ? 'medio' : 'baixo'; $impacto = abs($despesasAtual-$despesasAnterior);
    $titulo = 'Resumo financeiro da semana';
    if ($variacao !== null && $variacao >= 15) { $titulo='Seus gastos aumentaram nesta semana'; $descricao='As despesas foram '.number_format($variacao,1,',','.').'% maiores que nos mesmos dias da semana anterior. '; }
    elseif ($variacao !== null && $variacao <= -10) { $titulo='Seus gastos diminuíram nesta semana'; $descricao='As despesas foram '.number_format(abs($variacao),1,',','.').'% menores que nos mesmos dias da semana anterior. '; }
    else { $descricao='Nesta semana, suas despesas somam R$ '.number_format($despesasAtual,2,',','.').' e suas receitas somam R$ '.number_format($receitasAtual,2,',','.').'. '; }
    if ($variacao !== null && $variacao >= 15) $descricao .= 'Você gastou R$ '.number_format($despesasAtual,2,',','.').', contra R$ '.number_format($despesasAnterior,2,',','.').' no período comparável. ';
    if ($categoriaDestaque !== '') $descricao .= 'Categoria com mais despesas: '.$categoriaDestaque.'.';
    $fonte='regras';
    if (function_exists('curl_init')) {
        $contexto=json_encode(['despesas_semana_atual'=>round($despesasAtual,2),'receitas_semana_atual'=>round($receitasAtual,2),'despesas_semana_anterior'=>round($despesasAnterior,2),'variacao_despesas_percentual'=>$variacao===null?null:round($variacao,1),'categoria_com_mais_despesas'=>$categoriaDestaque?:null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $payload=json_encode(['model'=>finmapAiConfig()['ollama_model'],'think'=>false,'stream'=>false,'format'=>'json','options'=>['temperature'=>0.2,'num_predict'=>180],'prompt'=>'Escreva um insight financeiro semanal curto em português brasileiro, sem inventar dados nem dar certeza sobre o futuro. Gere titulo e descricao de ate 350 caracteres e sugestao pratica quando possivel. Responda somente JSON com titulo e descricao. Dados: '.$contexto],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $curl=curl_init(finmapAiConfig()['ollama_url'] . '/api/generate');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>finmapOllamaHeaders(),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>20]);
        $resposta=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        if (is_string($resposta)&&$status>=200&&$status<300) { $retorno=json_decode($resposta,true); $texto=is_array($retorno)?json_decode((string)($retorno['response']??''),true):null; if (is_array($texto)&&trim((string)($texto['titulo']??''))!==''&&trim((string)($texto['descricao']??''))!=='') { $titulo=trim($texto['titulo']); $descricao=finmapLimparContagemCaracteresInsight(trim($texto['descricao'])); $fonte='ia'; } }
    }
    $stmt=$conn->prepare("INSERT INTO insights_financeiros (usuario_id,tipo,periodo_inicio,periodo_fim,titulo,descricao,severidade,impacto_estimado,horizonte,fonte,lido) VALUES (?,?,?,?,?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE periodo_fim=VALUES(periodo_fim),titulo=VALUES(titulo),descricao=VALUES(descricao),severidade=VALUES(severidade),impacto_estimado=VALUES(impacto_estimado),horizonte=VALUES(horizonte),fonte=VALUES(fonte)");
    if (!$stmt) return null;
    $horizonte='Semana de '.date('d/m',strtotime($inicioPeriodo)).' a '.date('d/m',strtotime($fimPeriodo));
    $stmt->bind_param('issssssdss',$usuarioId,$tipo,$inicioPeriodo,$fimPeriodo,$titulo,$descricao,$severidade,$impacto,$horizonte,$fonte);
    if (!$stmt->execute()) { $stmt->close(); return null; } $stmt->close();
    $insight=['titulo'=>$titulo,'descricao'=>$descricao,'severidade'=>$severidade,'impacto_estimado'=>$impacto,'horizonte'=>$horizonte,'fonte'=>$fonte,'periodo_inicio'=>$inicioPeriodo,'periodo_fim'=>$fimPeriodo];
    finmapGarantirNotificacaoInsight($conn,$usuarioId,$insight); return $insight;
}

function finmapBuscarOuGerarPrevisaoSaldo(mysqli $conn, int $usuarioId): ?array
{
    $hoje = new DateTimeImmutable('today');
    $inicioSemana = $hoje->modify('monday this week')->format('Y-m-d');
    $fimHoje = $hoje->format('Y-m-d');
    $tipo = 'previsao_saldo_negativo';

    $stmt = $conn->prepare('SELECT titulo, descricao, severidade, impacto_estimado, horizonte, fonte, periodo_inicio, periodo_fim FROM insights_financeiros WHERE usuario_id = ? AND tipo = ? AND periodo_inicio = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('iss', $usuarioId, $tipo, $inicioSemana);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existente) { $existente['tipo'] = $tipo; return $existente; }

    $stmt = $conn->prepare('SELECT saldo_total FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $usuarioId);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$usuario) return null;
    $saldo = (float)$usuario['saldo_total'];

    $inicioHistorico = $hoje->modify('-29 days')->format('Y-m-d');
    $stmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS despesas,
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS receitas,
        COUNT(*) AS quantidade
        FROM transacoes WHERE usuario_id = ? AND status = 'aprovado' AND data_transacao BETWEEN ? AND ?");
    if (!$stmt) return null;
    $stmt->bind_param('iss', $usuarioId, $inicioHistorico, $fimHoje);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $dados = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $saidaLiquida = (float)($dados['despesas'] ?? 0) - (float)($dados['receitas'] ?? 0);
    if ($saldo > 0 && ((int)($dados['quantidade'] ?? 0) === 0 || $saidaLiquida <= 0)) return null;

    $dias = 0;
    $mediaDiaria = 0.0;
    if ($saldo > 0) {
        $mediaDiaria = $saidaLiquida / 30;
        if ($mediaDiaria <= 0) return null;
        $dias = (int)ceil($saldo / $mediaDiaria);
        if ($dias > 90) return null;
        $dataPrevista = $hoje->modify('+' . $dias . ' days')->format('d/m');
        $titulo = 'Saldo pode ficar negativo em cerca de ' . $dias . ' dias';
        $descricao = 'Com o saldo atual de R$ ' . number_format($saldo, 2, ',', '.') .
            ' e a saída líquida média de R$ ' . number_format($mediaDiaria, 2, ',', '.') .
            ' por dia registrada nos últimos 30 dias, o saldo pode ficar negativo por volta de ' . $dataPrevista .
            ' se esse ritmo continuar e não entrarem novas receitas.';
        $severidade = $dias <= 7 ? 'alto' : ($dias <= 30 ? 'medio' : 'baixo');
        $horizonte = 'Estimativa de ' . $dias . ' dias';
    } else {
        $titulo = 'Seu saldo já está negativo';
        $descricao = 'O saldo registrado está em R$ ' . number_format($saldo, 2, ',', '.') .
            '. Revise os próximos lançamentos e receitas para planejar a recuperação.';
        $severidade = 'alto';
        $horizonte = 'Situação atual';
    }

    $impacto = abs($mediaDiaria);
    $fonte = 'regras';
    $stmt = $conn->prepare("INSERT INTO insights_financeiros (usuario_id, tipo, periodo_inicio, periodo_fim, titulo, descricao, severidade, impacto_estimado, horizonte, fonte, lido)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE periodo_fim=VALUES(periodo_fim), titulo=VALUES(titulo), descricao=VALUES(descricao), severidade=VALUES(severidade), impacto_estimado=VALUES(impacto_estimado), horizonte=VALUES(horizonte), fonte=VALUES(fonte)");
    if (!$stmt) return null;
    $stmt->bind_param('issssssdss', $usuarioId, $tipo, $inicioSemana, $fimHoje, $titulo, $descricao, $severidade, $impacto, $horizonte, $fonte);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $stmt->close();

    $tituloNotificacao = 'Previsão de saldo';
    $mensagem = $titulo . ': ' . $descricao;
    $stmt = $conn->prepare("SELECT id, mensagem FROM notificacoes WHERE usuario_id = ? AND categoria = 'alerta' AND titulo = ? AND criado_em >= ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('iss', $usuarioId, $tituloNotificacao, $inicioSemana);
        if ($stmt->execute()) {
            $notificacao = $stmt->get_result()->fetch_assoc();
            if ($notificacao) {
                if ($notificacao['mensagem'] !== $mensagem) {
                    $update = $conn->prepare('UPDATE notificacoes SET mensagem = ?, lida = 0 WHERE id = ? AND usuario_id = ?');
                    if ($update) { $id = (int)$notificacao['id']; $update->bind_param('sii', $mensagem, $id, $usuarioId); $update->execute(); $update->close(); }
                }
            } else {
                $insert = $conn->prepare("INSERT INTO notificacoes (usuario_id, categoria, titulo, mensagem, lida) VALUES (?, 'alerta', ?, ?, 0)");
                if ($insert) { $insert->bind_param('iss', $usuarioId, $tituloNotificacao, $mensagem); $insert->execute(); $insert->close(); }
            }
        }
        $stmt->close();
    }

    return ['tipo'=>$tipo, 'titulo'=>$titulo, 'descricao'=>$descricao, 'severidade'=>$severidade,
        'impacto_estimado'=>$impacto, 'horizonte'=>$horizonte, 'fonte'=>$fonte,
        'periodo_inicio'=>$inicioSemana, 'periodo_fim'=>$fimHoje];
}
