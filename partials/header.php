<?php
// partials/header.php — inclui com $pageTitle definido antes
$user = currentUser();
$role = $user['role'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'SIGEC') ?> | MEC Moçambique</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --verde:    #00843D;
      --verde-d:  #005c2b;
      --vermelho: #E3122E;
      --dourado:  #FCD116;
      --bg:       #F4F7F5;
      --card:     #FFFFFF;
      --texto:    #1A2610;
      --sub:      #5a6e5e;
      --borda:    #DDE5DF;
      --sidebar-w: 256px;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--texto); min-height:100vh; display:flex; }

    /* ─ Sidebar ─────────────────────────────────────────────── */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--verde);
      min-height: 100vh;
      display: flex; flex-direction: column;
      position: fixed; top:0; left:0; bottom:0;
      z-index: 50;
      overflow-y: auto;
    }
    .sidebar-brand {
      padding: 24px 20px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; gap: 12px;
    }
    .sidebar-brand img { height: 42px; filter: brightness(0) invert(1); }
    .sidebar-brand .app-name {
      font-family: 'DM Serif Display', serif;
      font-size: 1.4rem; color: white; line-height: 1;
    }
    .sidebar-brand .app-sub {
      font-size: 0.65rem; color: rgba(255,255,255,0.6);
      text-transform: uppercase; letter-spacing: 0.06em;
    }
    .sidebar-nav { flex: 1; padding: 16px 12px; }
    .nav-section-title {
      font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.1em;
      color: rgba(255,255,255,0.45); font-weight: 600;
      padding: 12px 10px 6px;
    }
    .nav-item {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 12px; border-radius: 10px;
      color: rgba(255,255,255,0.8); font-weight: 500; font-size: 0.9rem;
      text-decoration: none;
      transition: background 0.15s, color 0.15s;
      margin-bottom: 2px;
    }
    .nav-item i { width: 18px; text-align: center; font-size: 0.95rem; }
    .nav-item:hover, .nav-item.active {
      background: rgba(255,255,255,0.14);
      color: white;
    }
    .nav-item.active { font-weight: 700; }
    .sidebar-footer {
      padding: 16px 12px;
      border-top: 1px solid rgba(255,255,255,0.12);
    }
    .user-chip {
      background: rgba(255,255,255,0.1);
      border-radius: 12px; padding: 10px 14px;
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 10px;
    }
    .user-avatar {
      width: 34px; height: 34px;
      background: var(--dourado);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; color: var(--verde-d); font-size: 0.9rem;
      flex-shrink: 0;
    }
    .user-chip .user-name { color: white; font-weight: 600; font-size: 0.85rem; }
    .user-chip .user-role { color: rgba(255,255,255,0.6); font-size: 0.72rem; }
    .btn-logout {
      width: 100%; padding: 9px;
      background: rgba(227,18,46,0.15);
      border: 1px solid rgba(227,18,46,0.3);
      border-radius: 10px; color: #ff8096;
      font-size: 0.85rem; font-weight: 600;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      text-decoration: none;
      transition: background 0.15s;
    }
    .btn-logout:hover { background: rgba(227,18,46,0.25); }

    /* ─ Main Layout ─────────────────────────────────────────── */
    .main-wrap {
      margin-left: var(--sidebar-w);
      flex: 1;
      min-height: 100vh;
      display: flex; flex-direction: column;
    }
    .topbar {
      background: white;
      border-bottom: 1px solid var(--borda);
      padding: 0 32px;
      height: 64px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 40;
    }
    .page-heading { font-weight: 700; font-size: 1.15rem; color: var(--texto); }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .badge-role {
      background: #e8f5ee; color: var(--verde-d);
      font-size: 0.75rem; font-weight: 700;
      padding: 4px 12px; border-radius: 99px;
      text-transform: uppercase; letter-spacing: 0.05em;
    }
    .content-area { padding: 32px; flex: 1; }

    /* ─ Cards ───────────────────────────────────────────────── */
    .card {
      background: white; border-radius: 16px;
      border: 1px solid var(--borda);
      padding: 24px;
    }
    .card-title {
      font-weight: 700; font-size: 1rem;
      color: var(--texto); margin-bottom: 16px;
      display: flex; align-items: center; gap: 8px;
    }

    /* ─ Buttons ─────────────────────────────────────────────── */
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px; border-radius: 10px;
      font-size: 0.9rem; font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer; border: none; transition: all 0.2s;
      text-decoration: none;
    }
    .btn-primary { background: var(--verde); color: white; }
    .btn-primary:hover { background: var(--verde-d); }
    .btn-danger { background: var(--vermelho); color: white; }
    .btn-danger:hover { background: #b80d26; }
    .btn-ghost { background: transparent; border: 1.5px solid var(--borda); color: var(--sub); }
    .btn-ghost:hover { background: var(--bg); }
    .btn-sm { padding: 7px 14px; font-size: 0.82rem; }

    /* ─ Tables ──────────────────────────────────────────────── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th {
      text-align: left; padding: 11px 14px;
      background: var(--bg); color: var(--sub);
      font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
      border-bottom: 1px solid var(--borda);
    }
    td { padding: 12px 14px; border-bottom: 1px solid #f0f4f1; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafcfb; }

    /* ─ Badges ──────────────────────────────────────────────── */
    .badge {
      display: inline-block; padding: 3px 10px;
      border-radius: 99px; font-size: 0.75rem; font-weight: 700;
    }
    .badge-pendente  { background: #fff7e6; color: #b45309; }
    .badge-realizada { background: #e8f5ee; color: #15803d; }
    .badge-perda     { background: #fef2f2; color: #b91c1c; }
    .badge-ok    { background: #e8f5ee; color: #15803d; }
    .badge-baixo { background: #fff7e6; color: #b45309; }
    .badge-critico { background: #fef2f2; color: #b91c1c; }

    /* ─ Alerts ──────────────────────────────────────────────── */
    .alert {
      padding: 12px 16px; border-radius: 10px; font-size: 0.9rem;
      display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
    }
    .alert-success { background: #e8f5ee; color: #166534; border: 1px solid #a7d7b8; }
    .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .alert-warn    { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

    /* ─ Forms ───────────────────────────────────────────────── */
    .form-row { display: grid; gap: 20px; margin-bottom: 20px; }
    .form-row.cols-2 { grid-template-columns: 1fr 1fr; }
    .form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .form-group label {
      display: block; font-weight: 600; font-size: 0.85rem;
      color: var(--texto); margin-bottom: 7px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%; padding: 11px 14px;
      border: 1.5px solid var(--borda);
      border-radius: 10px; font-size: 0.9rem;
      font-family: 'DM Sans', sans-serif;
      background: white; color: var(--texto);
      transition: border-color 0.2s, box-shadow 0.2s;
      outline: none;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--verde);
      box-shadow: 0 0 0 3px rgba(0,132,61,0.1);
    }
    .form-group textarea { resize: vertical; min-height: 90px; }

    /* ─ Modal ───────────────────────────────────────────────── */
    .modal-overlay {
      display: none; position: fixed;
      inset: 0; background: rgba(0,0,0,0.45);
      z-index: 200; align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: white; border-radius: 20px;
      padding: 32px; max-width: 540px; width: 95%;
      box-shadow: 0 24px 60px rgba(0,0,0,0.15);
      max-height: 90vh; overflow-y: auto;
    }
    .modal-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 24px;
    }
    .modal-header h3 { font-size: 1.1rem; font-weight: 700; }
    .modal-close {
      background: none; border: none; font-size: 1.3rem;
      color: var(--sub); cursor: pointer;
    }

    /* ─ Stats Row ───────────────────────────────────────────── */
    .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; margin-bottom: 28px; }
    .stat-card {
      background: white; border-radius: 16px; border: 1px solid var(--borda);
      padding: 20px 22px; display: flex; align-items: center; gap: 16px;
    }
    .stat-icon {
      width: 48px; height: 48px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; flex-shrink: 0;
    }
    .stat-icon.verde   { background: #e8f5ee; color: var(--verde); }
    .stat-icon.amarelo { background: #fffbeb; color: #d97706; }
    .stat-icon.vermelho{ background: #fef2f2; color: var(--vermelho); }
    .stat-icon.azul    { background: #eff6ff; color: #2563eb; }
    .stat-val { font-size: 1.7rem; font-weight: 800; color: var(--texto); line-height: 1; }
    .stat-lbl { font-size: 0.8rem; color: var(--sub); margin-top: 3px; }

    /* ─ Wizard ──────────────────────────────────────────────── */
    .wizard-bar {
      display: flex; align-items: center; gap: 0;
      margin-bottom: 32px;
    }
    .wz-step {
      display: flex; align-items: center; gap: 10px; flex: 1;
    }
    .wz-step:last-child { flex: none; }
    .wz-circle {
      width: 36px; height: 36px;
      border-radius: 50%; border: 2px solid var(--borda);
      background: white; color: var(--sub);
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 0.9rem;
      transition: all 0.3s; flex-shrink: 0;
    }
    .wz-step.active .wz-circle  { background: var(--verde); border-color: var(--verde); color: white; }
    .wz-step.done .wz-circle    { background: var(--verde); border-color: var(--verde); color: white; }
    .wz-label { font-size: 0.85rem; font-weight: 500; color: var(--sub); }
    .wz-step.active .wz-label   { color: var(--verde); font-weight: 700; }
    .wz-line  { flex: 1; height: 2px; background: var(--borda); margin: 0 8px; }
    .wz-line.done { background: var(--verde); }

    /* ─ Responsive ──────────────────────────────────────────── */
    @media (max-width: 900px) {
      .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
      .sidebar.open { transform: translateX(0); }
      .main-wrap { margin-left: 0; }
      .stats-row { grid-template-columns: 1fr 1fr; }
      .form-row.cols-3 { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
      .stats-row { grid-template-columns: 1fr; }
      .form-row.cols-2, .form-row.cols-3 { grid-template-columns: 1fr; }
      .content-area { padding: 20px 16px; }
    }
  </style>
</head>
<body>

<!-- ═══ Sidebar ═══ -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="assets/logo.png" alt="MEC" onerror="this.style.display='none'">
    <div>
      <div class="app-name">SIGEC</div>
      <div class="app-sub">MEC · Moçambique</div>
    </div>
  </div>

  <div class="sidebar-nav">
    <div class="nav-section-title">Geral</div>
    <a href="dashboard.php" class="nav-item <?= ($currentPage??'') === 'dashboard' ? 'active' : '' ?>">
      <i class="fas fa-gauge-high"></i> Dashboard
    </a>

    <?php if (in_array($role, ['admin','gestor'])): ?>
    <div class="nav-section-title">Almoxarifado</div>
    <a href="estoque.php" class="nav-item <?= ($currentPage??'') === 'estoque' ? 'active' : '' ?>">
      <i class="fas fa-boxes-stacked"></i> Estoque
    </a>
    <a href="consumiveis.php" class="nav-item <?= ($currentPage??'') === 'consumiveis' ? 'active' : '' ?>">
      <i class="fas fa-list-ul"></i> Catálogo
    </a>
    <a href="movimentacoes.php" class="nav-item <?= ($currentPage??'') === 'movimentacoes' ? 'active' : '' ?>">
      <i class="fas fa-arrow-right-arrow-left"></i> Movimentações
    </a>
    <?php endif; ?>

    <div class="nav-section-title">Requisições</div>
    <a href="requisicoes.php" class="nav-item <?= ($currentPage??'') === 'requisicoes' ? 'active' : '' ?>">
      <i class="fas fa-file-signature"></i> Nova Requisição
    </a>
    <a href="minhas_requisicoes.php" class="nav-item <?= ($currentPage??'') === 'minhas_req' ? 'active' : '' ?>">
      <i class="fas fa-clipboard-list"></i> Minhas Requisições
    </a>
    <?php if (in_array($role, ['admin','gestor'])): ?>
    <a href="gestao_requisicoes.php" class="nav-item <?= ($currentPage??'') === 'gestao_req' ? 'active' : '' ?>">
      <i class="fas fa-tasks"></i> Gerir Requisições
    </a>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <div class="nav-section-title">Administração</div>
    <a href="utilizadores.php" class="nav-item <?= ($currentPage??'') === 'utilizadores' ? 'active' : '' ?>">
      <i class="fas fa-users"></i> Utilizadores
    </a>
    <a href="logs.php" class="nav-item <?= ($currentPage??'') === 'logs' ? 'active' : '' ?>">
      <i class="fas fa-shield-halved"></i> Logs de Auditoria
    </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(mb_substr($user['nome'], 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars(explode(' ', $user['nome'])[0]) ?></div>
        <div class="user-role"><?= roleLabel($role) ?></div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i> Terminar sessão</a>
  </div>
</nav>

<!-- ═══ Main ═══ -->
<div class="main-wrap">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:14px;">
      <button onclick="document.getElementById('sidebar').classList.toggle('open')"
        style="display:none;background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--texto)" id="menuBtn">
        <i class="fas fa-bars"></i>
      </button>
      <span class="page-heading"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">
      <span class="badge-role"><?= roleLabel($role) ?></span>
      <span style="font-size:0.85rem;color:var(--sub)"><?= htmlspecialchars($user['unidade']) ?></span>
    </div>
  </div>
  <div class="content-area">
