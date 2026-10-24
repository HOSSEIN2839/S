<?php
declare(strict_types=1);

namespace App\Gateway;

/**
 * بررسی جواب هوش مصنوعی قبل از رسیدن به کاربر:
 * لو رفتن System Prompt (با کد canary)، شبیه کلید API، لینک و عکس بیرونی، تگ HTML.
 */
final class OutputGuard
{
    public const SAFE_REPLY = 'متأسفم، نتوانستم این درخواست را انجام دهم. اگر درباره ساعت یا بودجه‌تان سؤال دارید، بپرسید.';
    private const MAX_REPLY_CHARS = 1500;

    private string $canary;

    public function __construct(string $canary)
    {
        $this->canary = $canary;
    }

    /**
     * @return array{text:string,blocked:bool,reason:?string}
     */
    public function filter(string $text): array
    {
        if ($this->canary !== '' && stripos($text, $this->canary) !== false) {
            return ['text' => self::SAFE_REPLY, 'blocked' => true, 'reason' => 'canary_leak'];
        }

        $leakPatterns = [
            '/\bsk-[A-Za-z0-9_\-]{16,}/',
            '/AIza[0-9A-Za-z_\-]{20,}/',
            '/Bearer\s+[A-Za-z0-9._\-]{20,}/i',
            '/api[_\- ]?key\s*[:=]/i',
            '/<\/?user_message>/i',
            '/<\/?system_rules>/i',
        ];
        foreach ($leakPatterns as $p) {
            if (preg_match($p, $text) === 1) {
                return ['text' => self::SAFE_REPLY, 'blocked' => true, 'reason' => 'secret_or_prompt_marker'];
            }
        }

        // تگ‌های HTML، تصویر مارک‌داون و لینک‌ها حذف می‌شوند (لینک محصول فقط در کارت‌ها می‌آید).
        $clean = strip_tags($text);
        $clean = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $clean) ?? $clean;
        $clean = preg_replace('~https?://\S+~iu', '', $clean) ?? $clean;
        $clean = trim($clean);

        if ($clean === '') {
            return ['text' => self::SAFE_REPLY, 'blocked' => true, 'reason' => 'empty_after_clean'];
        }
        if (mb_strlen($clean) > self::MAX_REPLY_CHARS) {
            $clean = mb_substr($clean, 0, self::MAX_REPLY_CHARS) . '…';
        }
        return ['text' => $clean, 'blocked' => false, 'reason' => null];
    }
}
