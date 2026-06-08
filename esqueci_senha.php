<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$msg  = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $erro = 'Introduza o seu endereço de email.';
    } else {
        $stmt = db()->prepare("SELECT id, nome FROM utilizadores WHERE email=? AND estado='ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidar tokens anteriores para este email
            db()->prepare("UPDATE password_resets SET usado=1 WHERE email=?")->execute([$email]);

            $token    = bin2hex(random_bytes(32));
            $expira   = date('Y-m-d H:i:s', time() + 3600);
            db()->prepare("INSERT INTO password_resets (email, token, expira_em) VALUES (?,?,?)")
                ->execute([$email, $token, $expira]);

            $link  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                     . dirname($_SERVER['SCRIPT_NAME']) . '/reset_senha.php?token=' . $token;

            $corpo = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto'>
              <div style='background:#00843D;padding:24px 32px;border-radius:10px 10px 0 0'>
                <h2 style='color:white;margin:0'>SIGEC — Recuperação de Palavra-passe</h2>
                <p style='color:rgba(255,255,255,0.8);margin:4px 0 0;font-size:13px'>Ministério da Educação e Cultura</p>
              </div>
              <div style='background:#f9f9f9;padding:28px 32px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 10px 10px'>
                <p>Olá, <strong>" . htmlspecialchars($user['nome']) . "</strong>.</p>
                <p>Recebemos um pedido para redefinir a palavra-passe da sua conta SIGEC.</p>
                <p>Clique no botão abaixo para definir uma nova palavra-passe. O link é válido por <strong>1 hora</strong>.</p>
                <div style='text-align:center;margin:28px 0'>
                  <a href='" . $link . "' style='background:#00843D;color:white;padding:13px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px'>
                    Redefinir Palavra-passe
                  </a>
                </div>
                <p style='font-size:12px;color:#888'>Se não solicitou este pedido, ignore este email. A sua conta está segura.</p>
                <p style='font-size:12px;color:#aaa;border-top:1px solid #e0e0e0;padding-top:14px;margin-top:14px'>
                  Não consegue clicar no botão? Copie este link:<br>
                  <span style='color:#00843D;word-break:break-all'>" . $link . "</span>
                </p>
              </div>
            </div>";

            enviarEmail($email, 'SIGEC — Recuperação de Palavra-passe', $corpo);
            logAction("Pedido de reset de senha para: $email");
        }

        // Mensagem igual independentemente de o email existir (evita enumeração)
        $msg = 'Se o email existir no sistema, receberá um link de recuperação em breve.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIGEC | Recuperar Palavra-passe</title>
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
    .input-wrap { position:relative; }
    .input-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--cinza); font-size:0.9rem; }
    input[type=email] { width:100%; padding:12px 14px 12px 40px; border:1.5px solid #d4dbd6; border-radius:10px; font-size:0.95rem; font-family:'DM Sans',sans-serif; outline:none; transition:border-color .2s,box-shadow .2s; }
    input[type=email]:focus { border-color:var(--verde); box-shadow:0 0 0 3px rgba(0,132,61,0.1); }
    .btn { width:100%; padding:13px; background:var(--verde); color:white; border:none; border-radius:10px; font-size:1rem; font-weight:700; font-family:'DM Sans',sans-serif; cursor:pointer; margin-top:20px; transition:background .2s; }
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
      <div class="logo-circle"><i class="fas fa-lock-open"></i></div>
      <span>SIGEC</span>
    </div>
    <h2>Recuperar palavra-passe</h2>
    <p class="sub">Introduza o seu email institucional. Enviaremos um link para redefinir a sua palavra-passe.</p>

    <?php if ($msg): ?>
      <div class="msg-ok"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="msg-err"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (!$msg): ?>
    <form method="POST">
      <?= csrfField() ?>
      <div>
        <label for="email">Endereço de email</label>
        <div class="input-wrap">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="utilizador@mec.gov.mz"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Enviar link de recuperação</button>
    </form>
    <?php endif; ?>

    <a href="index.php" class="back"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
  </div>
</body>
</html>
