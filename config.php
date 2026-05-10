<?php
/**
 * Art Decò - Configurazione PagOnline UniCredit
 * 
 * CREDENZIALI PRODUZIONE
 */

// ============================================
// CREDENZIALI UNICREDIT PAGONLINE
// ============================================
define('UNICREDIT_TERMINAL_ID', '30701804');
define('UNICREDIT_API_KEY', '5c1b9c5c0g0a8j4j0j0b5c1b9c5j0j0b'); // kSig
define('UNICREDIT_MERCHANT_ID', '531293500002');
define('UNICREDIT_SHOP_EMAIL', 'ISFEQUIPE@HOTMAIL.IT');

// URL Servizi PagOnline - PRODUZIONE
define('UNICREDIT_API_URL', 'https://pagamenti.unicredit.it/UNI_CG_SERVICES/services/PaymentInitGatewayPort');
define('UNICREDIT_INIT_WSDL', 'https://pagamenti.unicredit.it/UNI_CG_SERVICES/services/PaymentInitGatewayPort?wsdl');
define('UNICREDIT_TRAN_WSDL', 'https://pagamenti.unicredit.it/UNI_CG_SERVICES/services/PaymentTranGatewayPort?wsdl');

// Versione API UniCredit
define('API_VERSION', '5.5.0.6');

// BackOffice
define('UNICREDIT_BACKOFFICE_URL', 'https://pagamenti.unicredit.it/backoffice');

// ============================================
// CONFIGURAZIONE APPLICAZIONE
// ============================================

// URL del tuo servizio Railway (AGGIORNARE dopo il deploy)
define('BASE_URL', 'https://art-deco-app-production.up.railway.app');

// URL di callback
define('NOTIFY_URL', BASE_URL . '/notify.php');
define('ERROR_URL', BASE_URL . '/error.php');
define('SUCCESS_URL', BASE_URL . '/success.php');

// URL Deep Link per tornare all'app
define('APP_DEEP_LINK_SUCCESS', 'artdeco://payment/success');
define('APP_DEEP_LINK_ERROR', 'artdeco://payment/error');

// ============================================
// CONFIGURAZIONE PAGAMENTO
// ============================================
define('PAYMENT_CURRENCY', 'EUR');
define('PAYMENT_LANGUAGE', 'IT');
define('PAYMENT_TYPE', 'AUTH'); // AUTH o PURCHASE

// ============================================
// CONFIGURAZIONE DATABASE (opzionale per logging)
// ============================================
define('DB_ENABLED', false); // Imposta true se vuoi salvare le transazioni

// Se DB_ENABLED = true, configura:
// define('DB_HOST', 'your-db-host');
// define('DB_NAME', 'your-db-name');
// define('DB_USER', 'your-db-user');
// define('DB_PASS', 'your-db-pass');

// ============================================
// FUNZIONI HELPER
// ============================================

/**
 * Genera la firma digitale per le richieste UniCredit
 */
function generateSignature($params) {
    $signatureString = '';
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $signatureString .= $key . '=' . $value;
        }
    }
    return hash('sha256', $signatureString);
}

/**
 * Logga un messaggio (file o database)
 */
function logMessage($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if ($data) {
        $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    // Log su file
    error_log($logEntry, 3, __DIR__ . '/payment.log');
    
    // Log su error_log di PHP (visibile in Railway)
    error_log($message . ($data ? ' | ' . json_encode($data) : ''));
}

/**
 * Risposta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Valida i parametri richiesti
 */
function validateParams($params, $required) {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($params[$field]) || empty($params[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}
