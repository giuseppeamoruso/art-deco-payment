<?php
/**
 * Art Decò - Inizializzazione Pagamento
 * 
 * Questo endpoint viene chiamato dall'app Flutter per iniziare un pagamento.
 * Contatta UniCredit PagOnline e restituisce l'URL dove redirigere l'utente.
 */

require_once 'config.php';

// Abilita CORS per permettere chiamate dall'app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'error' => 'Metodo non permesso. Usa POST.'
    ], 405);
}

// Leggi i dati dal body (JSON o form-data)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

logMessage('Init Payment Request', $input);

// Valida parametri richiesti
$required = ['amount', 'email', 'order_id'];
$missing = validateParams($input, $required);

if (!empty($missing)) {
    jsonResponse([
        'success' => false,
        'error' => 'Parametri mancanti',
        'missing_fields' => $missing
    ], 400);
}

// Estrai parametri
$amount = (int)$input['amount']; // Importo in centesimi (es. 2500 = €25.00)
$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$orderId = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['order_id']); // Sanitize
$description = $input['description'] ?? 'Pagamento Art Decò Parrucchieri';
$customerName = $input['customer_name'] ?? '';

// Valida email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse([
        'success' => false,
        'error' => 'Email non valida'
    ], 400);
}

// Valida importo (minimo €1.00, massimo €9999.99)
if ($amount < 100 || $amount > 999999) {
    jsonResponse([
        'success' => false,
        'error' => 'Importo non valido (min €1.00, max €9999.99)'
    ], 400);
}

// ============================================
// CHIAMATA API UNICREDIT - INIT
// ============================================

try {
    // Prepara i parametri per UniCredit
    $params = [
        'tid' => UNICREDIT_TERMINAL_ID,
        'shopID' => $orderId,
        'shopUserRef' => $email,
        'trType' => PAYMENT_TYPE,
        'amount' => $amount,
        'currencyCode' => PAYMENT_CURRENCY,
        'langID' => PAYMENT_LANGUAGE,
        'notifyURL' => NOTIFY_URL,
        'errorURL' => ERROR_URL . '?order_id=' . urlencode($orderId),
        'description' => $description
    ];

    // Genera la firma
    $signatureParams = [
        'kSig' => UNICREDIT_API_KEY,
        'tid' => $params['tid'],
        'shopID' => $params['shopID'],
        'shopUserRef' => $params['shopUserRef'],
        'trType' => $params['trType'],
        'amount' => $params['amount'],
        'currencyCode' => $params['currencyCode'],
        'langID' => $params['langID'],
        'notifyURL' => $params['notifyURL'],
        'errorURL' => $params['errorURL']
    ];
    
    // Crea stringa per firma
    $signatureString = '';
    foreach ($signatureParams as $key => $value) {
        $signatureString .= $value;
    }
    
    $signature = hash('sha256', $signatureString);
    $params['signature'] = $signature;

    // Chiamata HTTP POST a UniCredit
    $ch = curl_init(UNICREDIT_API_URL . '/PaymentInit');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logMessage('UniCredit Init Response', [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curlError
    ]);

    if ($curlError) {
        throw new Exception('Errore connessione: ' . $curlError);
    }

    // Parse risposta
    $result = json_decode($response, true);
    
    if (!$result) {
        // Prova a parsare come XML se non è JSON
        $xml = simplexml_load_string($response);
        if ($xml) {
            $result = json_decode(json_encode($xml), true);
        }
    }

    // Verifica successo
    if (isset($result['paymentID']) && isset($result['redirectURL'])) {
        // Successo!
        jsonResponse([
            'success' => true,
            'payment_id' => $result['paymentID'],
            'redirect_url' => $result['redirectURL'],
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => PAYMENT_CURRENCY
        ]);
    } else {
        // Errore da UniCredit
        $errorCode = $result['rc'] ?? 'UNKNOWN';
        $errorDesc = $result['errorDesc'] ?? 'Errore sconosciuto';
        
        logMessage('UniCredit Error', $result);
        
        jsonResponse([
            'success' => false,
            'error' => 'Errore inizializzazione pagamento',
            'error_code' => $errorCode,
            'error_description' => $errorDesc
        ], 400);
    }

} catch (Exception $e) {
    logMessage('Exception in init_payment', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    jsonResponse([
        'success' => false,
        'error' => 'Errore interno del server',
        'message' => $e->getMessage()
    ], 500);
}