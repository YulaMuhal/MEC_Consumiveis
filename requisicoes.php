<?php
require_once 'config.php';
requireLogin();
$currentPage = 'requisicoes';
$pageTitle   = 'Nova Requisição';

$db   = db();
$user = currentUser();

// ── Submeter via AJAX (JSON) ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE'])
    && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {

    header('Content-Type: application/json');
    $data  = json_decode(file_get_contents('php://input'), true);
    $setor = trim($data['setor'] ?? '');
    $just  = trim($data['justificativa'] ?? '');
    $itens = $data['itens'] ?? [];

    if (!$setor || empty($itens)) {
        echo json_encode(['ok'=>false,'erro'=>'Setor e itens são obrigatórios.']); exit;
    }

    // Gerar número único com AUTO_INCREMENT como base
    $numero = 'REQ-' . strtoupper(substr(preg_replace('/[^A-Z]/i','', $user['unidade'] ?: 'MEC'), 0, 3))
              . '-' . date('ymd') . '-' . str_pad(mt_rand(1,9999), 4, '0', STR_PAD_LEFT);

    try {
        $db->beginTransaction();

        // Verificar stock com lock para evitar race condition
        foreach ($itens as $item) {
            $cid = (int)($item['consumivel_id'] ?? 0);
            $qty = (int)($item['quantidade'] ?? 0);
            $row = $db->prepare("SELECT e.quantidade, c.nome FROM estoque e JOIN consumiveis c ON c.id=e.consumivel_id WHERE e.consumivel_id=? FOR UPDATE");
            $row->execute([$cid]);
            $stock = $row->fetch();
            if (!$stock || $stock['quantidade'] < $qty) {
                $db->rollBack();
                $nome = $stock['nome'] ?? 'item';
                $disp = $stock['quantidade'] ?? 0;
                echo json_encode(['ok'=>false,'erro'=>"Stock insuficiente para \"{$nome}\". Disponível: {$disp}."]); exit;
            }
        }

        // Inserir requisição
        $db->prepare(
            "INSERT INTO requisicoes (utilizador_id, numero, setor, justificativa, estado) VALUES (?,?,?,?,'pendente')"
        )->execute([$user['id'], $numero, $setor, $just]);
        $reqId = $db->lastInsertId();

        // Inserir itens
        foreach ($itens as $item) {
            $db->prepare("INSERT INTO requisicao_itens (requisicao_id, consumivel_id, quantidade) VALUES (?,?,?)")
               ->execute([$reqId, (int)$item['consumivel_id'], (int)$item['quantidade']]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['ok'=>false,'erro'=>'Erro interno. Tente novamente.']); exit;
    }

    logAction("Requisição submetida: $numero");
    echo json_encode(['ok'=>true,'numero'=>$numero]); exit;
}

// ── Catálogo disponível ───────────────────────────────────────────────────────
$catalogo = $db->query(
    "SELECT c.id, c.nome, c.codigo, c.unidade, e.quantidade AS stock
     FROM consumiveis c
     JOIN estoque e ON e.consumivel_id = c.id
     WHERE e.quantidade > 0
     ORDER BY c.nome ASC"
)->fetchAll();

include 'partials/header.php';
?>

<div style="max-width:820px;">

<!-- Wizard Bar -->
<div class="wizard-bar" id="wizardBar">
  <div class="wz-step active" id="ws1">
    <div class="wz-circle">1</div>
    <div class="wz-label">Dados gerais</div>
  </div>
  <div class="wz-line" id="wl1"></div>
  <div class="wz-step" id="ws2">
    <div class="wz-circle">2</div>
    <div class="wz-label">Itens</div>
  </div>
  <div class="wz-line" id="wl2"></div>
  <div class="wz-step" id="ws3">
    <div class="wz-circle">3</div>
    <div class="wz-label">Revisão</div>
  </div>
</div>

<!-- Alerta -->
<div class="alert" id="alertBox" style="display:none"></div>

<!-- ── Step 1 ── -->
<div class="card" id="step1">
  <div class="card-title"><i class="fas fa-pen-to-square" style="color:var(--verde)"></i> Informações da Requisição</div>
  <div class="form-row cols-2">
    <div class="form-group">
      <label>Número (auto-gerado)</label>
      <input type="text" id="numReq" readonly style="background:var(--bg);color:var(--sub)" value="A gerar automaticamente">
    </div>
    <div class="form-group">
      <label>Data</label>
      <input type="date" id="dataReq" readonly style="background:var(--bg);color:var(--sub)">
    </div>
    <div class="form-group">
      <label>Setor / Departamento *</label>
      <input type="text" id="setorReq" placeholder="Ex: Direcção de TIC" required>
    </div>
    <div class="form-group">
      <label>Unidade Orgânica</label>
      <input type="text" value="<?= htmlspecialchars($user['unidade']) ?>" readonly style="background:var(--bg);color:var(--sub)">
    </div>
    <div class="form-group" style="grid-column:1/-1">
      <label>Justificativa / Observações</label>
      <textarea id="justReq" placeholder="Descreva a necessidade desta requisição..."></textarea>
    </div>
  </div>
  <div style="display:flex;justify-content:flex-end">
    <button class="btn btn-primary" onclick="goStep(2)">Próximo <i class="fas fa-chevron-right"></i></button>
  </div>
</div>

<!-- ── Step 2 ── -->
<div class="card" id="step2" style="display:none">
  <div class="card-title"><i class="fas fa-boxes-stacked" style="color:var(--verde)"></i> Itens a Requisitar</div>

  <div style="display:flex;gap:12px;margin-bottom:18px;">
    <input type="text" id="searchItem" placeholder="Pesquisar consumível..." style="flex:1;padding:10px 14px;border:1.5px solid var(--borda);border-radius:10px;font-family:'DM Sans',sans-serif;outline:none">
    <button class="btn btn-primary" onclick="document.getElementById('modalCatalogo').classList.add('open')">
      <i class="fas fa-plus"></i> Adicionar Item
    </button>
  </div>

  <div class="table-wrap">
    <table id="tblItens">
      <thead><tr><th>Consumível</th><th>Código</th><th>Disponível</th><th>Qtd. Pedida</th><th>Und.</th><th></th></tr></thead>
      <tbody id="tbodyItens">
        <tr id="rowVazio"><td colspan="6" style="text-align:center;padding:32px;color:var(--sub)">
          <i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
          Nenhum item adicionado. Clique em "Adicionar Item".
        </td></tr>
      </tbody>
    </table>
  </div>

  <div style="display:flex;justify-content:space-between;margin-top:20px">
    <button class="btn btn-ghost" onclick="goStep(1)"><i class="fas fa-chevron-left"></i> Voltar</button>
    <button class="btn btn-primary" onclick="goStep(3)">Rever Requisição <i class="fas fa-chevron-right"></i></button>
  </div>
</div>

<!-- ── Step 3 ── -->
<div class="card" id="step3" style="display:none">
  <div class="card-title"><i class="fas fa-magnifying-glass" style="color:var(--verde)"></i> Revisão e Submissão</div>
  <div id="reviewContent" style="line-height:1.8;margin-bottom:24px"></div>
  <div style="display:flex;justify-content:space-between">
    <button class="btn btn-ghost" onclick="goStep(2)"><i class="fas fa-chevron-left"></i> Voltar</button>
    <button class="btn btn-danger" id="btnSubmit" onclick="submeter()">
      <i class="fas fa-paper-plane"></i> Submeter Requisição
    </button>
  </div>
</div>

<!-- ── Sucesso ── -->
<div class="card" id="stepSucesso" style="display:none;text-align:center;padding:48px 32px">
  <div style="font-size:3.5rem;color:var(--verde);margin-bottom:16px"><i class="fas fa-circle-check"></i></div>
  <h2 style="font-family:'DM Serif Display',serif;margin-bottom:8px">Requisição Submetida!</h2>
  <p id="successNum" style="font-size:1.1rem;color:var(--sub);margin-bottom:28px"></p>
  <div style="display:flex;gap:12px;justify-content:center">
    <a href="minhas_requisicoes.php" class="btn btn-ghost"><i class="fas fa-list"></i> Ver Minhas Requisições</a>
    <button class="btn btn-primary" onclick="resetWizard()"><i class="fas fa-plus"></i> Nova Requisição</button>
  </div>
</div>

</div><!-- /max-width -->

<!-- Modal Catálogo -->
<div class="modal-overlay" id="modalCatalogo">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header">
      <h3><i class="fas fa-list-ul" style="color:var(--verde)"></i> Catálogo de Consumíveis</h3>
      <button class="modal-close" onclick="document.getElementById('modalCatalogo').classList.remove('open')">×</button>
    </div>
    <input type="text" id="filterCatalogo" placeholder="Filtrar catálogo..."
      style="width:100%;padding:10px 14px;border:1.5px solid var(--borda);border-radius:10px;margin-bottom:16px;font-family:'DM Sans',sans-serif;outline:none"
      oninput="filterCat(this.value)">
    <div id="catalogoList" style="display:grid;gap:8px;max-height:360px;overflow-y:auto"></div>
  </div>
</div>

<script>
const CATALOGO = <?= json_encode(array_values($catalogo)) ?>;
let itemsReq = [];
let currentStep = 1;

// Init
document.getElementById('dataReq').valueAsDate = new Date();

function goStep(n) {
  if (n === 2 && currentStep === 1) {
    if (!document.getElementById('setorReq').value.trim()) {
      showAlert('Por favor preencha o Setor / Departamento.', 'warn'); return;
    }
  }
  if (n === 3 && currentStep === 2) {
    if (itemsReq.length === 0) {
      showAlert('Adicione pelo menos um item à requisição.', 'warn'); return;
    }
    buildReview();
  }
  ['step1','step2','step3','stepSucesso'].forEach(id => document.getElementById(id).style.display = 'none');
  document.getElementById('step' + n).style.display = 'block';
  document.getElementById('wizardBar').style.display = 'flex';
  currentStep = n;
  // Update wizard bar
  [1,2,3].forEach(i => {
    const el = document.getElementById('ws'+i);
    el.classList.remove('active','done');
    if (i < n) el.classList.add('done');
    else if (i === n) el.classList.add('active');
    if (i < 3) {
      const ln = document.getElementById('wl'+i);
      ln.classList.toggle('done', i < n);
    }
    // Tick icon for done
    const circle = el.querySelector('.wz-circle');
    circle.innerHTML = i < n ? '<i class="fas fa-check" style="font-size:0.75rem"></i>' : i;
  });
  hideAlert();
}

function showAlert(msg, type='error') {
  const a = document.getElementById('alertBox');
  a.className = 'alert alert-'+type;
  a.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + msg;
  a.style.display = 'flex';
}
function hideAlert() { document.getElementById('alertBox').style.display='none'; }

// Render table
function renderItens() {
  const tbody = document.getElementById('tbodyItens');
  const vazio = document.getElementById('rowVazio');
  if (itemsReq.length === 0) { tbody.innerHTML = ''; tbody.appendChild(vazio); return; }
  let html = '';
  itemsReq.forEach((it, idx) => {
    html += `<tr>
      <td><strong>${esc(it.nome)}</strong></td>
      <td><code style="font-size:0.78rem;background:var(--bg);padding:2px 6px;border-radius:5px">${esc(it.codigo)}</code></td>
      <td>${it.stock} ${it.unidade}</td>
      <td><input type="number" class="qty-inp" data-idx="${idx}" value="${it.quantidade}"
          min="1" max="${it.stock}" style="width:80px;padding:7px 10px;border:1.5px solid var(--borda);border-radius:8px;font-family:'DM Sans',sans-serif"></td>
      <td>${esc(it.unidade)}</td>
      <td><button onclick="removeItem(${idx})" style="background:none;border:none;cursor:pointer;color:#e74c3c;font-size:1rem">
        <i class="fas fa-trash-alt"></i></button></td>
    </tr>`;
  });
  tbody.innerHTML = html;
  document.querySelectorAll('.qty-inp').forEach(inp => inp.addEventListener('change', e => {
    let v = parseInt(e.target.value);
    const mx = parseInt(e.target.getAttribute('max'));
    if (v < 1) v = 1; if (v > mx) v = mx;
    itemsReq[e.target.dataset.idx].quantidade = v;
    e.target.value = v;
  }));
}

function removeItem(idx) { itemsReq.splice(idx,1); renderItens(); }

// Catálogo modal
function openCatModal() {
  buildCatList(CATALOGO);
  document.getElementById('modalCatalogo').classList.add('open');
}
function buildCatList(list) {
  const div = document.getElementById('catalogoList');
  div.innerHTML = list.length ? '' : '<p style="color:var(--sub);text-align:center;padding:20px">Nenhum resultado.</p>';
  list.forEach(c => {
    const isAdded = itemsReq.some(i => i.consumivel_id === c.id);
    const el = document.createElement('div');
    el.style.cssText = `display:flex;justify-content:space-between;align-items:center;
      padding:12px 14px;border:1px solid var(--borda);border-radius:10px;
      background:${isAdded?'#e8f5ee':'white'};cursor:pointer;transition:background .15s`;
    el.innerHTML = `
      <div>
        <div style="font-weight:600;font-size:0.9rem">${esc(c.nome)}</div>
        <div style="font-size:0.75rem;color:var(--sub)">Cód: ${esc(c.codigo)} · Stock: ${c.stock} ${esc(c.unidade)}</div>
      </div>
      ${isAdded ? '<span style="color:var(--verde);font-size:0.8rem;font-weight:700"><i class="fas fa-check"></i> Adicionado</span>'
        : '<button class="btn btn-primary btn-sm" style="pointer-events:none"><i class="fas fa-plus"></i></button>'}
    `;
    if (!isAdded) {
      el.addEventListener('click', () => {
        itemsReq.push({ consumivel_id:c.id, nome:c.nome, codigo:c.codigo, unidade:c.unidade, stock:c.stock, quantidade:1 });
        renderItens();
        document.getElementById('modalCatalogo').classList.remove('open');
      });
    }
    div.appendChild(el);
  });
}
function filterCat(q) {
  q = q.toLowerCase();
  buildCatList(q ? CATALOGO.filter(c=>c.nome.toLowerCase().includes(q)||c.codigo.toLowerCase().includes(q)) : CATALOGO);
}
buildCatList(CATALOGO);

// Revisão
function buildReview() {
  const setor = document.getElementById('setorReq').value;
  const just  = document.getElementById('justReq').value;
  const data  = document.getElementById('dataReq').value;
  const itensHtml = '<ul style="margin:8px 0 0 20px">'
    + itemsReq.map(i=>`<li>${i.quantidade}× ${i.nome} (${i.unidade})</li>`).join('') + '</ul>';
  document.getElementById('reviewContent').innerHTML = `
    <table style="width:100%;font-size:0.9rem;border-collapse:collapse">
      <tr><td style="padding:8px 0;font-weight:600;width:160px;color:var(--sub)">Setor</td><td>${esc(setor)}</td></tr>
      <tr><td style="padding:8px 0;font-weight:600;color:var(--sub)">Data</td><td>${data}</td></tr>
      <tr><td style="padding:8px 0;font-weight:600;color:var(--sub)">Justificativa</td><td>${esc(just)||'—'}</td></tr>
      <tr><td style="padding:8px 0;font-weight:600;color:var(--sub);vertical-align:top">Itens</td><td>${itensHtml}</td></tr>
      <tr><td style="padding:8px 0;font-weight:600;color:var(--sub)">Requisitante</td><td><?= htmlspecialchars($user['nome']) ?></td></tr>
    </table>`;
}

async function submeter() {
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A submeter...';
  hideAlert();
  try {
    const res = await fetch('requisicoes.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        setor:          document.getElementById('setorReq').value,
        justificativa:  document.getElementById('justReq').value,
        itens: itemsReq.map(i=>({consumivel_id:i.consumivel_id,quantidade:i.quantidade}))
      })
    });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('successNum').textContent = 'Número: ' + data.numero;
      ['step1','step2','step3'].forEach(id=>document.getElementById(id).style.display='none');
      document.getElementById('wizardBar').style.display='none';
      document.getElementById('stepSucesso').style.display='block';
      itemsReq=[];
    } else {
      showAlert(data.erro || 'Erro ao submeter.','error');
      btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Submeter Requisição';
    }
  } catch(e) {
    showAlert('Erro de rede. Tente novamente.','error');
    btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Submeter Requisição';
  }
}

function resetWizard() {
  itemsReq=[];
  document.getElementById('setorReq').value='';
  document.getElementById('justReq').value='';
  renderItens();
  document.getElementById('stepSucesso').style.display='none';
  document.getElementById('wizardBar').style.display='flex';
  goStep(1);
}

function esc(s){ return String(s||'').replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }

document.querySelectorAll('.modal-overlay').forEach(m=>{
  m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');});
});
</script>

<?php include 'partials/footer.php'; ?>
