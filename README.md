# Art Decò Payment Gateway

Sistema di pagamento per Art Decò Parrucchieri integrato con **UniCredit PagOnline**.

## 🚀 Quick Start

Questo è un backend PHP per gestire i pagamenti tramite UniCredit PagOnline.

### Deploy su Railway

1. Crea un account su [Railway.app](https://railway.app)
2. Clicca su "New Project" → "Deploy from GitHub repo"
3. Seleziona questo repository
4. Railway rileverà automaticamente PHP e farà il deploy!

### Test

Dopo il deploy, visita l'URL fornito da Railway (es. `https://art-deco-payment.railway.app`)

Dovresti vedere una risposta JSON con:
```json
{
    "status": "success",
    "message": "Art Decò Payment Gateway è attivo!",
    "php_version": "8.x",
    ...
}
```

## 📋 Credenziali UniCredit

- **Terminal ID**: 30701804
- **User ID**: 5312935
- **BackOffice**: https://pagamenti.unicredit.it/backoffice
- **API Key**: Da recuperare dal BackOffice (Profilo esercente → Terminali)

## 🔧 Configurazione

Una volta verificato che PHP funziona, aggiungeremo:
- `config.php` - Configurazione credenziali
- `init_payment.php` - Inizializzazione pagamento
- `verify_payment.php` - Verifica esito
- `notify.php` - Callback UniCredit
- Librerie IGFS_CG_API di UniCredit

## 📱 Integrazione con App Flutter

L'app Flutter chiamerà:
```dart
// Inizializza pagamento
final response = await http.post(
  Uri.parse('https://art-deco-payment.railway.app/init_payment.php'),
  body: {
    'amount': '2500', // €25.00 in centesimi
    'email': 'cliente@example.com',
    'orderId': 'ORDER123'
  }
);
```

## 📄 Licenza

© 2025 Art Decò Parrucchieri
