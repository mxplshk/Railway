<?php
// ============================================================
// pay.php — принимает данные с Тильды и создаёт платёж Epoint
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ── НАСТРОЙКИ ──────────────────────────────────────────────
define('PUBLIC_KEY',   'i000000001');          // ← ваш public_key
define('PRIVATE_KEY',  'your_private_key');    // ← ваш private_key
define('CURRENCY',     'AZN');
define('LANGUAGE',     'ru');
define('SUCCESS_URL',  'https://yoursite.com/success');  // страница успеха на Тильде
define('ERROR_URL',    'https://yoursite.com/error');    // страница ошибки на Тильде
define('EPOINT_API',   'https://epoint.az/api/1/request');
// ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Получаем данные из формы Тильды
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$amount      = floatval($input['amount'] ?? 0);
$description = htmlspecialchars($input['description'] ?? 'Оплата заказа');
$order_id    = 'ORDER_' . time() . '_' . rand(1000, 9999);

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверная сумма']);
    exit;
}

// Формируем json_string
$payload = [
    'public_key'           => PUBLIC_KEY,
    'amount'               => (string) $amount,
    'currency'             => CURRENCY,
    'language'             => LANGUAGE,
    'order_id'             => $order_id,
    'description'          => $description,
    'success_redirect_url' => SUCCESS_URL,
    'error_redirect_url'   => ERROR_URL,
];

// Кодируем data
$data = base64_encode(json_encode($payload));

// Формируем signature
$sgn_string = PRIVATE_KEY . $data . PRIVATE_KEY;
$signature  = base64_encode(sha1($sgn_string, true));

// Отправляем запрос на Epoint
$postfields = http_build_query([
    'data'      => $data,
    'signature' => $signature,
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, EPOINT_API);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка соединения: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);

if ($result['status'] === 'success' && !empty($result['redirect_url'])) {
    // Возвращаем redirect_url в Тильду
    echo json_encode([
        'status'       => 'success',
        'redirect_url' => $result['redirect_url'],
        'transaction'  => $result['transaction'] ?? '',
        'order_id'     => $order_id,
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $result['message'] ?? 'Ошибка создания платежа',
    ]);
}
