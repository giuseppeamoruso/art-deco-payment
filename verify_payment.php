<?php
/**
 * Art Decò - Verify Payment
 * 
 * Endpoint per verificare lo stato di un pagamento.
 * Può essere chiamato dall'app per controllare se il pagamento è andato a buon fine.
 */

require_once 'config.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Accetta GET o POST
$paymentID = $_GET['payment_id'] ?? $_POST['payment_id'] ?? null;
$orderID = $_GET['order_id'] ?? $_POST['order_id'] ?? null;

if (!$paymentID || !$orderID) {
    jsonResponse([
        'success' => false,
        'error' => 'Parametri mancanti: payment_id e order_id richiesti'
    ], 400);
}

logMessage('Verify Payment Request', [
    'payment_id' => $paymentID,
    'order_id' => $orderID
]);

// ============================================
// CONTROLLA SE ABBIAMO GIÀ IL RISULTATO
// ============================================

$transactionFile = __DIR__ . '/transactions/' . $orderID . '_' . $paymentID . '.json';

if (file_exists($transactionFile)) {
    // Leggi dal file
    $transactionData = json_decode(file_get_contents($transactionFile), true);
    
    logMessage('Transaction found in cache', $transactionData);
    
    jsonResponse([
        'success' => true,
        'cached' => true,
        'payment_status' => $transactionData['success'] ? 'completed' : 'failed',
        'payment_id' => $paymentID,
        'order_id' => $orderID,
        'transaction_id' => $transactionData['transaction_id'] ?? null,
        'auth_code' => $transactionData['auth_code'] ?? null,
        'timestamp' => $transactionData['timestamp'] ?? null,
        'error_description' => $transactionData['error_desc'] ?? null
    ]);
}

// ============================================
// CHIAMATA API UNICREDIT - VERIFY
// ============================================

try {
    // Prepara parametri
    $params = [
        'tid' => UNICREDIT_TERMINAL_ID,
        'shopID' => $orderID,
        'paymentID' => $paymentID
    ];

    // Genera firma
    $signatureString = UNICREDIT_API_KEY . UNICREDIT_TERMINAL_ID . $orderID . $paymentID;
    $signature = hash('sha256', $signatureString);
    $params['signature'] = $signature;

    // Chiamata HTTP
    $ch = curl_init(UNICREDIT_API_URL . '/PaymentVerify');
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

    logMessage('UniCredit Verify Response', [
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
        $xml = simplexml_load_string($response);
        if ($xml) {
            $result = json_decode(json_encode($xml), true);
        }
    }

    // Analizza risultato
    $rcCode = $result['rc'] ?? 'UNKNOWN';
    $success = ($rcCode === 'IGFS_000' || $rcCode === '000');
    
    $tranID = $result['tranID'] ?? null;
    $authCode = $result['authCode'] ?? null;
    $authStatus = $result['authStatus'] ?? null;
    $enrStatus = $result['enrStatus'] ?? null;
    $errorDesc = $result['errorDesc'] ?? '';

    // Salva il risultato
    $transactionData = [
        'payment_id' => $paymentID,
        'order_id' => $orderID,
        'transaction_id' => $tranID,
        'rc_code' => $rcCode,
        'auth_code' => $authCode,
        'auth_status' => $authStatus,
        'enr_status' => $enrStatus,
        'error_desc' => $errorDesc,
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'raw_data' => $result
    ];

    @mkdir(__DIR__ . '/transactions', 0755, true);
    file_put_contents($transactionFile, json_encode($transactionData, JSON_PRETTY_PRINT));

    // Risposta
    jsonResponse([
        'success' => true,
        'cached' => false,
        'payment_status' => $success ? 'completed' : 'failed',
        'payment_id' => $paymentID,
        'order_id' => $orderID,
        'transaction_id' => $tranID,
        'auth_code' => $authCode,
        'auth_status' => $authStatus,
        'enr_status' => $enrStatus,
        'rc_code' => $rcCode,
        'error_description' => $errorDesc,
        'timestamp' => $transactionData['timestamp']
    ]);

} catch (Exception $e) {
    logMessage('Verify Exception', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    jsonResponse([
        'success' => false,
        'error' => 'Errore verifica pagamento',
        'message' => $e->getMessage()
    ], 500);
}