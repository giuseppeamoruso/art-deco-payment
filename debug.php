<?php
/**
 * Debug - Mostra ultima risposta UniCredit
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>UniCredit Response Debug</h1>";

// Info
if (file_exists('/tmp/unicredit_response_info.txt')) {
    echo "<h2>Response Info:</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents('/tmp/unicredit_response_info.txt')) . "</pre>";
} else {
    echo "<p>No info file found. Run a test first.</p>";
}

// XML completo
if (file_exists('/tmp/unicredit_response.xml')) {
    echo "<h2>Raw XML Response:</h2>";
    $xml = file_get_contents('/tmp/unicredit_response.xml');
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
} else {
    echo "<p>No response file found. Run a test first.</p>";
}

echo "<hr>";
echo "<p><a href='/test_payment.html'>← Back to Test</a></p>";
?>
