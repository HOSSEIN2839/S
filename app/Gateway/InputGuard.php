<?php
declare(strict_types=1);

namespace App\Gateway;

use App\Support\Text;

/**
 * بررسی و پاک‌سازی پیام کاربر قبل از رسیدن به بخش پرامپت.
 * این لایه یک سد اول است؛ سد اصلی System Prompt و بررسی خروجی است.
 */
final class InputGuard
{
    private int $maxChars;

    /** الگوهای رایج تزریق پرامپت (انگلیسی و فارسی). روی متن نرمال‌شده بررسی می‌شوند. */
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?(the\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|rules?)/i',
        '/disregard\s+(all\s+)?(the\s+)?(previous|prior|above|earlier)/i',
        '/(reveal|show|print|repeat|tell\s+me|display)\s+(me\s+)?(your|the)\s+(system\s+|hidden\s+|initial\s+)?(prompt|instructions?|rules)/i',
        '/system\s*prompt/i',
        '/developer\s+mode|jailbreak|\bDAN\s+mode\b/i',
        '/you\s+are\s+now\s+(a|an|the)\s+/i',
        '/(دستور(ات|ها)?|قوانین|قواعد|پرامپت|پرامت)\s*(های\s*)?(قبلی|پیشین|بالا)(\s*(ت|تان|خود))?\s*(را|رو)?\s*(نادیده|فراموش|کنار)/u',
        '/(پرامپت|پرومپت|پرامت|دستورالعمل)\s*(های\s*)?(سیستم|سیستمی|اولیه|داخلی|مخفی)/u',
        '/(قوانین|دستورات|تنظیمات|دستورالعمل‌?های?)\s*(خودت|خودتان|داخلی)\s*(را|رو)?\s*(بگو|بنویس|نشان\s*بده|فاش|لو\s*بده)/u',
        '/از\s*این\s*به\s*بعد\s*(تو|شما)\s*/u',
        '/نقش\s*(خود|خودت|ات)\s*(را|رو)?\s*(عوض|تغییر)/u',
        '/حالت\s*(توسعه\s*دهنده|دولوپر|جیلبریک)/u',
    ];

    public function __construct(int $maxChars)
    {
        $this->maxChars = $maxChars;
    }

    /**
     * @return array{ok:bool,error:?string,text:string,flagged:bool,reason:?string}
     */
    public function inspect(string $raw): array
    {
        if (!mb_check_encoding($raw, 'UTF-8')) {
            return $this->fail('پیام نامعتبر است.');
        }

        // حذف کاراکترهای کنترلی و کاراکترهای پنهان جهت‌دهی (نیم‌فاصله ZWNJ حفظ می‌شود)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw) ?? '';
        $text = preg_replace('/[\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';
        $text = trim($text);

        $len = mb_strlen($text);
        if ($len < 1) {
            return $this->fail('پیام نمی‌تواند خالی باشد.');
        }
        if ($len > $this->maxChars) {
            return $this->fail('پیام خیلی طولانی است؛ حداکثر ' . $this->maxChars . ' حرف.');
        }

        $normalized = Text::normalize($text);
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return ['ok' => true, 'error' => null, 'text' => $text, 'flagged' => true, 'reason' => 'injection_pattern'];
            }
        }

        return ['ok' => true, 'error' => null, 'text' => $text, 'flagged' => false, 'reason' => null];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'text' => '', 'flagged' => false, 'reason' => null];
    }
}
