<?php
// فایل: chat_api.php — دریافت پیام از صفحه چت و فرستادن آن به هوش مصنوعی (آوالای)
header("Content-Type: application/json; charset=UTF-8");

// برای عیب‌یابی در زمان ساخت true بماند؛ هنگام تحویل نهایی false شود
$DEBUG = true;

function respond($text) {
    echo json_encode(["reply" => $text], JSON_UNESCAPED_UNICODE);
    exit;
}

// فقط درخواست POST پذیرفته می‌شود
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond("درخواست نامعتبر است.");
}

// ۱) دریافت پیام کاربر از جاوااسکریپت
$data = json_decode(file_get_contents("php://input"));
$userMessage = isset($data->message) ? trim((string)$data->message) : '';

if ($userMessage === '') {
    respond("پیامی دریافت نشد.");
}
if (mb_strlen($userMessage) > 500) {
    respond("پیام شما خیلی طولانی است (حداکثر ۵۰۰ حرف).");
}

// ۲) خواندن کلید از فایل config/secrets.php (کلید هرگز داخل این فایل نوشته نمی‌شود)
$secretsFile = __DIR__ . '/config/secrets.php';
if (!file_exists($secretsFile)) {
    respond("تنظیمات سرور کامل نیست (فایل config/secrets.php پیدا نشد).");
}
$secrets = require $secretsFile;
$apiKey  = $secrets['api_key'] ?? '';
if ($apiKey === '') {
    respond("کلید API تنظیم نشده است.");
}

$apiUrl = "https://api.avalai.ir/v1/chat/completions";
$model  = "gpt-5.4-mini";   // نام مدل را از https://api.avalai.ir/public/models بردار

// ۳) دستورالعمل دستیار
$systemPrompt = "شما یک دستیار خرید هوشمند برای فروشگاه ساعت هستید.
فقط و فقط به سوالات مربوط به ساعت (مردانه، زنانه، رومیزی، برند، قیمت، بند و...) پاسخ دهید.
اگر کاربر سوالی پرسید که ربطی به ساعت نداشت (مثلاً قیمت گوشی، لباس، هوا و...)، دقیقاً این جمله را بگویید:
'سوال شما نامرتبط با این سایت است. لطفاً سوال‌های مربوط به ساعت را بپرسید.'
هرگز اسم محصول یا قیمت از خودتان نسازید. اگر فهرست محصولات در اختیارتان نیست، صادقانه بگویید فعلاً به فهرست محصولات دسترسی ندارید.
پاسخ‌ها کوتاه، مفید و دوستانه باشند.";

// ۴) ساخت درخواست (بدون temperature؛ بعضی مدل‌های جدید مقدار دلخواه آن را رد می‌کنند)
$postData = json_encode([
    "model"    => $model,
    "messages" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user",   "content" => $userMessage]
    ]
], JSON_UNESCAPED_UNICODE);

// ۵) ارسال با cURL (بررسی SSL فعال است)
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ۶) پردازش جواب
if ($response !== false && $httpCode == 200) {
    $responseData = json_decode($response, true);
    $botReply = $responseData['choices'][0]['message']['content'] ?? "پاسخ نامشخصی از سرور دریافت شد.";
    respond($botReply);
}

// خطا: جزئیات فقط در حالت DEBUG نشان داده می‌شود
error_log("AI error: status=$httpCode curl=$curlError");
$msg = "خطا در ارتباط با هوش مصنوعی.";
if ($DEBUG) {
    $msg .= " (کد: $httpCode" . ($curlError ? " - $curlError" : "") . ")";
}
respond($msg);
