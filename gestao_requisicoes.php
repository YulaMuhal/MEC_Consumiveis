<?php
require_once 'config.php';
requireRole(['admin','gestor']);
$currentPage = 'gestao_req';
$pageTitle   = 'Gerir Requisições';

$db   = db();
$user = currentUser();
$msg  = '';
$err  = '';

// ── Processar acção ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqId = (int)($_POST['req_id'] ?? 0);
    $acao  = $_POST['acao_req'] ?? '';
    $obs   = trim($_POST['observacao'] ?? '');

    if ($reqId && in_array($acao, ['realizada','perda'])) {
        $req = $db->prepare("SELECT * FROM requisicoes WHERE id=? AND estado='pendente'");
        $req->execute([$reqId]);
        $requisicao = $req->fetch();

        if (!$requisicao) { $err = 'Requisição não encontrada ou já processada.'; }
        else {
            if ($acao === 'realizada') {
                // Verificar e subtrair stock
                $itens = $db->prepare(
                    "SELECT ri.*, c.nome FROM requisicao_itens ri JOIN consumiveis c ON c.id=ri.consumivel_id WHERE ri.requisicao_id=?"
                );
                $itens->execute([$reqId]);
                $itensList = $itens->fetchAll();

                $semStock = [];
                foreach ($itensList as $it) {
                    $stock = $db->prepare("SELECT quantidade FROM estoque WHERE consumivel_id=?");
                    $stock->execute([$it['consumivel_id']]);
                    $s = $stock->fetchColumn();
                    if ($s < $it['quantidade']) $semStock[] = $it['nome'];
                }

                if (!empty($semStock)) {
                    $err = 'Stock insuficiente para: ' . implode(', ', $semStock);
                } else {
                    foreach ($itensList as $it) {
                        $db->prepare("UPDATE estoque SET quantidade=quantidade-? WHERE consumivel_id=?")
                           ->execute([$it['quantidade'], $it['consumivel_id']]);
                        $db->prepare("INSERT INTO movimentacoes (consumivel_id, tipo, quantidade, referencia, utilizador_id) VALUES (?,?,?,?,?)")
                           ->execute([$it['consumivel_id'], 'saida', $it['quantidade'], "Req: {$requisicao['numero']}", $user['id']]);
                    }
                    $db->prepare("UPDATE requisicoes SET estado='realizada', observacao=? WHERE id=?")
                       ->execute([$obs, $reqId]);
                    logAction("Requisição realizada: {$requisicao['numero']}");
                    $msg = "Requisição {$requisicao['numero']} marcada como Realizada. Stock actualizado.";
                }
            } else {
                // Perda
                $db->prepare("UPDATE requisicoes SET estado='perda', observacao=? WHERE id=?")
                   ->execute([$obs ?: 'Não efectuada', $reqId]);
                logAction("Requisição marcada como perda: {$requisicao['numero']}");
                $msg = "Requisição {$requisicao['numero']} marcada como Perda/Não efectuada.";
            }
        }
    }
}

// ── Listar ────────────────────────────────────────────────────────────────────
$filtroEstado = $_GET['estado'] ?? '';
$where = $filtroEstado ? "WHERE r.estado='$filtroEstado'" : '';
$requisicoes  = $db->query(
    "SELECT r.*, u.nome AS req_nome, u.unidade AS req_unidade,
            (SELECT COUNT(*) FROM requisicao_itens ri WHERE ri.requisicao_id=r.id) AS num_itens
     FROM requisicoes r JOIN utilizadores u ON u.id=r.utilizador_id
     $where ORDER BY r.criado_em DESC"
)->fetchAll();

include 'partials/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Filtro tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ([''=>'Todas','pendente'=>'Pendentes','realizada'=>'Realizadas','perda'=>'Perdas'] as $v=>$l): ?>
    <a href="?estado=<?= $v ?>" class="btn <?= $filtroEstado===$v?'btn-primary':'btn-ghost' ?> btn-sm">
      <?= $l ?> <?php
        $cnt = $db->query("SELECT COUNT(*) FROM requisicoes" . ($v ? " WHERE estado='$v'" : ""))->fetchColumn();
        if ($cnt > 0) echo "<span style='background:rgba(255,255,255,0.25);padding:1px 7px;border-radius:99px;font-size:0.75rem;margin-left:4px'>$cnt</span>";
      ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Número</th><th>Requisitante</th><th>Unidade</th><th>Setor</th>
        <th>Itens</th><th>Estado</th><th>Data</th><th>Acções</th>
      </tr></thead>
      <tbody>
      <?php if (empty($requisicoes)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--sub)">Nenhuma requisição encontrada.</td></tr>
      <?php else: ?>
      <?php foreach ($requisicoes as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['numero']) ?></strong></td>
          <td><?= htmlspecialchars($r['req_nome']) ?></td>
          <td style="font-size:0.82rem;color:var(--sub)"><?= htmlspecialchars($r['req_unidade']??'—') ?></td>
          <td><?= htmlspecialchars($r['setor']??'—') ?></td>
          <td><?= $r['num_itens'] ?> item(ns)</td>
          <td><span class="badge badge-<?= $r['estado'] ?>"><?= ucfirst($r['estado']) ?></span></td>
          <td style="font-size:0.8rem;color:var(--sub)"><?= date('d/m/Y H:i', strtotime($r['criado_em'])) ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn btn-ghost btn-sm"
              onclick="verDetalhe(<?= htmlspecialchars(json_encode($r)) ?>)">
              <i class="fas fa-eye"></i>
            </button>
            <?php if ($r['estado'] === 'pendente'): ?>
            <button class="btn btn-primary btn-sm"
              onclick="abrirProcessar(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['numero'])) ?>')">
              <i class="fas fa-check"></i> Processar
            </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Processar -->
<div class="modal-overlay" id="modalProcessar">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="procTitulo">Processar Requisição</h3>
      <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="req_id" id="procId">
      <input type="hidden" name="acao_req" id="procAcao">
      <div class="form-group">
        <label>Observação (opcional)</label>
        <textarea name="observacao" placeholder="Notas sobre esta acção..."></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
        <button type="button" class="btn btn-danger" onclick="definirAcao('perda')">
          <i class="fas fa-times-circle"></i> Marcar como Perda
        </button>
        <button type="button" class="btn btn-primary" onclick="definirAcao('realizada')">
          <i class="fas fa-check-circle"></i> Marcar como Realizada
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detalhe (read-only) -->
<div class="modal-overlay" id="modalDetalhe">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Detalhe da Requisição</h3>
      <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">×</button>
    </div>
    <div id="detalheContent"></div>
  </div>
</div>

<script>
function abrirProcessar(id, numero) {
  document.getElementById('procId').value = id;
  document.getElementById('procTitulo').textContent = 'Processar: ' + numero;
  document.getElementById('modalProcessar').classList.add('open');
}
function definirAcao(acao) {
  document.getElementById('procAcao').value = acao;
  document.getElementById('modalProcessar').querySelector('form').submit();
}
async function verDetalhe(r) {
  const res = await fetch('api_itens_req.php?id=' + r.id);
  const itens = await res.json();
  let html = `<table style="width:100%;font-size:0.9rem;border-collapse:collapse;margin-bottom:16px">
    <tr><td style="padding:6px 0;font-weight:600;color:var(--sub);width:130px">Número</td><td><strong>${r.numero}</strong></td></tr>
    <tr><td style="padding:6px 0;font-weight:600;color:var(--sub)">Estado</td><td><span class="badge badge-${r.estado}">${r.estado.charAt(0).toUpperCase()+r.estado.slice(1)}</span></td></tr>
    <tr><td style="padding:6px 0;font-weight:600;color:var(--sub)">Setor</td><td>${r.setor||'—'}</td></tr>
    <tr><td style="padding:6px 0;font-weight:600;color:var(--sub)">Justificativa</td><td>${r.justificativa||'—'}</td></tr>
    ${r.observacao?`<tr><td style="padding:6px 0;font-weight:600;color:var(--sub)">Observação</td><td>${r.observacao}</td></tr>`:''}
  </table>
  <div style="font-weight:700;margin-bottom:8px">Itens</div>
  <table style="width:100%;font-size:0.85rem;border-collapse:collapse">
    <thead><tr style="background:var(--bg)"><th style="padding:8px;text-align:left">Item</th><th style="padding:8px;text-align:left">Qtd</th><th style="padding:8px;text-align:left">Und</th></tr></thead>
    <tbody>${itens.map(i=>`<tr><td style="padding:7px 8px">${i.nome}</td><td style="padding:7px 8px">${i.quantidade}</td><td style="padding:7px 8px">${i.unidade}</td></tr>`).join('')}</tbody>
  </table>`;
  document.getElementById('detalheContent').innerHTML = html;
  document.getElementById('modalDetalhe').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
</script>

<?php include 'partials/footer.php'; ?>
