<?php
/**
 * Art Decò - Read Debug Log
 * Legge il file di debug salvato da notify.php
 * RIMUOVERE dopo il test!
 */

$file = '/tmp/notify_debug.txt';

if (!file_exists($file)) {
    echo "❌ Nessun file di debug trovato.\n";
    echo "Significa che notify.php non è ancora stato chiamato da UniCredit.\n";
    exit;
}

echo "<pre>";
echo "✅ CONTENUTO DEBUG NOTIFY.PHP\n";
echo "================================\n\n";
echo htmlspecialchars(file_get_contents($file));
echo "</pre>";
