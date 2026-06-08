<?php
require_once 'config.php';
requireRole(['admin']);
$currentPage = 'logs';
$pageTitle   = 'Logs de Auditoria';

$db = db();

$perPage = 50;
$page    = max(1, (int)($_GET['pagina'] ?? 1));
$total   = (int)$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$pag     = paginate($total, $perPage, $page);

$logs = $db->prepare(
    "SELECT l.*, u.nome AS user_nome, u.email
     FROM logs l
     LEFT JOIN utilizadores u ON u.id = l.utilizador_id
     ORDER BY l.criado_em DESC
     LIMIT ? OFFSET ?"
);
$logs->execute([$pag['perPage'], $pag['offset']]);
$logs = $logs->fetchAll();

include 'partials/header.php';
?>

<div class="card">
  <div class="card-title">
    <i class="fas fa-shield-halved" style="color:var(--verde)"></i>
    Logs de Auditoria
    <span style="margin-left:auto;font-size:0.82rem;color:var(--sub);font-weight:400">
      Página <?= $pag['page'] ?> de <?= $pag['totalPages'] ?>
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Data / Hora</th><th>Utilizador</th><th>Email</th><th>Acção</th><th>IP</th>
      </tr></thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--sub)">Nenhum registo encontrado.</td></tr>
      <?php else: ?>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-size:0.82rem;color:var(--sub);white-space:nowrap">
            <?= date('d/m/Y H:i:s', strtotime($l['criado_em'])) ?>
          </td>
          <td><?= htmlspecialchars($l['user_nome'] ?? 'Sistema') ?></td>
          <td style="font-size:0.82rem;color:var(--sub)"><?= htmlspecialchars($l['email'] ?? '—') ?></td>
          <td><?= htmlspecialchars($l['acao']) ?></td>
          <td style="font-size:0.82rem;color:var(--sub)"><?= htmlspecialchars($l['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= paginationHtml($pag, 'logs.php') ?>
</div>

<?php include 'partials/footer.php'; ?>
