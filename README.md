# Art Decò Payment Gateway

Sistema di pagamento per Art Decò Parrucchieri integrato con **UniCredit PagOnline**.

## 🎉 Sistema Completo e Pronto!

Tutti i file sono configurati e pronti all'uso con le credenziali di produzione UniCredit.

## 📁 Struttura File

```
art-deco-payment/
├── index.php              # API info e test
├── config.php             # Configurazione e credenziali
├── init_payment.php       # Inizializza pagamento
├── verify_payment.php     # Verifica stato pagamento
├── notify.php             # Callback da UniCredit
├── success.php            # Pagina successo
├── error.php              # Pagina errore
├── transactions/          # (creata automaticamente) Log transazioni
└── README.md
```

## 🚀 Deploy su Railway

### 1. Push su GitHub
```bash
git add .
git commit -m "Add payment integration"
git push
```

### 2. Railway Auto-Deploy
Railway rileverà automaticamente il push e farà il deploy!

## 🔧 Configurazione nell'App Flutter

### Inizializza Pagamento

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<String?> initPayment({
  required int amount,  // in centesimi: 2500 = €25.00
  required String email,
  required String orderId,
  String? customerName,
  String? description,
}) async {
  final response = await http.post(
    Uri.parse('https://art-deco-app-production.up.railway.app/init_payment.php'),
    headers: {'Content-Type': 'application/json'},
    body: jsonEncode({
      'amount': amount,
      'email': email,
      'order_id': orderId,
      'customer_name': customerName,
      'description': description ?? 'Pagamento Art Decò',
    }),
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    if (data['success']) {
      // Apri il browser con l'URL di pagamento
      final redirectUrl = data['redirect_url'];
      return redirectUrl;
    }
  }
  return null;
}
```

### Apri URL Pagamento

```dart
import 'package:url_launcher/url_launcher.dart';

Future<void> openPayment(String paymentUrl) async {
  final uri = Uri.parse(paymentUrl);
  if (await canLaunchUrl(uri)) {
    await launchUrl(
      uri,
      mode: LaunchMode.externalApplication, // Apre nel browser
    );
  }
}
```

### Verifica Pagamento

```dart
Future<bool> verifyPayment({
  required String paymentId,
  required String orderId,
}) async {
  final response = await http.get(
    Uri.parse(
      'https://art-deco-app-production.up.railway.app/verify_payment.php'
      '?payment_id=$paymentId&order_id=$orderId'
    ),
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    return data['success'] && data['payment_status'] == 'completed';
  }
  return false;
}
```

### Deep Link Configuration

Aggiungi deep link per tornare all'app dopo il pagamento:

#### Android (`android/app/src/main/AndroidManifest.xml`):
```xml
<intent-filter>
    <action android:name="android.intent.action.VIEW" />
    <category android:name="android.intent.category.DEFAULT" />
    <category android:name="android.intent.category.BROWSABLE" />
    <data android:scheme="artdeco" android:host="payment" />
</intent-filter>
```

#### iOS (`ios/Runner/Info.plist`):
```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>artdeco</string>
        </array>
    </dict>
</array>
```

## 📱 Flusso Completo

1. **Utente prenota** nell'app
2. **App chiama** `init_payment.php`
3. **Riceve** `redirect_url`
4. **Apre browser** con URL UniCredit
5. **Utente paga** su sito UniCredit sicuro
6. **UniCredit notifica** `notify.php` (server-to-server)
7. **UniCredit redirect** utente a `success.php` o `error.php`
8. **success.php** fa auto-redirect a `artdeco://payment/success`
9. **App si riapre** e verifica con `verify_payment.php`
10. **Conferma** prenotazione in Supabase

## 🔐 Credenziali Configurate

- **Terminal ID**: 30701804
- **Merchant ID**: 531293500002
- **API Key**: Configurata in `config.php`
- **Ambiente**: Produzione

## 📊 Monitoraggio

### Log Transazioni
Tutte le transazioni vengono salvate in `/transactions/` come file JSON:
```
transactions/ORDER123_PAYMENTXXX.json
```

### Log Sistema
Tutti i log vengono scritti in:
- `payment.log` (file locale)
- Railway Logs (visibili nel pannello)

## 🧪 Test

### Test Endpoint
```bash
curl https://art-deco-app-production.up.railway.app/index.php
```

### Test Init Payment
```bash
curl -X POST https://art-deco-app-production.up.railway.app/init_payment.php \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 2500,
    "email": "test@example.com",
    "order_id": "TEST001",
    "description": "Test payment"
  }'
```

## ⚠️ Note Importanti

1. **API Key**: NON committare mai `config.php` con credenziali reali su repository pubblici
2. **HTTPS**: UniCredit richiede HTTPS (Railway lo fornisce automaticamente)
3. **Callback**: `notify.php` deve essere accessibile pubblicamente
4. **Test**: Testa prima in ambiente di test UniCredit se disponibile

## 📞 Supporto

Per problemi tecnici:
- Railway Logs: Dashboard → Logs
- Payment Logs: `/transactions/*.json`
- UniCredit BackOffice: https://pagamenti.unicredit.it/backoffice

## 📄 Licenza

© 2025 Art Decò Parrucchieri