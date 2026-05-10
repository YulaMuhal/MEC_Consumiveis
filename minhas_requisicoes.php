<?php
require_once 'config.php';
requireLogin();
$currentPage = 'minhas_req';
$pageTitle   = 'Minhas Requisições';

$db   = db();
$user = currentUser();

// Filtros
$busca   = trim($_GET['q'] ?? '');
$estado  = $_GET['estado'] ?? '';
$periodo = $_GET['periodo'] ?? '';

$where = $user['role'] === 'requisitante' ? "WHERE r.utilizador_id = {$user['id']}" : "WHERE 1=1";
$params = [];

if ($busca) {
    $where .= " AND (r.numero LIKE ? OR r.setor LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%";
}
if ($estado) {
    $where .= " AND r.estado = ?"; $params[] = $estado;
}
if ($periodo === 'hoje') {
    $where .= " AND DATE(r.criado_em) = CURDATE()";
} elseif ($periodo === 'semana') {
    $where .= " AND r.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($periodo === 'mes') {
    $where .= " AND r.criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$stmt = $db->prepare(
    "SELECT r.*, u.nome AS req_nome
     FROM requisicoes r
     JOIN utilizadores u ON u.id = r.utilizador_id
     $where
     ORDER BY r.criado_em DESC"
);
$stmt->execute($params);
$requisicoes = $stmt->fetchAll();

// Detalhes de uma requisição específica
$detalhe = null;
if (!empty($_GET['ver'])) {
    $stmtD = $db->prepare(
        "SELECT r.*, u.nome AS req_nome, u.unidade
         FROM requisicoes r JOIN utilizadores u ON u.id=r.utilizador_id
         WHERE r.id=?"
    );
    $stmtD->execute([(int)$_GET['ver']]);
    $detalhe = $stmtD->fetch();
    if ($detalhe) {
        $itens = $db->prepare(
            "SELECT ri.quantidade, c.nome, c.codigo, c.unidade
             FROM requisicao_itens ri JOIN consumiveis c ON c.id=ri.consumivel_id
             WHERE ri.requisicao_id=?"
        );
        $itens->execute([$detalhe['id']]);
        $detalhe['itens'] = $itens->fetchAll();
    }
}

include 'partials/header.php';
?>

<!-- Filtros -->
<div class="card" style="margin-bottom:20px;padding:16px 24px">
  <form method="GET" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="flex:2;min-width:180px;margin:0">
      <label style="margin-bottom:6px">Pesquisar</label>
      <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Número ou setor...">
    </div>
    <div class="form-group" style="flex:1;min-width:140px;margin:0">
      <label style="margin-bottom:6px">Estado</label>
      <select name="estado">
        <option value="">Todos</option>
        <option value="pendente" <?= $estado==='pendente'?'selected':'' ?>>Pendente</option>
        <option value="realizada" <?= $estado==='realizada'?'selected':'' ?>>Realizada</option>
        <option value="perda" <?= $estado==='perda'?'selected':'' ?>>Perda</option>
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:140px;margin:0">
      <label style="margin-bottom:6px">Período</label>
      <select name="periodo">
        <option value="">Todos</option>
        <option value="hoje" <?= $periodo==='hoje'?'selected':'' ?>>Hoje</option>
        <option value="semana" <?= $periodo==='semana'?'selected':'' ?>>Últimos 7 dias</option>
        <option value="mes" <?= $periodo==='mes'?'selected':'' ?>>Último mês</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary" style="height:44px">
      <i class="fas fa-search"></i> Filtrar
    </button>
    <?php if ($busca || $estado || $periodo): ?>
      <a href="minhas_requisicoes.php" class="btn btn-ghost" style="height:44px">Limpar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-title">
    <i class="fas fa-clipboard-list" style="color:var(--verde)"></i>
    <?= count($requisicoes) ?> requisição(ões) encontrada(s)
    <a href="requisicoes.php" class="btn btn-primary btn-sm" style="margin-left:auto">
      <i class="fas fa-plus"></i> Nova
    </a>
  </div>

  <?php if (empty($requisicoes)): ?>
    <p style="text-align:center;color:var(--sub);padding:32px">Nenhuma requisição encontrada.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Número</th>
        <th>Requisitante</th>
        <th>Setor</th>
        <th>Estado</th>
        <th>Data</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($requisicoes as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['numero']) ?></strong></td>
          <td><?= htmlspecialchars($r['req_nome']) ?></td>
          <td><?= htmlspecialchars($r['setor'] ?? '—') ?></td>
          <td><span class="badge badge-<?= $r['estado'] ?>"><?= ucfirst($r['estado']) ?></span></td>
          <td style="font-size:0.82rem;color:var(--sub)"><?= date('d/m/Y H:i', strtotime($r['criado_em'])) ?></td>
          <td>
            <a href="?ver=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">
              <i class="fas fa-eye"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($detalhe): ?>
<div class="modal-overlay open" id="modalDetalhe">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-file-alt" style="color:var(--verde)"></i> <?= htmlspecialchars($detalhe['numero']) ?></h3>
      <a href="minhas_requisicoes.php" class="modal-close">×</a>
    </div>
    <table style="width:100%;font-size:0.9rem;border-collapse:collapse;margin-bottom:16px">
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub);width:140px">Estado</td>
          <td><span class="badge badge-<?= $detalhe['estado'] ?>"><?= ucfirst($detalhe['estado']) ?></span></td></tr>
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub)">Setor</td><td><?= htmlspecialchars($detalhe['setor']??'—') ?></td></tr>
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub)">Requisitante</td><td><?= htmlspecialchars($detalhe['req_nome']) ?></td></tr>
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub)">Unidade</td><td><?= htmlspecialchars($detalhe['unidade']??'—') ?></td></tr>
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub)">Justificativa</td><td><?= htmlspecialchars($detalhe['justificativa']??'—') ?></td></tr>
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub)">Data</td><td><?= date('d/m/Y H:i', strtotime($detalhe['criado_em'])) ?></td></tr>
      <?php if ($detalhe['observacao']): ?>
      <tr><td style="padding:7px 0;font-weight:600;color:var(--sub)">Observação</td><td><?= htmlspecialchars($detalhe['observacao']) ?></td></tr>
      <?php endif; ?>
    </table>
    <div style="font-weight:700;font-size:0.9rem;margin-bottom:10px">Itens Requisitados</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Código</th><th>Qtd.</th><th>Und.</th></tr></thead>
        <tbody>
        <?php foreach ($detalhe['itens'] as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['nome']) ?></td>
            <td><code style="font-size:0.78rem;background:var(--bg);padding:2px 6px;border-radius:5px"><?= htmlspecialchars($it['codigo']) ?></code></td>
            <td><strong><?= $it['quantidade'] ?></strong></td>
            <td><?= htmlspecialchars($it['unidade']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:20px;text-align:right">
      <a href="minhas_requisicoes.php" class="btn btn-ghost">Fechar</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>
