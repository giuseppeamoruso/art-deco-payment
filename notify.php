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
        $nuovoStato = $success ? 'completato' : 'failed';

        // 1) Controlla se esiste già un record in PAGAMENTI
        $checkUrl = $supabaseBaseUrl . '/PAGAMENTI?appuntamento_id=eq.' . $appointmentId . '&select=id,stato';
        $ch = curl_init($checkUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $supabaseHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $checkBody = curl_exec($ch);
        curl_close($ch);

        $esistenti = json_decode($checkBody, true);

        if (!empty($esistenti)) {
            // Record già presente → UPDATE stato e unicredit_payment_id
            $updateData = ['stato' => $nuovoStato];
            if ($success && $paymentID) {
                $updateData['unicredit_payment_id'] = $paymentID;
            }
            $updateUrl = $supabaseBaseUrl . '/PAGAMENTI?appuntamento_id=eq.' . $appointmentId;
            $ch = curl_init($updateUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $supabaseHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
            logMessage('✅ Supabase PAGAMENTI AGGIORNATO', ['appuntamento_id' => $appointmentId, 'stato' => $nuovoStato]);

        } else {
            // Nessun record → recupera prezzo da APPUNTAMENTI e INSERT
            $appUrl = $supabaseBaseUrl . '/APPUNTAMENTI?id=eq.' . $appointmentId . '&select=prezzo_totale';
            $ch = curl_init($appUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $supabaseHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $appBody = curl_exec($ch);
            curl_close($ch);

            $appData = json_decode($appBody, true);
            $importo = (!empty($appData) && isset($appData[0]['prezzo_totale']))
                ? floatval($appData[0]['prezzo_totale'])
                : 0.0;

            $insertData = [
                'appuntamento_id'      => $appointmentId,
                'metodo_pagamento'     => 'unicredit',
                'stato'                => $nuovoStato,
                'importo'              => $importo,
                'unicredit_payment_id' => $paymentID,
            ];
            $ch = curl_init($supabaseBaseUrl . '/PAGAMENTI');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($insertData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $supabaseHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
            logMessage('✅ Supabase PAGAMENTI INSERITO', $insertData);
        }
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



