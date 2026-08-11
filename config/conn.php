<<<<<<< Updated upstream
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
=======
<?php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "Finmap"
);
// if (!$conn) {
// die("Erro na conexão: " . mysqli_connect_error());
// }

// mysqli_set_charset($conn, "utf8");
>>>>>>> Stashed changes
?>