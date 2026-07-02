<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Registro - Finanças</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="./FinMap_Projeto/css/registro.css">

</head> 

<body>

<div class="container d-flex justify-content-center align-items-center register-wrapper">
<div class="card register-card p-4 rounded-5" style="width: 420px;">

<div class="text-center mb-4">
<h3 class="mb-2">Crie sua conta</h3>
</div>

<form id="registroForm">

<div class="mb-3">
<label class="form-label">Nome completo</label>
<input type="text" class="form-control" placeholder="Seu nome completo">
</div>

<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" class="form-control" placeholder="seu@email.com">
</div>

<div class="mb-3">
<label class="form-label">Senha</label>
<div class="input-group">
<input type="password" class="form-control" id="senha" placeholder="Digite sua senha">

<span class="input-group-text eye-btn" onclick="toggleSenha()" style="cursor:pointer;">
<i id="iconeSenha" class="bi bi-eye-slash"></i>
</span>

</div>
</div>

<div class="mb-3">
<label class="form-label">Confirmar senha</label>

<div class="input-group">
<input type="password" class="form-control" id="confirmarSenha" placeholder="Confirme sua senha">

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

<button type="button" class="social-btn google">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google">
</button>

<button type="button" class="social-btn apple">
<i class="bi bi-apple"></i>
</button>

</div>

<div class="login-redirect text-center mt-4">
<span>Já tem uma conta?</span>
<a href="login.html" class="login-link">Entrar</a>
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

/* redirecionamento após clicar em criar conta */

document.getElementById("registroForm").addEventListener("submit", function(e){

e.preventDefault();

window.location.href = "config-renda.html";

});

</script>

</body>
</html>