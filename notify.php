<?php
// 🔍 DEBUG TOTALE - rimuovere dopo il test
file_put_contents('/tmp/notify_debug.txt', 
    date('Y-m-d H:i:s') . "\n" .
    "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n" .
    "GET: " . print_r($_GET, true) . "\n" .
    "POST: " . print_r($_POST, true) . "\n" .
    "BODY RAW: " . file_get_contents('php://input') . "\n" .
    "HEADERS: " . print_r(getallheaders(), true) . "\n"
);
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

// Leggi i parametri
$paymentID = $_GET['paymentID'] ?? $_POST['paymentID'] ?? null;
$shopID = $_GET['shopID'] ?? $_POST['shopID'] ?? null;
$rc = $_GET['rc'] ?? $_POST['rc'] ?? null;
$errorDesc = $_GET['errorDesc'] ?? $_POST['errorDesc'] ?? '';
$tranID = $_GET['tranID'] ?? $_POST['tranID'] ?? null;
$authCode = $_GET['authCode'] ?? $_POST['authCode'] ?? null;
$enrStatus = $_GET['enrStatus'] ?? $_POST['enrStatus'] ?? null;
$authStatus = $_GET['authStatus'] ?? $_POST['authStatus'] ?? null;

// Capisce se e' il browser dell'utente o una chiamata server-to-server
$isFromBrowser = isset($_SERVER['HTTP_ACCEPT']) && 
                 strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false;

if (!$paymentID || !$shopID) {
    if ($isFromBrowser) {
        // Il browser e' arrivato qui senza parametri
        // Lo mandiamo a success.php che fara' il deep link verso l'app
        logMessage('Browser senza parametri - redirect a success.php');
        $orderId = $_GET['order_id'] ?? '';
        header('Location: ' . SUCCESS_URL . '?order_id=' . urlencode($orderId));
        exit;
    }
    logMessage('Notify Error: Missing parameters', [
        'received_get'  => $_GET,
        'received_post' => $_POST,
    ]);
    http_response_code(400);
    echo "Missing parameters";
    exit;
}

// ============================================
// VERIFICA IL PAGAMENTO TRAMITE PARAMETRI NOTIFICA
// UniCredit invia rc, paymentID, tranID etc. direttamente nella notifica S2S.
// Non è necessario un ulteriore verify: usiamo questi parametri direttamente.
// ============================================

try {
    $rcCode = $rc; // parametro inviato da UniCredit nella notifica
    $success = ($rcCode === 'IGFS_000' || $rcCode === '000');

    logMessage('Notify - risultato da parametri UniCredit', [
        'rc' => $rc,
        'rcCode' => $rcCode,
        'success' => $success,
        'paymentID' => $paymentID,
        'tranID' => $tranID,
        'authCode' => $authCode,
    ]);

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
    ];

    // Salva su file JSON
    $logFile = __DIR__ . '/transactions/' . $shopID . '_' . $paymentID . '.json';
    @mkdir(__DIR__ . '/transactions', 0755, true);
    file_put_contents($logFile, json_encode($transactionData, JSON_PRETTY_PRINT));

    logMessage('Transaction Saved', $transactionData);

    // ============================================
    // SALVA SU SUPABASE PAGAMENTI (server-to-server)
    // ============================================

    $supabaseBaseUrl = 'https://fykszvedjcgurryynhha.supabase.co/rest/v1';
    $supabaseKey     = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ5a3N6dmVkamNndXJyeXluaGhhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTYxODc1ODksImV4cCI6MjA3MTc2MzU4OX0.H_HOV90GkbdZ_0Ue5ml781Qm1q8N6eukcDgXHAqE0VY';
    $supabaseHeaders = [
        'apikey: '       . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ];

    // Il formato di shopID è: APP_{appointmentId}_{timestamp}
    $orderParts    = explode('_', $shopID);
    $appointmentId = (count($orderParts) >= 2) ? intval($orderParts[1]) : null;

    if ($appointmentId) {
        // Usa la funzione RPC SECURITY DEFINER per bypassare le RLS
        // Questo garantisce che il PATCH funzioni anche per utenti con supabase_uid impostato
        $nuovoStato = $success ? 'completato' : 'fallito';
        $rpcData = [
            'p_appointment_id'        => $appointmentId,
            'p_stato'                 => $nuovoStato,
            'p_unicredit_payment_id'  => ($success && $paymentID) ? $paymentID : null,
        ];

        $ch = curl_init($supabaseBaseUrl . '/rpc/update_payment_unicredit');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpcData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $supabaseHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $rpcResponse = curl_exec($ch);
        $rpcCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logMessage('✅ Supabase RPC update_payment_unicredit', [
            'appuntamento_id' => $appointmentId,
            'stato'           => $nuovoStato,
            'http_code'       => $rpcCode,
            'response'        => $rpcResponse,
        ]);
    } else {
        logMessage('⚠️ Impossibile estrarre appointmentId da shopID', ['shopID' => $shopID]);
    }

    // Risposta OK a UniCredit
    // Se e' il browser, redirecta alla pagina giusta
    if ($isFromBrowser) {
        if ($success) {
            header('Location: ' . SUCCESS_URL . '?order_id=' . urlencode($shopID) . '&payment_id=' . urlencode($paymentID));
        } else {
            header('Location: ' . ERROR_URL . '?order_id=' . urlencode($shopID));
        }
        exit;
    }
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



