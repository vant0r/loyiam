<?php
/**
 * Payme webhook endpoint
 * URL: SITE_URL/api/payme.php
 */
require_once __DIR__ . '/../includes/payments/payme.php';
PaymePayment::handle();
