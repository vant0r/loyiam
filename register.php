<?php
/**
 * register.php — STANDALONE redirect to login.php?mode=register
 * (login.php split-screen ikkalasini ham qamrab oladi)
 */
require_once __DIR__ . '/includes/bootstrap.php';

$query = http_build_query(array_merge($_GET, ['mode' => 'register']));
header('Location: /login.php?' . $query);
exit;
