<?php
require_once 'config.php';
requireRole(['admin','gestor']);
$currentPage = 'movimentacoes';
$pageTitle   = 'Movimentações de Estoque';

$db = db();

$tipo    = $_GET['tipo'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$busca   = trim($_GET['q'] ?? '');

$where  = "WHERE 1=1";
$params = [];

if ($tipo) { $where .= " AND m.tipo=?"; $params[] = $tipo; }
if ($busca) { $where .= " AND c.nome LIKE ?"; $params[] = "%$busca%"; }
if ($periodo === 'hoje') $where .= " AND DATE(m.criado_em)=CURDATE()";
elseif ($periodo === 'semana') $where .= " AND m.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
elseif ($periodo === 'mes')    $where .= " AND m.criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

$perPage  = 50;
$page     = max(1, (int)($_GET['pagina'] ?? 1));
$countStmt = $db->prepare("SELECT COUNT(*) FROM movimentacoes m JOIN consumiveis c ON c.id=m.consumivel_id $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = $db->prepare(
    "SELECT m.*, c.nome AS consumivel_nome, c.unidade, u.nome AS user_nome
     FROM movimentacoes m
     JOIN consumiveis c ON c.id = m.consumivel_id
     LEFT JOIN utilizadores u ON u.id = m.utilizador_id
     $where
     ORDER BY m.criado_em DESC LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $pag['perPage'], $pag['offset']]);
$movs = $stmt->fetchAll();

// URL base para paginação preservando filtros activos
$filtroQuery = http_build_query(array_filter(['q'=>$busca,'tipo'=>$tipo,'periodo'=>$periodo]));
$baseUrlPag  = 'movimentacoes.php' . ($filtroQuery ? '?' . $filtroQuery : '');

include 'partials/header.php';
?>

<!-- Filtros -->
<div class="card" style="margin-bottom:20px;padding:16px 24px">
  <form method="GET" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="flex:2;min-width:160px;margin:0">
      <label style="margin-bottom:6px">Consumível</label>
      <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome...">
    </div>
    <div class="form-group" style="flex:1;min-width:130px;margin:0">
      <label style="margin-bottom:6px">Tipo</label>
      <select name="tipo">
        <option value="">Todos</option>
        <option value="entrada" <?= $tipo==='entrada'?'selected':'' ?>>Entrada</option>
        <option value="saida"   <?= $tipo==='saida'  ?'selected':'' ?>>Saída</option>
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:130px;margin:0">
      <label style="margin-bottom:6px">Período</label>
      <select name="periodo">
        <option value="">Todos</option>
        <option value="hoje" <?= $periodo==='hoje'?'selected':'' ?>>Hoje</option>
        <option value="semana" <?= $periodo==='semana'?'selected':'' ?>>7 dias</option>
        <option value="mes" <?= $periodo==='mes'?'selected':'' ?>>30 dias</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary" style="height:44px"><i class="fas fa-search"></i> Filtrar</button>
    <?php if ($busca||$tipo||$periodo): ?>
      <a href="movimentacoes.php" class="btn btn-ghost" style="height:44px">Limpar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-title">
    <i class="fas fa-arrow-right-arrow-left" style="color:var(--verde)"></i>
    Movimentações
    <span style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <span style="font-size:0.82rem;color:var(--sub);font-weight:400">Página <?= $pag['page'] ?> de <?= $pag['totalPages'] ?></span>
      <?php $exportParams = http_build_query(array_filter(['modulo'=>'movimentacoes','q'=>$busca,'tipo'=>$tipo,'periodo'=>$periodo])); ?>
      <a href="exportar.php?<?= $exportParams ?>&formato=csv" class="btn btn-ghost btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
      <a href="exportar.php?<?= $exportParams ?>&formato=pdf" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-print"></i> PDF</a>
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Consumível</th><th>Tipo</th><th>Quantidade</th><th>Referência</th><th>Utilizador</th><th>Data</th>
      </tr></thead>
      <tbody>
      <?php if (empty($movs)): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--sub)">Nenhuma movimentação encontrada.</td></tr>
      <?php else: ?>
      <?php foreach ($movs as $m): ?>
        <tr>
          <td><strong><?= htmlspecialchars($m['consumivel_nome']) ?></strong></td>
          <td>
            <?php if ($m['tipo'] === 'entrada'): ?>
              <span class="badge badge-ok"><i class="fas fa-arrow-up"></i> Entrada</span>
            <?php else: ?>
              <span class="badge badge-pendente"><i class="fas fa-arrow-down"></i> Saída</span>
            <?php endif; ?>
          </td>
          <td><strong><?= $m['quantidade'] ?></strong> <?= htmlspecialchars($m['unidade']) ?></td>
          <td style="font-size:0.85rem;color:var(--sub)"><?= htmlspecialchars($m['referencia'] ?? '—') ?></td>
          <td style="font-size:0.85rem"><?= htmlspecialchars($m['user_nome'] ?? '—') ?></td>
          <td style="font-size:0.8rem;color:var(--sub)"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= paginationHtml($pag, $baseUrlPag) ?>
</div>

<?php include 'partials/footer.php'; ?>
