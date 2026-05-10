<?php
/**
 * Art Decò Payment Gateway - Test
 * File di test per verificare che PHP funzioni su Railway
 */

// Header per JSON response
header('Content-Type: application/json');

// Informazioni PHP
$phpInfo = [
    'status' => 'success',
    'message' => 'Art Decò Payment Gateway è attivo!',
    'php_version' => phpversion(),
    'server_time' => date('Y-m-d H:i:s'),
    'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'endpoints' => [
        'test' => '/index.php',
        'init_payment' => '/init_payment.php',
        'verify_payment' => '/verify_payment.php',
        'notify' => '/notify.php (callback from UniCredit)',
        'success' => '/success.php',
        'error' => '/error.php'
    ],
    'unicredit_config' => [
        'terminal_id' => '30701804',
        'merchant_id' => '531293500002',
        'backoffice_url' => 'https://pagamenti.unicredit.it/backoffice',
        'api_configured' => true,
        'ready' => true
    ],
    'usage' => [
        'init_payment' => 'POST /init_payment.php with {amount, email, order_id}',
        'verify_payment' => 'GET /verify_payment.php?payment_id=xxx&order_id=yyy'
    ]
];

// Risposta JSON
echo json_encode($phpInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
