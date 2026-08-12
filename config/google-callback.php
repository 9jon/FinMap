<?php
// login/google-callback.php
// O Google redireciona pra cá depois que o usuário aceita o login.
// Esse arquivo troca o "code" por um token, pega os dados do usuário
// no Google, e faz login/cadastro automático no FinMap.

session_start();
include '../config/conn.php';
include '../config/oauth-google.php';

// O Google manda um "code" na URL quando o login dá certo
$code = $_GET['code'] ?? null;

if (!$code) {
    // Usuário cancelou o login ou algo deu errado
    header('Location: login.php?erro=google_cancelado');
    exit;
}

// --- PASSO 1: trocar o "code" por um access_token ---
$tokenUrl = 'https://oauth2.googleapis.com/token';

$tokenParams = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);

if (!isset($tokenData['access_token'])) {
    header('Location: login.php?erro=google_token');
    exit;
}

$accessToken = $tokenData['access_token'];

// --- PASSO 2: usar o access_token pra pegar os dados do usuário ---
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($accessToken);

$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userInfoResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userInfoResponse, true);

if (!isset($googleUser['id']) || !isset($googleUser['email'])) {
    header('Location: login.php?erro=google_dados');
    exit;
}

$googleId = $googleUser['id'];
$email = $googleUser['email'];
$nome = $googleUser['name'] ?? 'Usuário Google';

// Gera as iniciais (ex: "João Dias" -> "JD")
$partesNome = explode(' ', $nome);
$iniciais = strtoupper(substr($partesNome[0], 0, 1) . substr(end($partesNome), 0, 1));

// --- PASSO 3: já existe esse login social cadastrado? ---
$stmt = $conn->prepare("SELECT usuario_id FROM usuarios_oauth WHERE provedor = 'google' AND provedor_uid = ?");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$oauthExistente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($oauthExistente) {
    // Já logou com Google antes -> só recupera o usuário
    $usuarioId = $oauthExistente['usuario_id'];
} else {
    // Primeira vez logando com Google -> verifica se já existe conta com esse e-mail
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $usuarioExistente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($usuarioExistente) {
        // Já existe conta com esse e-mail (cadastrada por senha) -> vincula o Google a ela
        $usuarioId = $usuarioExistente['id'];
    } else {
        // Não existe -> cria um usuário novo (sem senha, já que login é via Google)
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha_hash, avatar_iniciais) VALUES (?, ?, NULL, ?)");
        $stmt->bind_param("sss", $nome, $email, $iniciais);
        $stmt->execute();
        $usuarioId = $stmt->insert_id;
        $stmt->close();
    }

    // Vincula esse Google ID ao usuário (pra reconhecer no próximo login)
    $stmt = $conn->prepare("INSERT INTO usuarios_oauth (usuario_id, provedor, provedor_uid) VALUES (?, 'google', ?)");
    $stmt->bind_param("is", $usuarioId, $googleId);
    $stmt->execute();
    $stmt->close();
}

// --- PASSO 4: verifica se já terminou o onboarding, pra saber pra onde mandar ---
$stmt = $conn->prepare("SELECT nome, onboarding_concluido FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$_SESSION['usuario_id'] = $usuarioId;
$_SESSION['usuario_nome'] = $usuario['nome'];

if (!$usuario['onboarding_concluido']) {
    header('Location: ../pages/config-renda.php');
} else {
    header('Location: ../pages/dashboard.php');
}
exit;