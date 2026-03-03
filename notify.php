<?php
/**
 * Art Decò - Notify Callback
 * 
 * Questo endpoint riceve le notifiche da UniCredit quando il pagamento è completato.
 * È chiamato dal server UniCredit (server-to-server).
 */

require_once 'config.php';

// Log della richiesta
logMessage('=== NOTIFY RAW REQUEST ===', [
    'method'  => $_SERVER['REQUEST_METHOD'],
    'get'     => $_GET,
    'post'    => $_POST,
    'body'    => file_get_contents('php://input'),
    'headers' => getallheaders()
]);
logMessage('Notify Callback Received', [

// Leggi i parametri
$paymentID = $_GET['paymentID'] ?? $_POST['paymentID'] ?? null;
$shopID = $_GET['shopID'] ?? $_POST['shopID'] ?? null;
$rc = $_GET['rc'] ?? $_POST['rc'] ?? null;
$errorDesc = $_GET['errorDesc'] ?? $_POST['errorDesc'] ?? '';
$tranID = $_GET['tranID'] ?? $_POST['tranID'] ?? null;
$authCode = $_GET['authCode'] ?? $_POST['authCode'] ?? null;
$enrStatus = $_GET['enrStatus'] ?? $_POST['enrStatus'] ?? null;
$authStatus = $_GET['authStatus'] ?? $_POST['authStatus'] ?? null;

if (!$paymentID || !$shopID) {
    logMessage('Notify Error: Missing parameters');
    http_response_code(400);
    echo "Missing parameters";
    exit;
}

// ============================================
// VERIFICA IL PAGAMENTO CON UNICREDIT
// ============================================

try {
    // Prepara parametri per verify
    $verifyParams = [
        'tid' => UNICREDIT_TERMINAL_ID,
        'shopID' => $shopID,
        'paymentID' => $paymentID
    ];

    // Genera firma
    $signatureString = UNICREDIT_API_KEY . UNICREDIT_TERMINAL_ID . $shopID . $paymentID;
    $signature = hash('sha256', $signatureString);
    $verifyParams['signature'] = $signature;

    // Chiamata verify
    $ch = curl_init(UNICREDIT_API_URL . '/PaymentVerify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verifyParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (!$result) {
        $xml = simplexml_load_string($response);
        if ($xml) {
            $result = json_decode(json_encode($xml), true);
        }
    }

    logMessage('Verify Response', [
        'http_code' => $httpCode,
        'result' => $result
    ]);

    // Analizza il risultato
    $rcCode = $result['rc'] ?? $rc;
    $success = ($rcCode === 'IGFS_000' || $rcCode === '000'); // Codici di successo

    // ============================================
    // SALVA IL RISULTATO (Database o File)
    // ============================================
    
    $transactionData = [
        'payment_id' => $paymentID,
        'order_id' => $shopID,
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

    // Salva su file JSON
    $logFile = __DIR__ . '/transactions/' . $shopID . '_' . $paymentID . '.json';
    @mkdir(__DIR__ . '/transactions', 0755, true);
    file_put_contents($logFile, json_encode($transactionData, JSON_PRETTY_PRINT));

    logMessage('Transaction Saved', $transactionData);

    // ============================================
    // NOTIFICA L'APP (opzionale - webhook)
    // ============================================
    
    // Se hai un webhook nell'app o in Supabase, chiamalo qui
    // Ad esempio: notifica Supabase che il pagamento è completato
    
    /*
    $supabaseUrl = 'https://your-project.supabase.co/rest/v1/payments';
    $supabaseKey = 'your-anon-key';
    
    $ch = curl_init($supabaseUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'order_id' => $shopID,
        'payment_id' => $paymentID,
        'status' => $success ? 'completed' : 'failed',
        'transaction_data' => $transactionData
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    */

    // Risposta OK a UniCredit
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    logMessage('Notify Exception', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo "ERROR";

}
