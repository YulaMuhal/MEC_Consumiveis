<?php
require_once 'config.php';
requireLogin();

$modulo  = $_GET['modulo']  ?? '';
$formato = $_GET['formato'] ?? 'csv';

// Validar módulo e permissões
$modulosPermitidos = [
    'logs'          => ['admin'],
    'movimentacoes' => ['admin','gestor'],
    'requisicoes'   => ['admin','gestor'],
];

if (!isset($modulosPermitidos[$modulo])) {
    http_response_code(400); die('Módulo inválido.');
}
requireRole($modulosPermitidos[$modulo]);

$db   = db();
$user = currentUser();

// ── Obter dados por módulo ────────────────────────────────────────────────────
switch ($modulo) {
    case 'logs':
        $rows = $db->query(
            "SELECT l.criado_em, u.nome AS utilizador, u.email, l.acao, l.ip
             FROM logs l
             LEFT JOIN utilizadores u ON u.id = l.utilizador_id
             ORDER BY l.criado_em DESC"
        )->fetchAll();
        $colunas  = ['Data/Hora', 'Utilizador', 'Email', 'Acção', 'IP'];
        $campos   = ['criado_em', 'utilizador', 'email', 'acao', 'ip'];
        $titulo   = 'Logs_Auditoria';
        break;

    case 'movimentacoes':
        $tipo    = in_array($_GET['tipo'] ?? '', ['entrada','saida']) ? $_GET['tipo'] : '';
        $periodo = in_array($_GET['periodo'] ?? '', ['hoje','semana','mes']) ? $_GET['periodo'] : '';
        $busca   = trim($_GET['q'] ?? '');

        $where  = "WHERE 1=1";
        $params = [];
        if ($tipo)    { $where .= " AND m.tipo=?";         $params[] = $tipo; }
        if ($busca)   { $where .= " AND c.nome LIKE ?";    $params[] = "%$busca%"; }
        if ($periodo === 'hoje')   $where .= " AND DATE(m.criado_em)=CURDATE()";
        elseif ($periodo === 'semana') $where .= " AND m.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        elseif ($periodo === 'mes')    $where .= " AND m.criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        $stmt = $db->prepare(
            "SELECT m.criado_em, c.nome AS consumivel, m.tipo, m.quantidade, c.unidade, m.referencia, u.nome AS utilizador
             FROM movimentacoes m
             JOIN consumiveis c ON c.id = m.consumivel_id
             LEFT JOIN utilizadores u ON u.id = m.utilizador_id
             $where ORDER BY m.criado_em DESC"
        );
        $stmt->execute($params);
        $rows    = $stmt->fetchAll();
        $colunas = ['Data/Hora', 'Consumível', 'Tipo', 'Quantidade', 'Unidade', 'Referência', 'Utilizador'];
        $campos  = ['criado_em', 'consumivel', 'tipo', 'quantidade', 'unidade', 'referencia', 'utilizador'];
        $titulo  = 'Movimentacoes';
        break;

    case 'requisicoes':
        $rows = $db->query(
            "SELECT r.criado_em, r.numero, u.nome AS requisitante, u.unidade, r.setor, r.estado, r.observacao
             FROM requisicoes r
             JOIN utilizadores u ON u.id = r.utilizador_id
             ORDER BY r.criado_em DESC"
        )->fetchAll();
        $colunas = ['Data', 'Número', 'Requisitante', 'Unidade', 'Setor', 'Estado', 'Observação'];
        $campos  = ['criado_em', 'numero', 'requisitante', 'unidade', 'setor', 'estado', 'observacao'];
        $titulo  = 'Requisicoes';
        break;
}

$dataHoje = date('Y-m-d');
logAction("Exportação $formato: $modulo");

// ── CSV ───────────────────────────────────────────────────────────────────────
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="SIGEC_' . $titulo . '_' . $dataHoje . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM para Excel reconhecer UTF-8
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $colunas, ';');
    foreach ($rows as $row) {
        $linha = [];
        foreach ($campos as $c) {
            $val = $row[$c] ?? '';
            if ($c === 'criado_em' && $val) $val = date('d/m/Y H:i', strtotime($val));
            $linha[] = $val;
        }
        fputcsv($out, $linha, ';');
    }
    fclose($out);
    exit;
}

// ── PDF (página HTML para impressão) ─────────────────────────────────────────
if ($formato === 'pdf') {
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>SIGEC — <?= htmlspecialchars($titulo) ?> — <?= $dataHoje ?></title>
  <style>
    @media print { .no-print { display:none; } @page { margin: 15mm; } }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 20px; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; border-bottom: 2px solid #00843D; padding-bottom:12px; }
    .header h1 { margin:0; font-size:16px; color:#00843D; }
    .header p  { margin:3px 0 0; font-size:10px; color:#555; }
    .meta { font-size:10px; text-align:right; color:#555; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th { background:#00843D; color:white; padding:7px 8px; text-align:left; font-size:10px; }
    td { padding:6px 8px; border-bottom:1px solid #e0e0e0; font-size:10px; }
    tr:nth-child(even) td { background:#f5faf7; }
    .btn-print { margin-bottom:16px; padding:8px 20px; background:#00843D; color:white; border:none; border-radius:6px; cursor:pointer; font-size:13px; }
    .total { margin-top:12px; font-size:10px; color:#555; text-align:right; }
  </style>
</head>
<body>
  <button class="btn-print no-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
  <div class="header">
    <div>
      <h1>SIGEC — <?= htmlspecialchars($titulo) ?></h1>
      <p>Ministério da Educação e Cultura — Moçambique</p>
      <p>Exportado por: <?= htmlspecialchars($user['nome']) ?> | <?= date('d/m/Y H:i') ?></p>
    </div>
    <div class="meta">
      Total de registos: <strong><?= count($rows) ?></strong>
    </div>
  </div>

  <table>
    <thead>
      <tr><?php foreach ($colunas as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
      <tr>
        <?php foreach ($campos as $c):
            $val = $row[$c] ?? '—';
            if ($c === 'criado_em' && $val && $val !== '—') $val = date('d/m/Y H:i', strtotime($val));
        ?>
          <td><?= htmlspecialchars((string)$val) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total">Total: <?= count($rows) ?> registo(s) — SIGEC © <?= date('Y') ?></div>
</body>
</html>
<?php
    exit;
}

http_response_code(400);
die('Formato inválido.');
