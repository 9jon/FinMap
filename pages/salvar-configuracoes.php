<?php
require_once __DIR__.'/../includes/autenticacao.php';
$uid=exigirUsuarioAutenticado(true);
require_once __DIR__.'/../config/conn.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
function resposta(int $status,array $dados):void{http_response_code($status);echo json_encode($dados,JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')resposta(405,['sucesso'=>false,'erro'=>'M?todo inv?lido.']);
if(empty($_SESSION['csrf_conta'])||!hash_equals($_SESSION['csrf_conta'],(string)($_POST['csrf_token']??'')))resposta(403,['sucesso'=>false,'erro'=>'Sess?o expirada. Atualize a p?gina.']);
$campos=['notificar_orcamento','notificar_metas','notificar_gastos','notificar_revisao','notificar_agendamentos','notificar_economia','ocultar_valores'];
$valores=[];foreach($campos as $campo)$valores[$campo]=isset($_POST[$campo])&&$_POST[$campo]==='1'?1:0;
$sql='INSERT INTO preferencias_usuario (usuario_id,'.implode(',',$campos).') VALUES (?,'.implode(',',array_fill(0,count($campos),'?')).') ON DUPLICATE KEY UPDATE '.implode(',',array_map(fn($c)=>"$c=VALUES($c)",$campos));
$stmt=$conn->prepare($sql);if(!$stmt)resposta(500,['sucesso'=>false,'erro'=>'Execute a migra??o SQL de prefer?ncias antes de salvar.']);
$types='i'.str_repeat('i',count($campos));$params=[$types,$uid];foreach($campos as $campo)$params[]=$valores[$campo];$refs=[];foreach($params as $k=>&$v)$refs[$k]=&$v;call_user_func_array([$stmt,'bind_param'],$refs);
if(!$stmt->execute())resposta(500,['sucesso'=>false,'erro'=>'N?o foi poss?vel salvar as configura??es.']);$stmt->close();resposta(200,['sucesso'=>true]);
