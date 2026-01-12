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
        
        // TEST PARSING COME IN init_payment.php
        echo "<h2>🧪 Test Parsing (same as init_payment.php):</h2>";
        
        libxml_use_internal_errors(true);
        $testDom = new DOMDocument();
        $loaded = @$testDom->loadXML($xml);
        
        if (!$loaded) {
            $xmlErrors = libxml_get_errors();
            echo "<p style='color:red;'>❌ DOMDocument load failed!</p>";
            echo "<pre>" . print_r($xmlErrors, true) . "</pre>";
        } else {
            echo "<p style='color:green;'>✅ DOMDocument loaded successfully</p>";
            
            $xpath = new DOMXPath($testDom);
            $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xpath->registerNamespace('ns1', 'http://services.api.web.cg.igfs.apps.netsw.it/');
            
            $responseNodes = $xpath->query('//ns1:InitResponse/response');
            
            echo "<p>XPath query: <code>//ns1:InitResponse/response</code></p>";
            echo "<p>Found nodes: <strong>" . $responseNodes->length . "</strong></p>";
            
            if ($responseNodes->length > 0) {
                echo "<p style='color:green;'>✅ Response node found!</p>";
                
                $responseNode = $responseNodes->item(0);
                
                echo "<h3>Extracted Values:</h3>";
                echo "<table border='1' cellpadding='5'>";
                
                foreach ($responseNode->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        echo "<tr>";
                        echo "<td><strong>" . htmlspecialchars($child->nodeName) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($child->nodeValue) . "</td>";
                        echo "</tr>";
                    }
                }
                
                echo "</table>";
                
            } else {
                echo "<p style='color:orange;'>⚠️ No nodes found with namespace xpath, trying alternative...</p>";
                
                $altNodes = $xpath->query('//*[local-name()="response"]');
                echo "<p>Alternative XPath: <code>//*[local-name()=\"response\"]</code></p>";
                echo "<p>Found nodes: <strong>" . $altNodes->length . "</strong></p>";
                
                if ($altNodes->length > 0) {
                    echo "<p style='color:green;'>✅ Found with alternative xpath!</p>";
                } else {
                    echo "<p style='color:red;'>❌ No nodes found even with alternative xpath</p>";
                }
            }
        }
    }
} else {
    echo "<p>No response file found. Run a test first.</p>";
}

echo "<hr>";
echo "<p><a href='/test_payment.html'>← Back to Test</a></p>";
?>
