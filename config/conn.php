
<?php 
$host = "localhost";
$usuario = "root";
$senha = "";    
$banco = "finmap";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Garante acentuação correta
$conn->set_charset("utf8mb4");

