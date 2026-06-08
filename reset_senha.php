<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$token = trim($_GET['token'] ?? '');
$erro  = '';
$msg   = '';

// Validar token
$reset = null;
if ($token) {
    $stmt = db()->prepare(
        "SELECT * FROM password_resets WHERE token=? AND usado=0 AND expira_em > NOW()"
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
}

if (!$reset && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $erro = 'Este link é inválido ou já expirou. Solicite um novo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    verifyCsrf();
    $nova    = $_POST['senha'] ?? '';
    $confirma= $_POST['confirma'] ?? '';

    if (strlen($nova) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($nova !== $confirma) {
        $erro = 'As palavras-passe não coincidem.';
    } else {
        $hash = password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare("UPDATE utilizadores SET senha=? WHERE email=?")
            ->execute([$hash, $reset['email']]);
        db()->prepare("UPDATE password_resets SET usado=1 WHERE token=?")
            ->execute([$token]);
        logAction("Palavra-passe redefinida via reset para: {$reset['email']}");
        $msg = 'Palavra-passe alterada com sucesso. Pode agora iniciar sessão.';
        $reset = null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIGEC | Nova Palavra-passe</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root { --verde:#00843D; --verde-d:#005c2b; --off-white:#F8F5EE; --texto:#1A2610; --cinza:#5a6e5e; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--off-white); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .box { background:white; border-radius:20px; padding:40px; max-width:420px; width:100%; box-shadow:0 8px 40px rgba(0,0,0,0.08); }
    .logo { display:flex; align-items:center; gap:10px; margin-bottom:28px; }
    .logo-circle { width:44px; height:44px; background:var(--verde); border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-size:1.1rem; }
    .logo span { font-family:'DM Serif Display',serif; font-size:1.4rem; color:var(--texto); }
    h2 { font-family:'DM Serif Display',serif; font-size:1.6rem; color:var(--texto); margin-bottom:8px; }
    p.sub { color:var(--cinza); font-size:0.9rem; margin-bottom:28px; line-height:1.6; }
    label { display:block; font-weight:600; font-size:0.85rem; color:var(--texto); margin-bottom:7px; }
    .form-group { margin-bottom:18px; }
    .input-wrap { position:relative; }
    .input-wrap i.icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--cinza); font-size:0.9rem; }
    .input-wrap .toggle-pw { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--cinza); cursor:pointer; font-size:0.9rem; background:none; border:none; }
    input[type=password], input[type=text] { width:100%; padding:12px 40px 12px 40px; border:1.5px solid #d4dbd6; border-radius:10px; font-size:0.95rem; font-family:'DM Sans',sans-serif; outline:none; transition:border-color .2s,box-shadow .2s; }
    input:focus { border-color:var(--verde); box-shadow:0 0 0 3px rgba(0,132,61,0.1); }
    .strength { height:4px; border-radius:99px; margin-top:6px; background:#e0e0e0; transition:all .3s; }
    .strength-label { font-size:0.75rem; color:var(--cinza); margin-top:4px; }
    .btn { width:100%; padding:13px; background:var(--verde); color:white; border:none; border-radius:10px; font-size:1rem; font-weight:700; font-family:'DM Sans',sans-serif; cursor:pointer; margin-top:8px; transition:background .2s; }
    .btn:hover { background:var(--verde-d); }
    .msg-ok  { background:#e8f5ee; border:1px solid #a7d7b8; color:#166534; padding:12px 16px; border-radius:10px; font-size:0.9rem; margin-bottom:20px; display:flex; gap:10px; align-items:center; }
    .msg-err { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:10px; font-size:0.9rem; margin-bottom:20px; display:flex; gap:10px; align-items:center; }
    .back { display:block; text-align:center; margin-top:20px; font-size:0.88rem; color:var(--cinza); text-decoration:none; }
    .back:hover { color:var(--verde); }
  </style>
</head>
<body>
  <div class="box">
    <div class="logo">
      <div class="logo-circle"><i class="fas fa-key"></i></div>
      <span>SIGEC</span>
    </div>
    <h2>Nova palavra-passe</h2>
    <p class="sub">Escolha uma palavra-passe segura com pelo menos 8 caracteres.</p>

    <?php if ($msg): ?>
      <div class="msg-ok"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
      <a href="index.php" class="back"><i class="fas fa-right-to-bracket"></i> Ir para o login</a>
    <?php elseif ($erro && !$reset): ?>
      <div class="msg-err"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
      <a href="esqueci_senha.php" class="back"><i class="fas fa-arrow-left"></i> Solicitar novo link</a>
    <?php else: ?>
      <?php if ($erro): ?>
        <div class="msg-err"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>
      <form method="POST" action="reset_senha.php?token=<?= htmlspecialchars($token) ?>">
        <?= csrfField() ?>
        <div class="form-group">
          <label>Nova palavra-passe</label>
          <div class="input-wrap">
            <i class="fas fa-lock icon"></i>
            <input type="password" id="senha" name="senha" placeholder="Mínimo 8 caracteres" required autofocus
                   oninput="avaliarForca(this.value)">
            <button type="button" class="toggle-pw" onclick="toggleVer('senha',this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="strength" id="strengthBar"></div>
          <div class="strength-label" id="strengthLabel"></div>
        </div>
        <div class="form-group">
          <label>Confirmar palavra-passe</label>
          <div class="input-wrap">
            <i class="fas fa-lock icon"></i>
            <input type="password" id="confirma" name="confirma" placeholder="Repita a palavra-passe" required>
            <button type="button" class="toggle-pw" onclick="toggleVer('confirma',this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn"><i class="fas fa-check"></i> Definir nova palavra-passe</button>
      </form>
      <a href="index.php" class="back"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
    <?php endif; ?>
  </div>

  <script>
  function toggleVer(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.classList.replace('fa-eye','fa-eye-slash');
    } else {
      inp.type = 'password';
      icon.classList.replace('fa-eye-slash','fa-eye');
    }
  }
  function avaliarForca(v) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const niveis = [
      {cor:'#ef4444', txt:'Muito fraca'},
      {cor:'#f97316', txt:'Fraca'},
      {cor:'#eab308', txt:'Razoável'},
      {cor:'#22c55e', txt:'Boa'},
      {cor:'#16a34a', txt:'Muito forte'},
    ];
    const n = niveis[Math.min(score, 4)];
    bar.style.background = n.cor;
    bar.style.width = ((score+1)/5*100) + '%';
    label.textContent = v ? n.txt : '';
    label.style.color = n.cor;
  }
  </script>
</body>
</html>
