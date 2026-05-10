<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Non Riuscito - Art Decò</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        .error-icon {
            width: 80px;
            height: 80px;
            background: #ef4444;
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
        .error-icon::after {
            content: "✕";
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
        .error-details {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            color: #991b1b;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
            margin: 5px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 87, 108, 0.6);
        }
        .btn:active {
            transform: translateY(0);
        }
        .btn-secondary {
            background: #6b7280;
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon"></div>
        <h1>Pagamento Non Riuscito</h1>
        <p>Il pagamento non è stato completato. Nessun addebito è stato effettuato sul tuo conto.</p>
        
        <?php
        require_once 'config.php';
        
        $orderId = $_GET['order_id'] ?? 'N/A';
        $errorCode = $_GET['rc'] ?? $_GET['error_code'] ?? null;
        $errorDesc = $_GET['errorDesc'] ?? $_GET['error_description'] ?? 'Errore generico';
        
        if ($errorCode || $errorDesc !== 'Errore generico'):
        ?>
        <div class="error-details">
            <strong>Dettagli errore:</strong><br>
            <?php if ($errorCode): ?>
                Codice: <?= htmlspecialchars($errorCode) ?><br>
            <?php endif; ?>
            <?= htmlspecialchars($errorDesc) ?>
        </div>
        <?php endif; ?>
        
        <p><strong>Possibili cause:</strong></p>
        <p style="font-size: 14px; text-align: left; margin: 15px 0;">
            • Dati della carta non corretti<br>
            • Fondi insufficienti<br>
            • Carta scaduta o bloccata<br>
            • Operazione annullata dall'utente<br>
            • Limite giornaliero raggiunto
        </p>
        
        <div style="margin-top: 30px;">
            <a href="<?= APP_DEEP_LINK_ERROR ?>?order_id=<?= urlencode($orderId) ?>" class="btn">
                Torna all'App
            </a>
            <br>
            <a href="#" onclick="history.back(); return false;" class="btn btn-secondary" style="margin-top: 10px;">
                Riprova
            </a>
        </div>
        
        <p style="margin-top: 30px; font-size: 12px; color: #9ca3af;">
            Per assistenza contatta il salone:<br>
            📞 +39 123 456 7890<br>
            ✉️ info@artdecoparrucchieri.it
        </p>
    </div>

    <script>
        // Auto-redirect all'app dopo 5 secondi
        setTimeout(function() {
            const orderId = '<?= htmlspecialchars($orderId) ?>';
            window.location.href = '<?= APP_DEEP_LINK_ERROR ?>?order_id=' + encodeURIComponent(orderId);
        }, 5000);
    </script>
</body>
</html>