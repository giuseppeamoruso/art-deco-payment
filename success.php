<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Completato - Art Decò</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease-out 0.2s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        .success-icon::after {
            content: "✓";
            font-size: 50px;
            color: white;
            font-weight: bold;
        }
        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 15px;
        }
        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .btn:active {
            transform: translateY(0);
        }
        .details {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #6b7280;
            font-size: 14px;
        }
        .detail-value {
            color: #1f2937;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon"></div>
        <h1>Pagamento Completato!</h1>
        <p>Il tuo pagamento è stato processato con successo. Riceverai una email di conferma all'indirizzo fornito.</p>
        
        <?php
        require_once 'config.php';

        $orderId = $_GET['order_id'] ?? $_GET['shopID'] ?? 'N/A';
        $paymentId = $_GET['payment_id'] ?? $_GET['paymentID'] ?? null;
        $tranId = $_GET['tran_id'] ?? 'N/A';
        $amount = $_GET['amount'] ?? null;

        // ========================================================
        // FALLBACK: aggiorna Supabase anche qui nel caso notify.php
        // non fosse stato chiamato o avesse fallito (es. timeout S2S)
        // ========================================================
        if ($orderId !== 'N/A') {
            $orderParts    = explode('_', $orderId);
            $appointmentId = (count($orderParts) >= 2) ? intval($orderParts[1]) : null;

            if ($appointmentId) {
                $supabaseBaseUrl = 'https://fykszvedjcgurryynhha.supabase.co/rest/v1';
                $supabaseKey     = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ5a3N6dmVkamNndXJyeXluaGhhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTYxODc1ODksImV4cCI6MjA3MTc2MzU4OX0.H_HOV90GkbdZ_0Ue5ml781Qm1q8N6eukcDgXHAqE0VY';
                $rpcData = [
                    'p_appointment_id'       => $appointmentId,
                    'p_stato'                => 'completato',
                    'p_unicredit_payment_id' => $paymentId ?: null,
                ];
                $ch = curl_init($supabaseBaseUrl . '/rpc/update_payment_unicredit');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpcData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Content-Type: application/json',
                    'Prefer: return=minimal',
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        if ($orderId && $orderId !== 'N/A'):
        ?>
        <div class="details">
            <div class="detail-row">
                <span class="detail-label">Numero Ordine</span>
                <span class="detail-value"><?= htmlspecialchars($orderId) ?></span>
            </div>
            <?php if ($tranId !== 'N/A'): ?>
            <div class="detail-row">
                <span class="detail-label">ID Transazione</span>
                <span class="detail-value"><?= htmlspecialchars($tranId) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($amount): ?>
            <div class="detail-row">
                <span class="detail-label">Importo</span>
                <span class="detail-value">€<?= number_format($amount / 100, 2, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Data</span>
                <span class="detail-value"><?= date('d/m/Y H:i') ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php
        // Costruisce l'URL del deep link con order_id E payment_id
        $deepLinkUrl = APP_DEEP_LINK_SUCCESS . '?order_id=' . urlencode($orderId);
        if ($paymentId) {
            $deepLinkUrl .= '&payment_id=' . urlencode($paymentId);
        }
        ?>
        <a href="<?= $deepLinkUrl ?>" class="btn">
            Torna all'App
        </a>

        <p style="margin-top: 20px; font-size: 12px;">
            Se il pulsante non funziona, apri manualmente l'app Art Decò
        </p>
    </div>

    <script>
        // Tenta il deep link subito (user gesture non richiesto su redirect da pagamento)
        window.location.href = '<?= addslashes($deepLinkUrl) ?>';
        // Fallback dopo 2 secondi nel caso il primo tentativo sia stato bloccato
        setTimeout(function() {
            window.location.href = '<?= addslashes($deepLinkUrl) ?>';
        }, 2000);
    </script>
</body>
</html>
