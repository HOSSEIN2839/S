<?php
declare(strict_types=1);

namespace App\Ai;

use App\Support\Text;

/**
 * هوش مصنوعی آزمایشی (mode = mock): بدون اینترنت و بدون کلید، کل مسیر را تست می‌کند.
 * اگر پیام درباره «ساعت» باشد، ابزار search_products را صدا می‌زند، مثل مدل واقعی.
 */
final class MockAiClient implements AiClientInterface
{
    public function chat(array $messages, array $tools = []): array
    {
        $last = end($messages);
        if (!is_array($last)) {
            return $this->text('درود! چطور می‌توانم کمکتان کنم؟');
        }

        // نتیجه ابزار برگشته؛ جواب نهایی را از روی آن بساز
        if (($last['role'] ?? '') === 'tool') {
            $data = json_decode((string) ($last['content'] ?? '{}'), true);
            $items = is_array($data['products'] ?? null) ? $data['products'] : [];
            if ($items === []) {
                return $this->text('متأسفم، محصول مناسبی پیدا نشد.');
            }
            $lines = [];
            foreach (array_slice($items, 0, 2) as $p) {
                $lines[] = '«' . ($p['name'] ?? '') . '» با قیمت ' . ($p['price_text'] ?? '');
            }
            return $this->text('پیشنهاد من: ' . implode(' و ', $lines) . ' (پاسخ آزمایشی)');
        }

        // آخرین پیام کاربر را از داخل تگ بیرون بکش
        $user = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $user = (string) ($m['content'] ?? '');
                break;
            }
        }
        if (preg_match('~<user_message>\s*(.*?)\s*</user_message>~su', $user, $mm) === 1) {
            $user = $mm[1];
        }

        if ($tools !== [] && mb_strpos($user, 'ساعت') !== false) {
            $args = ['query' => '', 'max_price' => 0];
            if (mb_strpos($user, 'مردانه') !== false) {
                $args['category'] = 'مردانه';
            } elseif (mb_strpos($user, 'زنانه') !== false) {
                $args['category'] = 'زنانه';
            }
            if (preg_match('/(\d+)\s*میلیون/u', Text::normalize($user), $n) === 1) {
                $args['max_price'] = (int) $n[1] * 1000000;
            }
            return [
                'content'     => null,
                'tool_calls'  => [['id' => 'call_mock1', 'name' => 'search_products', 'arguments' => $args]],
                'raw_message' => [
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => [[
                        'id'       => 'call_mock1',
                        'type'     => 'function',
                        'function' => ['name' => 'search_products', 'arguments' => json_encode($args, JSON_UNESCAPED_UNICODE)],
                    ]],
                ],
                'usage'       => [],
            ];
        }

        return $this->text('درود! (پاسخ آزمایشی) بگویید چه ساعتی با چه بودجه‌ای می‌خواهید.');
    }

    private function text(string $text): array
    {
        return ['content' => $text, 'tool_calls' => [], 'raw_message' => ['role' => 'assistant', 'content' => $text], 'usage' => []];
    }
}
