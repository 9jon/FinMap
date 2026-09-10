<?php
session_start();
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer (PHPMailer)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // -----------------------------------------------------------
        // Por segurança, SEMPRE mostramos a mesma mensagem de sucesso,
        // exista ou não o e-mail no banco. Se disséssemos "e-mail não
        // encontrado", qualquer pessoa poderia usar esse formulário
        // pra descobrir quais e-mails estão cadastrados no sistema.
        // -----------------------------------------------------------
        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expiraEm = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmtToken = $conn->prepare(
                "INSERT INTO senha_reset_tokens (usuario_id, token, expira_em) VALUES (?, ?, ?)"
            );
            $stmtToken->bind_param("iss", $usuario['id'], $token, $expiraEm);
            $stmtToken->execute();
            $stmtToken->close();

            // Ajuste esse domínio/caminho se o projeto rodar em outro lugar
            $linkReset = "http://localhost/FinMap/login/redefinir-senha.php?token=" . urlencode($token);

            $configEmail = require __DIR__ . '/config/email.php';

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $configEmail['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $configEmail['username'];
                $mail->Password = $configEmail['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $configEmail['port'];
                $mail->CharSet = 'UTF-8';

                $mail->setFrom($configEmail['from_email'], $configEmail['from_name']);
                $mail->addAddress($email, $usuario['nome']);

                $mail->isHTML(true);
                $mail->Subject = 'Redefinição de senha - FinMap';
                $mail->Body =
                    'Olá, ' . htmlspecialchars($usuario['nome']) . '!<br><br>' .
                    'Recebemos um pedido para redefinir sua senha no FinMap.<br>' .
                    'Clique no link abaixo para criar uma nova senha (válido por 1 hora):<br><br>' .
                    '<a href="' . $linkReset . '">' . $linkReset . '</a><br><br>' .
                    'Se você não pediu essa redefinição, pode ignorar este e-mail com tranquilidade.';
                $mail->AltBody = "Acesse o link para redefinir sua senha: $linkReset";

                $mail->send();
            } catch (Exception $e) {
                // Não mostramos o erro técnico pro usuário (por segurança
                // e porque não ajudaria em nada), mas registramos no log
                // do servidor pra você conseguir debugar se precisar.
                error_log('Erro ao enviar e-mail de redefinição de senha: ' . $mail->ErrorInfo);
            }
        }

        $mensagem = 'Se esse e-mail estiver cadastrado, você vai receber um link de redefinição em instantes. Confira também a caixa de spam.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Recuperar senha - FinMap</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

  <div class="container d-flex justify-content-center align-items-center login-wrapper">
    <div class="card login-card p-4 rounded-5" style="width: 420px;">

      <div class="text-center mb-4">
        <h3 class="mb-2">Esqueceu a senha?</h3>
        <p class="subtitle mb-0">Informe seu e-mail para receber um link de redefinição.</p>
      </div>

      <?php if ($mensagem): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <?php if (!$mensagem): ?>
        <form method="post" action="esqueci-senha.php">
          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input
              type="email"
              name="email"
              class="form-control"
              placeholder="seu@email.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
            >
          </div>

          <button type="submit" class="btn btn-success w-100 p-2 login-btn">
            Enviar link
          </button>
        </form>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="login.php" class="register-link">Voltar para entrar</a>
      </div>

    </div>
  </div>

</body>
</html>