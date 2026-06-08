<?php
require_once 'config.php';
requireRole(['admin','gestor']);
$currentPage = 'estoque';
$pageTitle   = 'Gestão de Estoque';

$db  = db();
$msg = '';
$err = '';

// ── Entrada de stock (POST) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao         = $_POST['acao'] ?? '';
    $consumivel_id= (int)($_POST['consumivel_id'] ?? 0);
    $quantidade   = (int)($_POST['quantidade'] ?? 0);
    $referencia   = trim($_POST['referencia'] ?? '');

    if ($acao === 'entrada' && $consumivel_id && $quantidade > 0) {
        $db->prepare("UPDATE estoque SET quantidade = quantidade + ? WHERE consumivel_id = ?")
           ->execute([$quantidade, $consumivel_id]);
        $db->prepare("INSERT INTO movimentacoes (consumivel_id, tipo, quantidade, referencia, utilizador_id)
                      VALUES (?,?,?,?,?)")
           ->execute([$consumivel_id, 'entrada', $quantidade, $referencia ?: null, currentUser()['id']]);
        logAction("Entrada de estoque: consumivel_id=$consumivel_id, qtd=$quantidade");
        $msg = "Entrada registada com sucesso.";

    } elseif ($acao === 'atualizar_min' && $consumivel_id) {
        $min = (int)($_POST['quantidade_minima'] ?? 0);
        $db->prepare("UPDATE estoque SET quantidade_minima=? WHERE consumivel_id=?")
           ->execute([$min, $consumivel_id]);
        $msg = "Quantidade mínima atualizada.";
    }
}

// ── Listar estoque ────────────────────────────────────────────────────────────
$estoque = $db->query(
    "SELECT e.*, c.nome, c.unidade, c.codigo, c.descricao
     FROM estoque e
     JOIN consumiveis c ON c.id = e.consumivel_id
     ORDER BY c.nome ASC"
)->fetchAll();

$consumiveis = $db->query("SELECT id, nome, unidade FROM consumiveis ORDER BY nome")->fetchAll();

include 'partials/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <p style="color:var(--sub);font-size:0.9rem"><?= count($estoque) ?> tipo(s) de consumível em stock</p>
  <button class="btn btn-primary" onclick="document.getElementById('modalEntrada').classList.add('open')">
    <i class="fas fa-plus"></i> Registar Entrada
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Consumível</th><th>Código</th><th>Stock Actual</th>
        <th>Stock Mínimo</th><th>Unidade</th><th>Status</th><th>Atualizado</th><th>Ações</th>
      </tr></thead>
      <tbody>
      <?php foreach ($estoque as $e):
        $status = $e['quantidade'] == 0 ? 'critico' : ($e['quantidade'] <= $e['quantidade_minima'] ? 'baixo' : 'ok');
        $statusLabel = ['critico'=>'Sem stock','baixo'=>'Stock baixo','ok'=>'Disponível'][$status];
      ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($e['nome']) ?></strong>
            <?php if ($e['descricao']): ?>
              <div style="font-size:0.75rem;color:var(--sub)"><?= htmlspecialchars(mb_substr($e['descricao'],0,50)) ?></div>
            <?php endif; ?>
          </td>
          <td><code style="font-size:0.8rem;background:var(--bg);padding:2px 7px;border-radius:5px"><?= htmlspecialchars($e['codigo']) ?></code></td>
          <td><strong style="font-size:1.05rem"><?= $e['quantidade'] ?></strong></td>
          <td><?= $e['quantidade_minima'] ?></td>
          <td><?= htmlspecialchars($e['unidade']) ?></td>
          <td><span class="badge badge-<?= $status ?>"><?= $statusLabel ?></span></td>
          <td style="font-size:0.8rem;color:var(--sub)"><?= date('d/m/Y', strtotime($e['atualizado_em'])) ?></td>
          <td>
            <button class="btn btn-ghost btn-sm"
              onclick="openMinModal(<?= $e['consumivel_id'] ?>, <?= $e['quantidade_minima'] ?>, '<?= htmlspecialchars(addslashes($e['nome'])) ?>')">
              <i class="fas fa-sliders"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Entrada -->
<div class="modal-overlay" id="modalEntrada">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-arrow-up" style="color:var(--verde)"></i> Registar Entrada de Stock</h3>
      <button class="modal-close" onclick="document.getElementById('modalEntrada').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="acao" value="entrada">
      <div class="form-row cols-2">
        <div class="form-group" style="grid-column:1/-1">
          <label>Consumível *</label>
          <select name="consumivel_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($consumiveis as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?> (<?= $c['unidade'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quantidade *</label>
          <input type="number" name="quantidade" min="1" required placeholder="0">
        </div>
        <div class="form-group">
          <label>Referência (ex: Factura nº)</label>
          <input type="text" name="referencia" placeholder="Opcional">
        </div>
      </div>
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalEntrada').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Confirmar Entrada</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Mínimo -->
<div class="modal-overlay" id="modalMin">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalMinTitle">Definir Stock Mínimo</h3>
      <button class="modal-close" onclick="document.getElementById('modalMin').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="acao" value="atualizar_min">
      <input type="hidden" name="consumivel_id" id="modalMinId">
      <div class="form-group">
        <label>Stock Mínimo de Alerta</label>
        <input type="number" name="quantidade_minima" id="modalMinVal" min="0" required>
      </div>
      <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalMin').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function openMinModal(id, min, nome) {
  document.getElementById('modalMinId').value = id;
  document.getElementById('modalMinVal').value = min;
  document.getElementById('modalMinTitle').textContent = 'Stock Mínimo — ' + nome;
  document.getElementById('modalMin').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if(e.target === m) m.classList.remove('open'); });
});
</script>

<?php include 'partials/footer.php'; ?>
