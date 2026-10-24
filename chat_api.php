<?php
// chat_api.php — دریافت پیام چت و ارسال آن به AvalAI
header('Content-Type: application/json; charset=UTF-8');

// جزئیات خطاها فقط در لاگ سرور ثبت شوند، نه در پاسخ قابل مشاهده برای کاربران.
$DEBUG = false;

function respond($text, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['reply' => $text], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * دریافت کلید از متغیر محیطی؛ در صورت نبودن، فایل خصوصی /root/.avalai_env خوانده می‌شود.
 * فرمت فایل:
 * AVALAI_API_KEY=کلید_واقعی
 */
function loadAvalAIKey() {
    $apiKey = getenv('AVALAI_API_KEY');

    if (is_string($apiKey) && trim($apiKey) !== '') {
        return trim($apiKey);
    }

    $envFile = '/root/.avalai_env';

    // فایل باید خارج از پوشه عمومی سایت بماند.
    if (!is_file($envFile) || !is_readable($envFile)) {
        return '';
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return '';
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (strpos($line, 'AVALAI_API_KEY=') === 0) {
            return trim(substr($line, strlen('AVALAI_API_KEY=')), " \t\n\r\0\x0B\"'");
        }
    }

    return '';
}

// فقط درخواست POST پذیرفته می‌شود.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond('درخواست نامعتبر است.', 405);
}

// بررسی نوع و محتوای درخواست JSON.
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody ?: '', true);

if (!is_array($data)) {
    respond('ساختار درخواست نامعتبر است.', 400);
}

$userMessage = isset($data['message']) && is_string($data['message'])
    ? trim($data['message'])
    : '';

if ($userMessage === '') {
    respond('لطفاً پیام خود را وارد کنید.', 400);
}

if (function_exists('mb_strlen')) {
    if (mb_strlen($userMessage, 'UTF-8') > 500) {
        respond('پیام شما خیلی طولانی است (حداکثر ۵۰۰ حرف).', 413);
    }
} elseif (strlen($userMessage) > 2000) {
    respond('پیام شما خیلی طولانی است.', 413);
}

if (!function_exists('curl_init')) {
    error_log('AvalAI error: PHP cURL extension is not enabled.');
    respond('سرویس هوش مصنوعی فعلاً در دسترس نیست.', 500);
}

$apiKey = loadAvalAIKey();
if ($apiKey === '') {
    error_log('AvalAI error: API key not found or not readable by the PHP process.');
    respond('تنظیمات اتصال هوش مصنوعی روی سرور کامل نیست.', 500);
}

$apiUrl = 'https://api.avalai.ir/v1/chat/completions';
$model = 'gpt-5.4-mini';

$systemPrompt = "شما یک دستیار خرید هوشمند برای فروشگاه ساعت هستید.
فقط به پرسش‌های مربوط به ساعت پاسخ دهید.
اگر پرسش نامرتبط بود، دقیقاً بگویید:
سوال شما نامرتبط با این سایت است. لطفاً سوال‌های مربوط به ساعت را بپرسید.
هرگز نام محصول، قیمت یا موجودی را حدس نزنید. در حال حاضر فهرست واقعی محصولات به شما داده نشده است؛ اگر کاربر درباره محصول مشخصی پرسید، صادقانه بگویید که برای اعلام اطلاعات دقیق باید فهرست محصولات بررسی شود.
پاسخ‌ها کوتاه، مفید و دوستانه باشند.";

$postData = json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userMessage],
    ],
], JSON_UNESCAPED_UNICODE);

if ($postData === false) {
    error_log('AvalAI error: could not encode request JSON.');
    respond('امکان آماده‌سازی درخواست وجود ندارد.', 500);
}

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    // اعتبارسنجی TLS در حالت پیش‌فرض cURL فعال می‌ماند.
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
    $responseData = json_decode($response, true);
    $botReply = $responseData['choices'][0]['message']['content'] ?? null;

    if (is_string($botReply) && trim($botReply) !== '') {
        respond(trim($botReply));
    }

    error_log('AvalAI error: response did not contain message content.');
    respond('پاسخ قابل استفاده‌ای از هوش مصنوعی دریافت نشد.', 502);
}

error_log('AvalAI request failed: HTTP ' . $httpCode . '; cURL error: ' . $curlError);
respond('در ارتباط با هوش مصنوعی مشکلی پیش آمد. لطفاً کمی بعد دوباره تلاش کنید.', 502);
