
<?php 
$host = "localhost";
$usuario = "root";
$senha = "";    
$banco = "finmap";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
<<<<<<< HEAD

=======
?>
>>>>>>> 5670726967beaa184440604ba38baf10bf203b2c
