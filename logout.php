<?php
require_once 'config.php';
if (isLoggedIn()) {
    logAction("Logout efectuado");
}
session_unset();
session_destroy();
header('Location: index.php');
exit;
