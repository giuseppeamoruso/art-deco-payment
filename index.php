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
        'init_payment' => '/init_payment.php (coming soon)',
        'verify_payment' => '/verify_payment.php (coming soon)',
        'notify' => '/notify.php (coming soon)'
    ],
    'unicredit_config' => [
        'terminal_id' => '30701804',
        'backoffice_url' => 'https://pagamenti.unicredit.it/backoffice',
        'ready' => false
    ]
];

// Risposta JSON
echo json_encode($phpInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
