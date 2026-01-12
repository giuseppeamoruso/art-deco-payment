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
    
    logMessage('=== PREPARING UNICREDIT CALL ===', [
        'terminal_id' => UNICREDIT_TERMINAL_ID,
        'order_id' => $orderId,
        'amount' => $amount,
        'email' => $email,
        'api_url' => UNICREDIT_API_URL,
        'notify_url' => NOTIFY_URL,
        'error_url' => ERROR_URL,
        'all_params' => $params
    ]);

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
    
    logMessage('Signature String (before hash)', [
        'string_length' => strlen($signatureString),
        'first_50_chars' => substr($signatureString, 0, 50)
    ]);
    
    $signature = hash('sha256', $signatureString);
    $params['signature'] = $signature;
    
    logMessage('Generated Signature', [
        'signature' => $signature,
        'signature_length' => strlen($signature)
    ]);

    // Costruiamo la richiesta XML/SOAP come richiesto da UniCredit
    // NOTA: Usa i namespace esatti dalla documentazione UniCredit
    $xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://services.api.web.cg.igfs.apps.netsw.it/">
<soapenv:Body>
<ser:Init>
<request>
<apiVersion>' . API_VERSION . '</apiVersion>
<tid><![CDATA[' . $params['tid'] . ']]></tid>
<merID></merID>
<payInstr></payInstr>
<shopID><![CDATA[' . $params['shopID'] . ']]></shopID>
<shopUserRef><![CDATA[' . $params['shopUserRef'] . ']]></shopUserRef>
<trType><![CDATA[' . $params['trType'] . ']]></trType>
<amount><![CDATA[' . $params['amount'] . ']]></amount>
<currencyCode><![CDATA[' . $params['currencyCode'] . ']]></currencyCode>
<langID><![CDATA[' . $params['langID'] . ']]></langID>
<notifyURL><![CDATA[' . $params['notifyURL'] . ']]></notifyURL>
<errorURL><![CDATA[' . $params['errorURL'] . ']]></errorURL>
<callbackURL></callbackURL>
<addInfo1></addInfo1>
<addInfo2></addInfo2>
<addInfo3></addInfo3>
<addInfo4></addInfo4>
<addInfo5></addInfo5>
<signature><![CDATA[' . $params['signature'] . ']]></signature>
</request>
</ser:Init>
</soapenv:Body>
</soapenv:Envelope>';

    logMessage('Calling UniCredit Init with SOAP/XML', [
        'url' => UNICREDIT_API_URL,
        'xml_length' => strlen($xmlRequest),
        'xml_preview' => substr($xmlRequest, 0, 300) . '...'
    ]);

    // Chiamata HTTP POST a UniCredit con XML/SOAP
    $ch = curl_init(UNICREDIT_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/xml; charset="utf-8"',
        'Content-Length: ' . strlen($xmlRequest)
    ]);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);

    // Salva risposta completa su file per debug
    file_put_contents('/tmp/unicredit_response.xml', $response);
    file_put_contents('/tmp/unicredit_response_info.txt', 
        "HTTP Code: $httpCode\n" .
        "Content-Type: $contentType\n" .
        "Length: " . strlen($response) . "\n" .
        "Curl Error: $curlError\n"
    );

    logMessage('=== UNICREDIT RAW RESPONSE ===', [
        'http_code' => $httpCode,
        'response_length' => strlen($response),
        'response_first_500_chars' => substr($response, 0, 500),
        'response_saved_to' => '/tmp/unicredit_response.xml',
        'curl_error' => $curlError,
        'content_type' => $contentType
    ]);
    
    curl_close($ch);

    if ($curlError) {
        logMessage('CURL ERROR', ['error' => $curlError]);
        throw new Exception('Errore connessione: ' . $curlError);
    }

    // Parse risposta JSON
    $result = json_decode($response, true);
    $jsonError = json_last_error();
    
    logMessage('JSON Parse Attempt', [
        'json_decoded' => $result !== null,
        'json_error' => $jsonError,
        'json_error_msg' => json_last_error_msg()
    ]);
    
    if (!$result && $jsonError !== JSON_ERROR_NONE) {
        // Prova a parsare come XML
        logMessage('Trying XML parse...');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        
        if ($xml) {
            $result = json_decode(json_encode($xml), true);
            logMessage('XML Parsed Successfully', ['result' => $result]);
        } else {
            $xmlErrors = libxml_get_errors();
            logMessage('XML Parse Failed', ['errors' => $xmlErrors]);
        }
    }

    logMessage('=== PARSED RESULT ===', [
        'result' => $result,
        'has_paymentID' => isset($result['paymentID']),
        'has_redirectURL' => isset($result['redirectURL']),
        'has_rc' => isset($result['rc']),
        'has_errorDesc' => isset($result['errorDesc'])
    ]);

    // Verifica successo
    if (isset($result['paymentID']) && isset($result['redirectURL'])) {
        // Successo!
        logMessage('=== PAYMENT INIT SUCCESS ===', [
            'payment_id' => $result['paymentID'],
            'redirect_url' => $result['redirectURL']
        ]);
        
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
        
        logMessage('=== UNICREDIT ERROR ===', [
            'error_code' => $errorCode,
            'error_description' => $errorDesc,
            'full_result' => $result
        ]);
        
        jsonResponse([
            'success' => false,
            'error' => 'Errore inizializzazione pagamento',
            'error_code' => $errorCode,
            'error_description' => $errorDesc,
            'raw_response' => substr($response, 0, 200) // primi 200 char per debug
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
