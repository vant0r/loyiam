<?php
/**
 * Register.php — login.php?mode=register'ga yo'naltiradi
 * (yangi split-screen dizayn ikkalasini ham qo'llab-quvvatlaydi)
 */
$query = http_build_query(array_merge($_GET, ['mode' => 'register']));
header('Location: /login.php?' . $query);
exit;
