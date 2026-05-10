<?php
/**
 * Art Decò - Verify Payment
 *
 * Verifica lo stato del pagamento interrogando direttamente Supabase,
 * che viene aggiornato da notify.php al completamento del pagamento UniCredit.
 */

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$paymentID = $_GET['payment_id'] ?? $_POST['payment_id'] ?? null;
$orderID   = $_GET['order_id']   ?? $_POST['order_id']   ?? null;

if (!$orderID) {
    jsonResponse(['success' => false, 'error' => 'Parametro order_id mancante'], 400);
}

logMessage('Verify Payment Request', ['payment_id' => $paymentID, 'order_id' => $orderID]);

// Estrai appointmentId dal formato APP_{id}_{timestamp}
$orderParts    = explode('_', $orderID);
$appointmentId = (count($orderParts) >= 2) ? intval($orderParts[1]) : null;

if (!$appointmentId) {
    jsonResponse(['success' => false, 'error' => 'Formato order_id non valido'], 400);
}

// ============================================
// QUERY SUPABASE PER LO STATO DEL PAGAMENTO
// ============================================

$supabaseBaseUrl = 'https://fykszvedjcgurryynhha.supabase.co/rest/v1';
$supabaseKey     = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ5a3N6dmVkamNndXJyeXluaGhhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTYxODc1ODksImV4cCI6MjA3MTc2MzU4OX0.H_HOV90GkbdZ_0Ue5ml781Qm1q8N6eukcDgXHAqE0VY';

$ch = curl_init(
    $supabaseBaseUrl . '/PAGAMENTI?appuntamento_id=eq.' . $appointmentId .
    '&select=stato,unicredit_payment_id&limit=1'
);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: '       . $supabaseKey,
    'Authorization: Bearer ' . $supabaseKey,
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

logMessage('Supabase PAGAMENTI response', [
    'http_code'  => $httpCode,
    'curl_error' => $curlError,
    'response'   => $response,
]);

if ($curlError) {
    jsonResponse(['success' => false, 'error' => 'Errore connessione database'], 500);
}

$payments = json_decode($response, true);

if (!is_array($payments) || empty($payments)) {
    // Record non ancora creato o non trovato: ancora in attesa
    jsonResponse([
        'success'        => true,
        'payment_status' => 'pending',
        'payment_id'     => $paymentID,
        'order_id'       => $orderID,
    ]);
}

$payment = $payments[0];
$stato   = $payment['stato'] ?? 'in_attesa';

// Mappa stato Supabase → payment_status atteso dal Flutter
switch ($stato) {
    case 'completato':
        $paymentStatus = 'completed';
        break;
    case 'fallito':
        $paymentStatus = 'failed';
        break;
    default:
        $paymentStatus = 'pending';
}

jsonResponse([
    'success'        => true,
    'payment_status' => $paymentStatus,
    'payment_id'     => $payment['unicredit_payment_id'] ?? $paymentID,
    'order_id'       => $orderID,
]);
