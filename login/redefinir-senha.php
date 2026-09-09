<?php
session_start();
require_once __DIR__ . '/../config/conn.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$erro = '';
$sucesso = false;
$tokenValido = false;
$usuarioId = null;

// -----------------------------------------------------------------
// Valida o token: precisa existir, não ter sido usado ainda, e não
// ter passado da data de expiração.
// -----------------------------------------------------------------
if ($token !== '') {
    $stmt = $conn->prepare("SELECT usuario_id, expira_em, usado FROM senha_reset_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($linha && !$linha['usado'] && strtotime($linha['expira_em']) > time()) {
        $tokenValido = true;
        $usuarioId = (int) $linha['usuario_id'];
    } else {
        $erro = 'Este link de redefinição é inválido ou já expirou. Solicite um novo.';
    }
} else {
    $erro = 'Link de redefinição inválido.';
}

// -----------------------------------------------------------------
// Processa a troca de senha (só chega aqui se o token acima for válido)
// -----------------------------------------------------------------
if ($tokenValido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaSenha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (strlen($novaSenha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $stmtUpdate = $conn->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $stmtUpdate->bind_param("si", $novoHash, $usuarioId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // Marca o token como usado — ele não pode ser reaproveitado
        $stmtUsado = $conn->prepare("UPDATE senha_reset_tokens SET usado = 1 WHERE token = ?");
        $stmtUsado->bind_param("s", $token);
        $stmtUsado->execute();
        $stmtUsado->close();

        $sucesso = true;
        $tokenValido = false; // esconde o form depois de trocar com sucesso
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Redefinir senha - FinMap</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

  <div class="container d-flex justify-content-center align-items-center login-wrapper">
    <div class="card login-card p-4 rounded-5" style="width: 420px;">

      <div class="text-center mb-4">
        <h3 class="mb-2">Redefinir senha</h3>
        <p class="subtitle mb-0">Escolha uma nova senha para sua conta.</p>
      </div>

      <?php if ($erro): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <?php if ($sucesso): ?>
        <div class="alert alert-success py-2">
          Senha atualizada com sucesso! Você já pode entrar com a nova senha.
        </div>
        <div class="text-center mt-3">
          <a href="login.php" class="btn btn-success w-100 p-2 login-btn">Ir para o login</a>
        </div>
      <?php elseif ($tokenValido): ?>
        <form method="post" action="redefinir-senha.php">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="mb-3">
            <label class="form-label">Nova senha</label>
            <input type="password" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
          </div>

          <div class="mb-3">
            <label class="form-label">Confirmar nova senha</label>
            <input type="password" name="confirmar_senha" class="form-control" placeholder="Repita a nova senha" required minlength="6">
          </div>

          <button type="submit" class="btn btn-success w-100 p-2 login-btn">
            Salvar nova senha
          </button>
        </form>
      <?php else: ?>
        <div class="text-center">
          <a href="esqueci-senha.php" class="btn btn-success w-100 p-2 login-btn">Solicitar novo link</a>
        </div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="login.php" class="register-link">Voltar para entrar</a>
      </div>

    </div>
  </div>

</body>
</html>