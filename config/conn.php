
<?php

// Todas as datas financeiras devem usar o mesmo fuso no PHP e no MySQL.
// Sem isso, o formulario pode gravar "hoje" em um dia diferente daquele
// utilizado pelos filtros mensais do painel.
date_default_timezone_set('America/Sao_Paulo');

$host = "localhost";
$usuario = "root";
$senha = "";    
$banco = "finmap";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (!$conn->query("SET time_zone = '-03:00'")) {
    die("Erro ao configurar o fuso horario: " . $conn->error);
}

?>

