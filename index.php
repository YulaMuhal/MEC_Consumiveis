<?php
require_once 'config.php';

// Se já está autenticado, redireciona
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email && $senha) {
        $stmt = db()->prepare(
            "SELECT u.*, r.nome AS role_nome
             FROM utilizadores u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.estado = 'ativo'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            session_regenerate_id(true);
            $_SESSION['uid']          = $user['id'];
            $_SESSION['nome']         = $user['nome'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['role']         = $user['role_nome'];
            $_SESSION['unidade']      = $user['unidade'] ?? '';
            $_SESSION['last_activity']= time();
            logAction("Login efectuado", $user['id']);
            header('Location: dashboard.php'); exit;
        } else {
            $erro = 'Email ou palavra-passe incorrectos.';
            logAction("Tentativa de login falhada para: $email");
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIGEC | Acesso ao Sistema — MEC Moçambique</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --verde:   #00843D;
      --verde-d: #005c2b;
      --vermelho:#E3122E;
      --dourado: #FCD116;
      --off-white:#F8F5EE;
      --texto:   #1A2610;
      --cinza:   #5a6e5e;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--off-white);
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }
    /* ── Painel esquerdo ── */
    .left-panel {
      background: var(--verde);
      display: flex; flex-direction: column;
      justify-content: space-between;
      padding: 48px 56px;
      position: relative;
      overflow: hidden;
    }
    .left-panel::before {
      content: '';
      position: absolute; top: -80px; right: -80px;
      width: 380px; height: 380px;
      border-radius: 50%;
      background: rgba(252,209,22,0.12);
    }
    .left-panel::after {
      content: '';
      position: absolute; bottom: -60px; left: -60px;
      width: 280px; height: 280px;
      border-radius: 50%;
      background: rgba(255,255,255,0.06);
    }
    .brand { position: relative; z-index: 2; }
    .brand-logo {
      display: flex; align-items: center; gap: 16px;
      margin-bottom: 48px;
    }
    .brand-logo img { height: 56px; object-fit: contain; filter: brightness(0) invert(1); }
    .brand-logo .sigla {
      font-family: 'DM Serif Display', serif;
      font-size: 2rem;
      color: white;
      line-height: 1;
    }
    .brand-logo .org {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.7);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      display: block;
      margin-top: 3px;
    }
    .brand h1 {
      font-family: 'DM Serif Display', serif;
      font-size: 2.6rem;
      color: white;
      line-height: 1.25;
      margin-bottom: 20px;
    }
    .brand h1 em {
      font-style: normal;
      color: var(--dourado);
    }
    .brand p {
      font-size: 1rem;
      color: rgba(255,255,255,0.8);
      max-width: 380px;
      line-height: 1.65;
    }
    .features {
      display: grid; gap: 16px;
      position: relative; z-index: 2;
    }
    .feat {
      display: flex; align-items: center; gap: 14px;
      background: rgba(255,255,255,0.1);
      border-radius: 14px;
      padding: 14px 18px;
    }
    .feat-icon {
      width: 38px; height: 38px;
      background: var(--dourado);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: var(--verde-d);
      font-size: 1rem;
      flex-shrink: 0;
    }
    .feat span { color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 500; }
    .flag-bar {
      height: 5px;
      background: linear-gradient(90deg, var(--verde) 33%, var(--dourado) 33% 66%, var(--vermelho) 66%);
      border-radius: 99px;
      width: 80px;
      margin-top: 32px;
      position: relative; z-index: 2;
    }
    /* ── Painel direito (login) ── */
    .right-panel {
      display: flex; align-items: center; justify-content: center;
      padding: 48px;
    }
    .login-box {
      width: 100%; max-width: 420px;
    }
    .login-box h2 {
      font-family: 'DM Serif Display', serif;
      font-size: 2rem;
      color: var(--texto);
      margin-bottom: 8px;
    }
    .login-box .subtitle {
      color: var(--cinza);
      font-size: 0.95rem;
      margin-bottom: 36px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      font-size: 0.85rem;
      color: var(--texto);
      margin-bottom: 8px;
      letter-spacing: 0.02em;
    }
    .input-wrap {
      position: relative;
    }
    .input-wrap i {
      position: absolute;
      left: 16px; top: 50%; transform: translateY(-50%);
      color: var(--cinza);
      font-size: 0.9rem;
    }
    .input-wrap input {
      width: 100%;
      padding: 13px 16px 13px 42px;
      border: 1.5px solid #d4dbd6;
      border-radius: 12px;
      font-size: 0.95rem;
      font-family: 'DM Sans', sans-serif;
      background: white;
      color: var(--texto);
      transition: border-color 0.2s, box-shadow 0.2s;
      outline: none;
    }
    .input-wrap input:focus {
      border-color: var(--verde);
      box-shadow: 0 0 0 3px rgba(0,132,61,0.12);
    }
    .btn-login {
      width: 100%;
      padding: 14px;
      background: var(--verde);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      transition: background 0.2s, transform 0.15s;
      margin-top: 28px;
    }
    .btn-login:hover { background: var(--verde-d); transform: translateY(-1px); }
    .btn-login:active { transform: translateY(0); }
    .erro-msg {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.9rem;
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 20px;
    }
    .demo-creds {
      margin-top: 28px;
      background: #f0faf4;
      border: 1px solid #a7d7b8;
      border-radius: 12px;
      padding: 16px 18px;
      font-size: 0.82rem;
      color: var(--cinza);
    }
    .demo-creds strong { color: var(--verde-d); display: block; margin-bottom: 6px; }
    .demo-creds code { background: white; padding: 2px 7px; border-radius: 5px; font-size: 0.82rem; }
    .footer-note {
      text-align: center; margin-top: 32px;
      font-size: 0.78rem; color: #9aab9d;
    }
    @media (max-width: 800px) {
      body { grid-template-columns: 1fr; }
      .left-panel { display: none; }
      .right-panel { padding: 32px 24px; min-height: 100vh; }
    }
  </style>
</head>
<body>
  <!-- Painel esquerdo -->
  <div class="left-panel">
    <div class="brand">
      <div class="brand-logo">
        <img src="assets/logo.png" alt="MEC" onerror="this.style.display='none'">
        <div>
          <div class="sigla">SIGEC</div>
          <span class="org">Ministério da Educação e Cultura</span>
        </div>
      </div>
      <h1>Gestão de consumíveis com <em>transparência</em> e eficiência</h1>
      <p>Plataforma centralizada de controlo de estoque, requisições e movimentações para o MEC Moçambique.</p>
      <div class="flag-bar"></div>
    </div>
    <div class="features">
      <div class="feat"><div class="feat-icon"><i class="fas fa-boxes-stacked"></i></div><span>Controlo de estoque em tempo real</span></div>
      <div class="feat"><div class="feat-icon"><i class="fas fa-file-signature"></i></div><span>Requisições com fluxo de aprovação</span></div>
      <div class="feat"><div class="feat-icon"><i class="fas fa-chart-pie"></i></div><span>Relatórios e dashboards analíticos</span></div>
      <div class="feat"><div class="feat-icon"><i class="fas fa-shield-halved"></i></div><span>Trilha de auditoria completa</span></div>
    </div>
  </div>

  <!-- Painel direito -->
  <div class="right-panel">
    <div class="login-box">
      <h2>Entrar no sistema</h2>
      <p class="subtitle">Insira as suas credenciais para aceder ao SIGEC</p>

      <?php if ($erro): ?>
        <div class="erro-msg"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <form method="POST" action="index.php">
        <?= csrfField() ?>
        <div class="form-group">
          <label for="email">Endereço de email</label>
          <div class="input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="utilizador@mec.gov.mz"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label for="senha">Palavra-passe</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" id="senha" name="senha" placeholder="••••••••" required>
          </div>
        </div>
        <div style="text-align:right;margin-top:-8px;margin-bottom:4px">
          <a href="esqueci_senha.php" style="font-size:0.83rem;color:var(--cinza);text-decoration:none">
            Esqueceu a palavra-passe?
          </a>
        </div>
        <button type="submit" class="btn-login">
          <i class="fas fa-right-to-bracket"></i> Aceder ao SIGEC
        </button>
      </form>
      <p class="footer-note">© 2026 SIGEC — República de Moçambique · MEC</p>
    </div>
  </div>
</body>
</html>
