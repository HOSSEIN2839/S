
<?php

require_once __DIR__ . '/vendor/autoload.php';

// خواندن کلید از متغیر محیطی یا فایل خصوصی سرور
$apiKey = getenv('AVALAI_API_KEY');

if (!$apiKey && is_readable('/etc/avalai.env')) {
    $envLines = file('/etc/avalai.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($envLines as $line) {
        $line = trim($line);

        if (strpos($line, 'AVALAI_API_KEY=') === 0) {
            $apiKey = trim(substr($line, strlen('AVALAI_API_KEY=')), " \t\n\r\0\x0B\"'");
            break;
        }
    }
}

if (!$apiKey) {
    die("خطا: کلید AvalAI پیدا نشد.\n");
}

$client = OpenAI::factory()
    ->withApiKey($apiKey)
    ->withBaseUri('https://api.avalai.ir/v1')
    ->make();

try {
    $response = $client->chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => 'سلام، فقط بنویس: اتصال موفق است'
            ]
        ],
        'max_tokens' => 50,
    ]);

    echo "پاسخ هوش مصنوعی:\n";
    echo $response->choices[0]->message->content . "\n";

} catch (\Throwable $e) {
    echo "خطا در درخواست API:\n";
    echo $e->getMessage() . "\n";
}
