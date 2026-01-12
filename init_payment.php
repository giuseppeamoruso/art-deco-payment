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

    // Genera la firma con TUTTI i campi richiesti da UniCredit
    // Riferimento: IgfsCgInit.php righe 322-348
    $signatureFields = [
        API_VERSION,                    // APIVERSION
        $params['tid'],                 // TID
        '',                             // MERID (vuoto)
        '',                             // PAYINSTR (vuoto)
        $params['shopID'],              // SHOPID
        $params['shopUserRef'],         // SHOPUSERREF
        '',                             // SHOPUSERNAME (vuoto)
        '',                             // SHOPUSERACCOUNT (vuoto)
        '',                             // SHOPUSERMOBILEPHONE (vuoto)
        '',                             // SHOPUSERIMEI (vuoto)
        $params['trType'],              // TRTYPE
        $params['amount'],              // AMOUNT
        $params['currencyCode'],        // CURRENCYCODE
        $params['langID'],              // LANGID
        $params['notifyURL'],           // NOTIFYURL
        $params['errorURL'],            // ERRORURL
        '',                             // CALLBACKURL (vuoto)
        '',                             // ADDINFO1 (vuoto)
        '',                             // ADDINFO2 (vuoto)
        '',                             // ADDINFO3 (vuoto)
        '',                             // ADDINFO4 (vuoto)
        '',                             // ADDINFO5 (vuoto)
        '',                             // PAYINSTRTOKEN (vuoto)
        ''                              // TOPUPID (vuoto)
    ];
    
    // Concatena tutti i campi (SENZA kSig all'inizio per HMAC)
    $signatureString = '';
    foreach ($signatureFields as $field) {
        $signatureString .= $field;
    }
    
    logMessage('Signature String (before hash)', [
        'string_length' => strlen($signatureString),
        'first_100_chars' => substr($signatureString, 0, 100),
        'field_count' => count($signatureFields)
    ]);
    
    // IMPORTANTE: UniCredit usa HMAC-SHA256 + Base64, NON semplice SHA256!
    // Riferimento: IgfsUtils.php riga 11
    $signature = base64_encode(hash_hmac('sha256', $signatureString, UNICREDIT_API_KEY, true));
    $params['signature'] = $signature;
    
    logMessage('Generated Signature', [
        'signature' => $signature,
        'signature_length' => strlen($signature)
    ]);

    // Costruiamo la richiesta XML/SOAP come richiesto da UniCredit
    // NOTA: Usa i namespace esatti dalla documentazione UniCredit
    // IMPORTANTE: I campi vuoti vanno OMESSI, non inclusi come tag vuoti
    $xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://services.api.web.cg.igfs.apps.netsw.it/">
<soapenv:Body>
<ser:Init>
<request>
<apiVersion>' . API_VERSION . '</apiVersion>
<tid><![CDATA[' . $params['tid'] . ']]></tid>
<shopID><![CDATA[' . $params['shopID'] . ']]></shopID>
<shopUserRef><![CDATA[' . $params['shopUserRef'] . ']]></shopUserRef>
<trType><![CDATA[' . $params['trType'] . ']]></trType>
<amount><![CDATA[' . $params['amount'] . ']]></amount>
<currencyCode><![CDATA[' . $params['currencyCode'] . ']]></currencyCode>
<langID><![CDATA[' . $params['langID'] . ']]></langID>
<notifyURL><![CDATA[' . $params['notifyURL'] . ']]></notifyURL>
<errorURL><![CDATA[' . $params['errorURL'] . ']]></errorURL>
<signature><![CDATA[' . $params['signature'] . ']]></signature>
</request>
</ser:Init>
</soapenv:Body>
</soapenv:Envelope>';

    logMessage('Calling UniCredit Init with SOAP/XML', [
        'url' => UNICREDIT_API_URL,
        'xml_length' => strlen($xmlRequest),
        'xml_preview' => substr($xmlRequest, 0, 300) . '...',
        'xml_full' => $xmlRequest // LOG COMPLETO PER DEBUG
    ]);

    // Salva anche la richiesta su file
    file_put_contents('/tmp/unicredit_request.xml', $xmlRequest);

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
        'content_type' => $contentType,
        'response_is_empty' => empty($response),
        'response_type' => gettype($response)
    ]);
    
    curl_close($ch);

    if ($curlError) {
        logMessage('CURL ERROR', ['error' => $curlError]);
        jsonResponse([
            'success' => false,
            'error_code' => 'CURL_ERROR',
            'error_message' => 'Errore connessione: ' . $curlError
        ]);
    }
    
    // VERIFICA CHE LA RISPOSTA NON SIA VUOTA
    if (empty($response)) {
        logMessage('EMPTY RESPONSE FROM UNICREDIT', [
            'http_code' => $httpCode,
            'curl_error' => $curlError
        ]);
        jsonResponse([
            'success' => false,
            'error_code' => 'EMPTY_RESPONSE',
            'error_message' => 'Risposta vuota dal server UniCredit'
        ]);
    }

    // Parse risposta SOAP/XML con namespace
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response);
    
    if (!$xml) {
        $xmlErrors = libxml_get_errors();
        logMessage('XML Parse Failed', [
            'errors' => $xmlErrors,
            'response_length' => strlen($response),
            'response_start' => substr($response, 0, 200)
        ]);
        jsonResponse([
            'success' => false,
            'error_code' => 'XML_PARSE_ERROR',
            'error_message' => 'Errore parsing XML: ' . ($xmlErrors[0]->message ?? 'Unknown')
        ]);
    }
    
    logMessage('XML loaded successfully', [
        'xml_object' => get_class($xml)
    ]);
    
    // Naviga nella struttura SOAP con namespace
    $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xml->registerXPathNamespace('ns1', 'http://services.api.web.cg.igfs.apps.netsw.it/');
    
    $responseNodes = $xml->xpath('//ns1:InitResponse/response');
    
    logMessage('XPath query result', [
        'found_nodes' => count($responseNodes),
        'query' => '//ns1:InitResponse/response'
    ]);
    
    if (empty($responseNodes)) {
        logMessage('Response node not found in SOAP - trying alternative paths');
        
        // Prova percorsi alternativi
        $alt1 = $xml->xpath('//response');
        $alt2 = $xml->xpath('//*[local-name()="response"]');
        
        logMessage('Alternative XPath attempts', [
            'xpath_response' => count($alt1),
            'xpath_local_name' => count($alt2)
        ]);
        
        if (!empty($alt2)) {
            $responseNodes = $alt2;
            logMessage('Using local-name xpath');
        } else {
            jsonResponse([
                'success' => false,
                'error_code' => 'INVALID_RESPONSE',
                'error_message' => 'Struttura risposta non valida'
            ]);
        }
    }
    
    $responseNode = $responseNodes[0];
    $rc = (string)$responseNode->rc;
    $error = ((string)$responseNode->error === 'true');
    $errorDesc = (string)$responseNode->errorDesc;
    $paymentID = (string)$responseNode->paymentID;
    $redirectURL = (string)$responseNode->redirectURL;
    
    logMessage('=== PARSED RESULT ===', [
        'rc' => $rc,
        'error' => $error,
        'errorDesc' => $errorDesc,
        'paymentID' => $paymentID,
        'redirectURL' => $redirectURL
    ]);
    
    if ($error || $rc !== 'IGFS_000') {
        logMessage('=== UNICREDIT ERROR ===', [
            'rc' => $rc,
            'errorDesc' => $errorDesc
        ]);
        jsonResponse([
            'success' => false,
            'error_code' => $rc,
            'error_message' => $errorDesc ?: 'Errore sconosciuto'
        ]);
    }
    
    // SUCCESS!
    logMessage('=== PAYMENT INIT SUCCESS ===', [
        'paymentID' => $paymentID,
        'redirectURL' => $redirectURL
    ]);
    
    jsonResponse([
        'success' => true,
        'payment_id' => $paymentID,
        'redirect_url' => $redirectURL,
        'order_id' => $orderId,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY
    ]);

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
