<?php
require_once 'config.php';
requireRole(['admin','gestor']);
$currentPage = 'consumiveis';
$pageTitle   = 'Catálogo de Consumíveis';

$db  = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome    = trim($_POST['nome'] ?? '');
        $desc    = trim($_POST['descricao'] ?? '');
        $unidade = trim($_POST['unidade'] ?? '');
        $codigo  = strtoupper(trim($_POST['codigo'] ?? ''));
        $qmin    = (int)($_POST['quantidade_minima'] ?? 10);

        if ($nome && $unidade) {
            try {
                $db->prepare("INSERT INTO consumiveis (nome, descricao, unidade, codigo) VALUES (?,?,?,?)")
                   ->execute([$nome, $desc ?: null, $unidade, $codigo ?: null]);
                $cid = $db->lastInsertId();
                $db->prepare("INSERT INTO estoque (consumivel_id, quantidade, quantidade_minima) VALUES (?,0,?)")
                   ->execute([$cid, $qmin]);
                logAction("Consumível criado: $nome (id=$cid)");
                $msg = "Consumível \"$nome\" adicionado ao catálogo.";
            } catch (Exception $e) {
                $err = "Erro ao criar: código já existe ou dados inválidos.";
            }
        } else { $err = "Nome e Unidade são obrigatórios."; }
    }

    elseif ($acao === 'editar') {
        $id      = (int)$_POST['id'];
        $nome    = trim($_POST['nome'] ?? '');
        $desc    = trim($_POST['descricao'] ?? '');
        $unidade = trim($_POST['unidade'] ?? '');
        $codigo  = strtoupper(trim($_POST['codigo'] ?? ''));
        if ($nome && $unidade) {
            $db->prepare("UPDATE consumiveis SET nome=?, descricao=?, unidade=?, codigo=? WHERE id=?")
               ->execute([$nome, $desc ?: null, $unidade, $codigo ?: null, $id]);
            logAction("Consumível editado: id=$id");
            $msg = "Consumível actualizado.";
        }
    }
}

$lista = $db->query(
    "SELECT c.*, e.quantidade, e.quantidade_minima
     FROM consumiveis c
     LEFT JOIN estoque e ON e.consumivel_id = c.id
     ORDER BY c.nome ASC"
)->fetchAll();

include 'partials/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px">
  <button class="btn btn-primary" onclick="document.getElementById('modalNovo').classList.add('open')">
    <i class="fas fa-plus"></i> Novo Consumível
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Nome</th><th>Código</th><th>Unidade</th><th>Stock</th><th>Stock Mín.</th><th>Criado em</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($lista as $c): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($c['nome']) ?></strong>
            <?php if ($c['descricao']): ?><div style="font-size:0.75rem;color:var(--sub)"><?= htmlspecialchars(mb_substr($c['descricao'],0,55)) ?></div><?php endif; ?>
          </td>
          <td><?= $c['codigo'] ? "<code style='font-size:0.78rem;background:var(--bg);padding:2px 6px;border-radius:5px'>".htmlspecialchars($c['codigo'])."</code>" : '—' ?></td>
          <td><?= htmlspecialchars($c['unidade']) ?></td>
          <td><?= $c['quantidade'] ?? '—' ?></td>
          <td><?= $c['quantidade_minima'] ?? '—' ?></td>
          <td style="font-size:0.8rem;color:var(--sub)"><?= date('d/m/Y', strtotime($c['criado_em'])) ?></td>
          <td>
            <button class="btn btn-ghost btn-sm"
              onclick="editarItem(<?= htmlspecialchars(json_encode($c)) ?>)">
              <i class="fas fa-pen"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Novo -->
<div class="modal-overlay" id="modalNovo">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-plus-circle" style="color:var(--verde)"></i> Novo Consumível</h3>
      <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="acao" value="criar">
      <div class="form-row cols-2">
        <div class="form-group" style="grid-column:1/-1">
          <label>Nome *</label>
          <input type="text" name="nome" required placeholder="Ex: Papel A4 80g">
        </div>
        <div class="form-group">
          <label>Código</label>
          <input type="text" name="codigo" placeholder="Ex: PAP-A4-80" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label>Unidade *</label>
          <select name="unidade" required>
            <option value="">Selecione...</option>
            <option value="Unidade">Unidade</option>
            <option value="Caixa">Caixa</option>
            <option value="Resma">Resma</option>
            <option value="Litro">Litro</option>
            <option value="Par">Par</option>
            <option value="Pacote">Pacote</option>
          </select>
        </div>
        <div class="form-group">
          <label>Stock Mínimo de Alerta</label>
          <input type="number" name="quantidade_minima" value="10" min="0">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Descrição</label>
          <textarea name="descricao" placeholder="Descrição opcional..."></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal-overlay" id="modalEditar">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-pen" style="color:var(--verde)"></i> Editar Consumível</h3>
      <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="acao" value="editar">
      <input type="hidden" name="id" id="editId">
      <div class="form-row cols-2">
        <div class="form-group" style="grid-column:1/-1">
          <label>Nome *</label>
          <input type="text" name="nome" id="editNome" required>
        </div>
        <div class="form-group">
          <label>Código</label>
          <input type="text" name="codigo" id="editCodigo" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label>Unidade *</label>
          <select name="unidade" id="editUnidade" required>
            <option value="Unidade">Unidade</option>
            <option value="Caixa">Caixa</option>
            <option value="Resma">Resma</option>
            <option value="Litro">Litro</option>
            <option value="Par">Par</option>
            <option value="Pacote">Pacote</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Descrição</label>
          <textarea name="descricao" id="editDesc"></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
      </div>
    </form>
  </div>
</div>

<script>
function editarItem(c) {
  document.getElementById('editId').value     = c.id;
  document.getElementById('editNome').value   = c.nome;
  document.getElementById('editCodigo').value = c.codigo || '';
  document.getElementById('editDesc').value   = c.descricao || '';
  document.getElementById('editUnidade').value= c.unidade;
  document.getElementById('modalEditar').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
</script>

<?php include 'partials/footer.php'; ?>
