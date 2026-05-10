<?php
require_once 'config.php';
requireRole(['admin']);
$currentPage = 'utilizadores';
$pageTitle   = 'Gestão de Utilizadores';

$db  = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome    = trim($_POST['nome'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $senha   = $_POST['senha'] ?? '';
        $role_id = (int)($_POST['role_id'] ?? 3);
        $unidade = trim($_POST['unidade'] ?? '');

        if ($nome && $email && $senha) {
            try {
                $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost'=>12]);
                $db->prepare("INSERT INTO utilizadores (nome, email, senha, role_id, unidade) VALUES (?,?,?,?,?)")
                   ->execute([$nome, $email, $hash, $role_id, $unidade ?: null]);
                logAction("Utilizador criado: $email");
                $msg = "Utilizador \"$nome\" criado com sucesso.";
            } catch (Exception $e) {
                $err = "Email já existe ou dados inválidos.";
            }
        } else { $err = "Nome, email e senha são obrigatórios."; }

    } elseif ($acao === 'toggle') {
        $id  = (int)$_POST['id'];
        $est = $db->prepare("SELECT estado FROM utilizadores WHERE id=?");
        $est->execute([$id]); $row = $est->fetch();
        if ($row) {
            $novo = $row['estado'] === 'ativo' ? 'inativo' : 'ativo';
            $db->prepare("UPDATE utilizadores SET estado=? WHERE id=?")->execute([$novo, $id]);
            logAction("Estado do utilizador id=$id alterado para $novo");
            $msg = "Estado do utilizador actualizado.";
        }
    }
}

$roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$lista = $db->query(
    "SELECT u.*, r.nome AS role_nome
     FROM utilizadores u JOIN roles r ON r.id=u.role_id
     ORDER BY u.criado_em DESC"
)->fetchAll();

include 'partials/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px">
  <button class="btn btn-primary" onclick="document.getElementById('modalNovo').classList.add('open')">
    <i class="fas fa-user-plus"></i> Novo Utilizador
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Nome</th><th>Email</th><th>Papel</th><th>Unidade</th><th>Estado</th><th>Criado em</th><th>Acção</th>
      </tr></thead>
      <tbody>
      <?php foreach ($lista as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;background:var(--verde);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.85rem;flex-shrink:0">
                <?= strtoupper(mb_substr($u['nome'],0,1)) ?>
              </div>
              <?= htmlspecialchars($u['nome']) ?>
            </div>
          </td>
          <td style="font-size:0.85rem"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge badge-ok"><?= htmlspecialchars($u['role_nome']) ?></span></td>
          <td style="font-size:0.85rem;color:var(--sub)"><?= htmlspecialchars($u['unidade']??'—') ?></td>
          <td>
            <span class="badge <?= $u['estado']==='ativo'?'badge-realizada':'badge-perda' ?>">
              <?= $u['estado'] ?>
            </span>
          </td>
          <td style="font-size:0.8rem;color:var(--sub)"><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
          <td>
            <?php if ($u['id'] != currentUser()['id']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="acao" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                onclick="return confirm('Alterar estado deste utilizador?')">
                <?= $u['estado']==='ativo' ? '<i class="fas fa-ban"></i>' : '<i class="fas fa-check"></i>' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Novo Utilizador -->
<div class="modal-overlay" id="modalNovo">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-user-plus" style="color:var(--verde)"></i> Novo Utilizador</h3>
      <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="acao" value="criar">
      <div class="form-row cols-2">
        <div class="form-group" style="grid-column:1/-1">
          <label>Nome completo *</label>
          <input type="text" name="nome" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required placeholder="utilizador@mec.gov.mz">
        </div>
        <div class="form-group">
          <label>Palavra-passe *</label>
          <input type="password" name="senha" required minlength="6">
        </div>
        <div class="form-group">
          <label>Papel *</label>
          <select name="role_id" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Unidade Orgânica</label>
          <input type="text" name="unidade" placeholder="Ex: Direcção de TIC">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Criar Utilizador</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
</script>

<?php include 'partials/footer.php'; ?>
