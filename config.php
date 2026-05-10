<?php
// config.php — SIGEC configuração central
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'sigec');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'SIGEC');
define('APP_ORG',  'Ministério da Educação e Cultura — Moçambique');
define('SESSION_TIMEOUT', 1800); // 30 min

// ── DB Connection (PDO) ──────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die(json_encode(['erro' => 'Falha na ligação à base de dados: ' . $e->getMessage()]));
    }
    return $pdo;
}

// ── Auth helpers ─────────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    if (empty($_SESSION['uid'])) return false;
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        session_unset(); session_destroy(); return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: index.php'); exit; }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: dashboard.php?erro=acesso_negado'); exit;
    }
}

function currentUser(): array {
    return [
        'id'     => $_SESSION['uid']    ?? 0,
        'nome'   => $_SESSION['nome']   ?? '',
        'email'  => $_SESSION['email']  ?? '',
        'role'   => $_SESSION['role']   ?? '',
        'unidade'=> $_SESSION['unidade']?? '',
    ];
}

// ── Log helper ───────────────────────────────────────────────────────────────
function logAction(string $acao, ?int $uid = null): void {
    try {
        $uid = $uid ?? ($_SESSION['uid'] ?? null);
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        db()->prepare("INSERT INTO logs (utilizador_id, acao, ip) VALUES (?,?,?)")
             ->execute([$uid, $acao, $ip]);
    } catch (Exception $e) { /* silent */ }
}

// ── Role labels ──────────────────────────────────────────────────────────────
function roleLabel(string $role): string {
    return match($role) {
        'admin'        => 'Administrador',
        'gestor'       => 'Gestor de Almoxarifado',
        'requisitante' => 'Requisitante',
        default        => ucfirst($role),
    };
}
?>
