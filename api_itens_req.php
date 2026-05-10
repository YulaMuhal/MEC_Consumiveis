<?php
require_once 'config.php';
requireLogin();
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo '[]'; exit; }

$stmt = db()->prepare(
    "SELECT ri.quantidade, c.nome, c.codigo, c.unidade
     FROM requisicao_itens ri
     JOIN consumiveis c ON c.id = ri.consumivel_id
     WHERE ri.requisicao_id = ?"
);
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll());
