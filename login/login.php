<?php
session_start();
include '../config/conn.php';

$errosGoogle = [
    'google_cancelado' => 'O login com Google foi cancelado. Você pode tentar novamente.',
    'google_sessao' => 'O login com Google expirou ou não foi iniciado neste navegador. Clique no botão Google novamente.',
    'google_configuracao' => 'O login com Google está indisponível. Use seu e-mail e senha.',
    'google_token' => 'Não foi possível concluir o login com Google. Tente novamente.',
    'google_dados' => 'Não foi possível confirmar sua conta Google. Tente novamente.',
];
$codigoErro = $_GET['erro'] ?? '';
$erro = is_string($codigoErro) ? ($errosGoogle[$codigoErro] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $stmt = $conn->prepare("SELECT id, nome, senha_hash, onboarding_concluido FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            $erro = 'E-mail ou senha incorretos.';
        } else {
            session_regenerate_id(true);
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $conn->close();

            // Se o usuário ainda não terminou o onboarding, manda pra config-renda.
            // Se já terminou, manda direto pro dashboard.
            if (!$usuario['onboarding_concluido']) {
                header('Location: ../pages/config-renda.php');
            } else {
                header('Location: ../pages/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login - FinMap</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

  <div class="container d-flex justify-content-center align-items-center login-wrapper">
    <div class="card login-card p-4 rounded-5" style="width: 420px;">

      <div class="text-center mb-4">
        <h3 class="mb-2">Entrar</h3>
        <p class="subtitle mb-0">Acesse sua conta e continue no FinMap</p>
      </div>

      <?php if ($erro): ?>
        <div class="alert alert-danger py-2 px-3" style="font-size: 0.9rem;">
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" method="POST" action="login.php">

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Senha</label>
          <div class="input-group">
            <input type="password" name="senha" class="form-control" id="senhaLogin" placeholder="Digite sua senha" required>
            <span class="input-group-text eye-btn" onclick="toggleSenhaLogin()" style="cursor:pointer;">
              <i id="iconeSenhaLogin" class="bi bi-eye-slash"></i>
            </span>
          </div>
        </div>

        <div class="options-row mb-3">
          <div class="form-check remember-check">
            <input class="form-check-input" type="checkbox" id="lembrarLogin">
            <label class="form-check-label" for="lembrarLogin">
              Lembrar de mim
            </label>
          </div>

          <a href="esqueci-senha.php" class="forgot-link">Esqueci a senha</a>
        </div>

        <button type="submit" class="btn btn-success w-100 p-2 login-btn">
          Entrar
        </button>

        <div class="divider">
          <span>ou continue com</span>
        </div>

        <div class="social-login">

<a class="social-btn google" href="google.php">
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google">
</a>

          <button type="button" class="social-btn apple">
            <i class="bi bi-apple"></i>
          </button>
        </div>

        <div class="register-redirect text-center mt-4">
          <span>Ainda não tem uma conta?</span>
          <a href="registro.php" class="register-link">Criar conta</a>
        </div>

      </form>
    </div>
  </div>

  <script>
    function toggleSenhaLogin() {
      const senha = document.getElementById("senhaLogin");
      const icone = document.getElementById("iconeSenhaLogin");

      if (senha.type === "password") {
        senha.type = "text";
        icone.classList.replace("bi-eye-slash", "bi-eye");
      } else {
        senha.type = "password";
        icone.classList.replace("bi-eye", "bi-eye-slash");
      }
    }

    /* O formulário agora envia de verdade pro servidor (method="POST"),
       então não interceptamos mais o submit nem redirecionamos na mão
       — o PHP lá em cima decide pra onde mandar o usuário. */
  </script>

</body>
</html>
