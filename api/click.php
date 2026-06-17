<?php
/**
 * Click webhook endpoint — Click serveridan kelgan POST so'rovlarni qabul qiladi.
 * URL ni Click admin paneliga kiritish kerak: SITE_URL/api/click.php
 */
require_once __DIR__ . '/../includes/payments/click.php';
ClickPayment::handle();
