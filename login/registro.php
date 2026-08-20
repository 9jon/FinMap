<?php
// login/registro.php
session_start();
include '../config/conn.php';
require_once '../config/categorias-padrao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        // Verifica se o e-mail já está cadastrado
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existente) {
            $erro = 'Já existe uma conta com esse e-mail.';
        } else {
            // Gera iniciais automaticamente (ex: "João Dias" -> "JD")
            $partesNome = explode(' ', $nome);
            $iniciais = strtoupper(substr($partesNome[0], 0, 1) . substr(end($partesNome), 0, 1));

            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha_hash, avatar_iniciais) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $senhaHash, $iniciais);

            if ($stmt->execute()) {
                $novoUsuarioId = (int) $stmt->insert_id;
                garantirCategoriasPadrao($conn, $novoUsuarioId);
                $_SESSION['usuario_id'] = $novoUsuarioId;
                $stmt->close();
                $conn->close();
                header('Location: ../pages/config-renda.php');
                exit;
            } else {
                $erro = 'Erro ao criar a conta. Tente novamente.';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Registro - Finanças</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/registro.css">

</head> 

<body>

<div class="container d-flex justify-content-center align-items-center register-wrapper">
<div class="card register-card p-4 rounded-5" style="width: 420px;">

<div class="text-center mb-4">
<h3 class="mb-2">Crie sua conta</h3>
</div>

<?php if ($erro): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size: 0.9rem;">
    <?= htmlspecialchars($erro) ?>
  </div>
<?php endif; ?>

<form id="registroForm" method="POST" action="registro.php">

<div class="mb-3">
<label class="form-label">Nome completo</label>
<input type="text" name="nome" class="form-control" placeholder="Seu nome completo" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
</div>

<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
</div>

<div class="mb-3">
<label class="form-label">Senha</label>
<div class="input-group">
<input type="password" name="senha" class="form-control" id="senha" placeholder="Digite sua senha" required>

<span class="input-group-text eye-btn" onclick="toggleSenha()" style="cursor:pointer;">
<i id="iconeSenha" class="bi bi-eye-slash"></i>
</span>

</div>
</div>

<div class="mb-3">
<label class="form-label">Confirmar senha</label>

<div class="input-group">
<input type="password" name="confirmar_senha" class="form-control" id="confirmarSenha" placeholder="Confirme sua senha" required>

<span class="input-group-text eye-btn" onclick="toggleConfirmar()" style="cursor:pointer;">
<i id="iconeConfirmar" class="bi bi-eye-slash"></i>
</span>

</div>
</div>

<div class="options-row mb-3">

<div class="form-check remember-check">
<input class="form-check-input" type="checkbox" id="lembrarSenha">

<label class="form-check-label" for="lembrarSenha">
Lembrar de mim
</label>

</div>

<a href="#" class="forgot-link">Esqueci a senha</a>

</div>

<button type="submit" class="btn btn-success w-100 p-2 register-btn">
Criar conta
</button>

<div class="divider">
<span>ou continue com</span>
</div>

<div class="social-login">

<?php include '../config/oauth-google.php'; ?>
<a class="social-btn google" href="https://accounts.google.com/o/oauth2/v2/auth?client_id=<?= urlencode(GOOGLE_CLIENT_ID) ?>&redirect_uri=<?= urlencode(GOOGLE_REDIRECT_URI) ?>&response_type=code&scope=email%20profile&access_type=online">
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google">
</a>

<button type="button" class="social-btn apple">
<i class="bi bi-apple"></i>
</button>

</div>

<div class="login-redirect text-center mt-4">
<span>Já tem uma conta?</span>
<a href="login.php" class="login-link">Entrar</a>
</div>

</form>

</div>
</div>

<script>

function toggleSenha(){
const senha = document.getElementById("senha");
const icone = document.getElementById("iconeSenha");

if(senha.type === "password"){
senha.type = "text";
icone.classList.replace("bi-eye-slash","bi-eye");
}else{
senha.type = "password";
icone.classList.replace("bi-eye","bi-eye-slash");
}
}

function toggleConfirmar(){
const senha = document.getElementById("confirmarSenha");
const icone = document.getElementById("iconeConfirmar");

if(senha.type === "password"){
senha.type = "text";
icone.classList.replace("bi-eye-slash","bi-eye");
}else{
senha.type = "password";
icone.classList.replace("bi-eye","bi-eye-slash");
}
}

/* O formulário agora envia de verdade pro servidor (method="POST"),
   então não precisamos mais interceptar o submit com JavaScript
   nem redirecionar manualmente — o PHP lá em cima cuida disso. */

</script>

</body>
</html>
