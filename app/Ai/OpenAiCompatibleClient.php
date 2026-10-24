<?php
declare(strict_types=1);

namespace App\Ai;

use App\Support\Config;
use App\Support\Logger;

/**
 * تنها کلاسی که با سرویس هوش مصنوعی حرف می‌زند. کلید فقط اینجا و از secrets.php خوانده می‌شود.
 * فرمت: Chat Completions سازگار با OpenAI (پیام‌ها، tools و tool_calls).
 */
final class OpenAiCompatibleClient implements AiClientInterface
{
    private array $cfg;
    private string $apiKey;

    public function __construct(?array $aiConfig = null, ?string $apiKey = null)
    {
        $this->cfg = $aiConfig ?? Config::get('ai');
        $this->apiKey = $apiKey ?? Config::secret('api_key');
    }

    public function chat(array $messages, array $tools = []): array
    {
        if ($this->apiKey === '' || $this->apiKey === 'PUT-API-KEY-HERE') {
            throw new AiException('config', 'کلید API در config/secrets.php تنظیم نشده است.');
        }
        if (!function_exists('curl_init')) {
            throw new AiException('config', 'افزونه cURL در PHP فعال نیست.');
        }

        $payload = [
            'model'       => (string) $this->cfg['model'],
            'messages'    => $messages,
            'temperature' => (float) ($this->cfg['temperature'] ?? 0.3),
            ($this->cfg['max_tokens_param'] ?? 'max_tokens') => (int) ($this->cfg['max_output_tokens'] ?? 400),
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $url = rtrim((string) $this->cfg['base_url'], '/') . '/' . ltrim((string) $this->cfg['chat_path'], '/');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new AiException('bad_response', 'ساخت درخواست JSON ناموفق بود.');
        }

        $attempts = 1 + max(0, (int) ($this->cfg['retries'] ?? 1));
        $last = null;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $this->parse($this->post($url, $body));
            } catch (AiException $e) {
                $last = $e;
                // فقط خطای شبکه و خطای سرور سرویس دوباره تلاش می‌شود
                if (!in_array($e->kind(), ['network', 'server'], true) || $i === $attempts) {
                    break;
                }
                usleep(800000);
            }
        }
        throw $last ?? new AiException('network', 'خطای نامشخص در اتصال به هوش مصنوعی.');
    }

    /** @return array<string,mixed> */
    private function post(string $url, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) ($this->cfg['connect_timeout'] ?? 8),
            CURLOPT_TIMEOUT        => (int) ($this->cfg['timeout'] ?? 25),
            CURLOPT_SSL_VERIFYPEER => true,   // هرگز خاموش نکنید
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);
        $started = microtime(true);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($response === false || $errno !== 0) {
            Logger::warning('ai_network_error', ['errno' => $errno, 'error' => $error, 'ms' => $ms]);
            throw new AiException('network', 'اتصال به سرویس هوش مصنوعی برقرار نشد.');
        }

        if ($status === 401 || $status === 403) {
            Logger::error('ai_auth_error', ['status' => $status]);
            throw new AiException('auth', 'کلید API معتبر نیست یا دسترسی مجاز نیست (HTTP ' . $status . ').');
        }
        if ($status === 429) {
            throw new AiException('rate_limit', 'سقف درخواست یا اعتبار سرویس هوش مصنوعی تمام شده است (HTTP 429).');
        }
        if ($status >= 500) {
            throw new AiException('server', 'خطای سرور سرویس هوش مصنوعی (HTTP ' . $status . ').');
        }
        if ($status < 200 || $status >= 300) {
            Logger::error('ai_http_error', ['status' => $status, 'body' => substr((string) $response, 0, 300)]);
            throw new AiException('bad_response', 'پاسخ ناموفق از سرویس هوش مصنوعی (HTTP ' . $status . ').');
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new AiException('bad_response', 'پاسخ سرویس هوش مصنوعی JSON معتبر نیست.');
        }
        $data['_ms'] = $ms;
        return $data;
    }

    /** @param array<string,mixed> $data */
    private function parse(array $data): array
    {
        $message = $data['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            throw new AiException('bad_response', 'ساختار پاسخ سرویس هوش مصنوعی آشنا نیست.');
        }

        $toolCalls = [];
        foreach (($message['tool_calls'] ?? []) as $call) {
            $name = (string) ($call['function']['name'] ?? '');
            $argsRaw = $call['function']['arguments'] ?? '{}';
            $args = is_array($argsRaw) ? $argsRaw : json_decode((string) $argsRaw, true);
            if ($name === '') {
                continue;
            }
            $toolCalls[] = [
                'id'        => (string) ($call['id'] ?? ('call_' . bin2hex(random_bytes(4)))),
                'name'      => $name,
                'arguments' => is_array($args) ? $args : [],
            ];
        }

        $content = $message['content'] ?? null;
        return [
            'content'     => is_string($content) ? $content : null,
            'tool_calls'  => $toolCalls,
            'raw_message' => $message,
            'usage'       => is_array($data['usage'] ?? null) ? $data['usage'] : [],
        ];
    }
}
