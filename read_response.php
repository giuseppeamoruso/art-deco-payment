<?php
echo "<h3>REQUEST XML inviato a UniCredit:</h3><pre>";
if (file_exists('/tmp/unicredit_request.xml')) {
    echo htmlspecialchars(file_get_contents('/tmp/unicredit_request.xml'));
} else {
    echo "❌ File non trovato - non è ancora stato fatto un tentativo di pagamento";
}
echo "</pre>";

echo "<hr>";

echo "<h3>RESPONSE XML ricevuto da UniCredit:</h3><pre>";
if (file_exists('/tmp/unicredit_response.xml')) {
    echo htmlspecialchars(file_get_contents('/tmp/unicredit_response.xml'));
} else {
    echo "❌ File non trovato";
}
echo "</pre>";

echo "<hr>";

echo "<h3>INFO risposta (HTTP code, errori curl):</h3><pre>";
if (file_exists('/tmp/unicredit_response_info.txt')) {
    echo htmlspecialchars(file_get_contents('/tmp/unicredit_response_info.txt'));
} else {
    echo "❌ File non trovato";
}
echo "</pre>";
