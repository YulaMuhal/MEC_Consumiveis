<?php
require_once 'config.php';
requireLogin();
$currentPage = 'dashboard';
$pageTitle   = 'Dashboard';

$db   = db();
$user = currentUser();
$role = $user['role'];

// ── Stats ────────────────────────────────────────────────────────────────────
$totalConsumineis = $db->query("SELECT COUNT(*) FROM consumiveis")->fetchColumn();
$itensEstoqueBaixo = $db->query(
    "SELECT COUNT(*) FROM estoque e WHERE e.quantidade <= e.quantidade_minima"
)->fetchColumn();
$reqPendentes = $db->query(
    "SELECT COUNT(*) FROM requisicoes WHERE estado='pendente'"
)->fetchColumn();
$totalMovHoje = $db->query(
    "SELECT COUNT(*) FROM movimentacoes WHERE DATE(criado_em)=CURDATE()"
)->fetchColumn();

// ── Requisições recentes ──────────────────────────────────────────────────────
$sqlReq = "SELECT r.*, u.nome AS req_nome
           FROM requisicoes r
           JOIN utilizadores u ON u.id = r.utilizador_id";
if ($role === 'requisitante') {
    $stmt = $db->prepare($sqlReq . " WHERE r.utilizador_id=? ORDER BY r.criado_em DESC LIMIT 10");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->query($sqlReq . " ORDER BY r.criado_em DESC LIMIT 10");
}
$requisicoes = $stmt->fetchAll();

// ── Estoque crítico ───────────────────────────────────────────────────────────
$estoqueCritico = $db->query(
    "SELECT c.nome, e.quantidade, e.quantidade_minima, c.unidade
     FROM estoque e JOIN consumiveis c ON c.id = e.consumivel_id
     WHERE e.quantidade <= e.quantidade_minima
     ORDER BY e.quantidade ASC LIMIT 8"
)->fetchAll();

include 'partials/header.php';
?>

<!-- Stats Row -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-icon verde"><i class="fas fa-boxes-stacked"></i></div>
    <div>
      <div class="stat-val"><?= $totalConsumineis ?></div>
      <div class="stat-lbl">Tipos de consumível</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amarelo"><i class="fas fa-triangle-exclamation"></i></div>
    <div>
      <div class="stat-val"><?= $itensEstoqueBaixo ?></div>
      <div class="stat-lbl">Itens com stock baixo</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon vermelho"><i class="fas fa-hourglass-half"></i></div>
    <div>
      <div class="stat-val"><?= $reqPendentes ?></div>
      <div class="stat-lbl">Requisições pendentes</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon azul"><i class="fas fa-arrow-right-arrow-left"></i></div>
    <div>
      <div class="stat-val"><?= $totalMovHoje ?></div>
      <div class="stat-lbl">Movimentações hoje</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:24px;">

  <!-- Requisições Recentes -->
  <div class="card">
    <div class="card-title">
      <i class="fas fa-clipboard-list" style="color:var(--verde)"></i>
      Requisições Recentes
      <a href="<?= $role === 'requisitante' ? 'minhas_requisicoes.php' : 'gestao_requisicoes.php' ?>"
         style="margin-left:auto;font-size:0.8rem;color:var(--verde);font-weight:600;text-decoration:none">
        Ver todas →
      </a>
    </div>
    <?php if (empty($requisicoes)): ?>
      <p style="color:var(--sub);font-size:0.9rem;text-align:center;padding:24px">Nenhuma requisição encontrada.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Número</th>
          <th>Requisitante</th>
          <th>Setor</th>
          <th>Estado</th>
          <th>Data</th>
        </tr></thead>
        <tbody>
        <?php foreach ($requisicoes as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['numero']) ?></strong></td>
            <td><?= htmlspecialchars($r['req_nome']) ?></td>
            <td><?= htmlspecialchars($r['setor'] ?? '—') ?></td>
            <td>
              <span class="badge badge-<?= $r['estado'] ?>">
                <?= ucfirst($r['estado']) ?>
              </span>
            </td>
            <td style="color:var(--sub);font-size:0.82rem">
              <?= date('d/m/Y', strtotime($r['criado_em'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Estoque Crítico -->
  <div class="card">
    <div class="card-title">
      <i class="fas fa-circle-exclamation" style="color:#d97706"></i>
      Alertas de Estoque
    </div>
    <?php if (empty($estoqueCritico)): ?>
      <p style="color:var(--sub);font-size:0.9rem;text-align:center;padding:24px">
        <i class="fas fa-check-circle" style="color:var(--verde)"></i><br>
        Nenhum item em nível crítico.
      </p>
    <?php else: ?>
      <div style="display:grid;gap:10px;">
        <?php foreach ($estoqueCritico as $e): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:10px 14px;background:var(--bg);border-radius:10px;">
          <div>
            <div style="font-weight:600;font-size:0.88rem"><?= htmlspecialchars($e['nome']) ?></div>
            <div style="font-size:0.75rem;color:var(--sub)">Mín: <?= $e['quantidade_minima'] ?> <?= $e['unidade'] ?></div>
          </div>
          <span class="badge <?= $e['quantidade'] == 0 ? 'badge-critico' : 'badge-baixo' ?>">
            <?= $e['quantidade'] ?> <?= $e['unidade'] ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (in_array($role, ['admin','gestor'])): ?>
      <a href="estoque.php" class="btn btn-ghost btn-sm" style="margin-top:14px;width:100%;justify-content:center">
        <i class="fas fa-boxes-stacked"></i> Gerir Estoque
      </a>
    <?php endif; ?>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
