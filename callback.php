<?php
// ============================================================
// callback.php — принимает результат платежа от Epoint (result_url)
// ============================================================

define('PRIVATE_KEY', 'your_private_key'); // ← ваш private_key
define('LOG_FILE',    __DIR__ . '/payments.log');

// Логируем все входящие данные
function logPayment($data) {
    file_put_contents(
        LOG_FILE,
        date('[Y-m-d H:i:s] ') . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data      = $_POST['data']      ?? '';
$signature = $_POST['signature'] ?? '';

if (empty($data) || empty($signature)) {
    logPayment(['error' => 'Пустые data или signature', 'post' => $_POST]);
    http_response_code(400);
    exit;
}

// Проверяем подпись
$expected_signature = base64_encode(sha1(PRIVATE_KEY . $data . PRIVATE_KEY, true));

if ($expected_signature !== $signature) {
    logPayment(['error' => 'Неверная подпись', 'data' => $data]);
    http_response_code(403);
    exit;
}

// Декодируем данные платежа
$result = json_decode(base64_decode($data), true);

logPayment($result);

// Обрабатываем результат
$status   = $result['status']   ?? '';
$order_id = $result['order_id'] ?? '';
$amount   = $result['amount']   ?? '';

if ($status === 'success') {
    // ✅ ПЛАТЁЖ УСПЕШЕН
    // Здесь добавьте вашу логику:
    // - Обновить статус заказа в БД
    // - Отправить письмо клиенту
    // - Записать транзакцию

    // Пример записи в файл (замените на БД):
    file_put_contents(
        __DIR__ . '/orders.log',
        date('[Y-m-d H:i:s] ') .
        "SUCCESS | order_id: {$order_id} | amount: {$amount} | " .
        "transaction: {$result['transaction']} | card: {$result['card_mask']}" .
        PHP_EOL,
        FILE_APPEND
    );

} elseif ($status === 'failed') {
    // ❌ ПЛАТЁЖ ОТКЛОНЁН
    file_put_contents(
        __DIR__ . '/orders.log',
        date('[Y-m-d H:i:s] ') .
        "FAILED | order_id: {$order_id} | code: {$result['code']} | " .
        "message: {$result['message']}" .
        PHP_EOL,
        FILE_APPEND
    );
}

// Epoint ожидает 200 OK
http_response_code(200);
echo 'OK';
