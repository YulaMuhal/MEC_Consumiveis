<?php
require_once 'config.php';
if (isLoggedIn()) {
    $motivo = isset($_GET['timeout']) ? 'Logout por inactividade' : 'Logout efectuado';
    logAction($motivo);
}
$timeout = isset($_GET['timeout']) ? '1' : '';
session_unset();
session_destroy();
header('Location: index.php' . ($timeout ? '?timeout=1' : ''));
exit;
