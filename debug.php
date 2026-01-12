<?php
/**
 * Debug - Mostra ultima risposta UniCredit
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>UniCredit Response Debug</h1>";

// Request XML
if (file_exists('/tmp/unicredit_request.xml')) {
    echo "<h2>Request XML (sent to UniCredit):</h2>";
    $requestXml = file_get_contents('/tmp/unicredit_request.xml');
    echo "<pre>" . htmlspecialchars($requestXml) . "</pre>";
    
    echo "<h3>Formatted Request:</h3>";
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (@$dom->loadXML($requestXml)) {
        echo "<pre>" . htmlspecialchars($dom->saveXML()) . "</pre>";
    } else {
        echo "<p style='color:red;'>⚠️ REQUEST XML IS MALFORMED!</p>";
    }
    echo "<hr>";
}

// Info
if (file_exists('/tmp/unicredit_response_info.txt')) {
    echo "<h2>Response Info:</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents('/tmp/unicredit_response_info.txt')) . "</pre>";
} else {
    echo "<p>No info file found. Run a test first.</p>";
}

// XML completo
if (file_exists('/tmp/unicredit_response.xml')) {
    $xml = file_get_contents('/tmp/unicredit_response.xml');
    
    if (empty($xml)) {
        echo "<h2>Raw XML Response:</h2>";
        echo "<p style='color:red;'>⚠️ EMPTY RESPONSE FROM SERVER!</p>";
    } else {
        echo "<h2>Raw XML Response:</h2>";
        echo "<pre>" . htmlspecialchars($xml) . "</pre>";
        
        echo "<h2>Formatted XML:</h2>";
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (@$dom->loadXML($xml)) {
            echo "<pre>" . htmlspecialchars($dom->saveXML()) . "</pre>";
        } else {
            echo "<p>Could not parse XML</p>";
        }
    }
} else {
    echo "<p>No response file found. Run a test first.</p>";
}

echo "<hr>";
echo "<p><a href='/test_payment.html'>← Back to Test</a></p>";
?>
