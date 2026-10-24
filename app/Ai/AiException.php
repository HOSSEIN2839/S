<?php
declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

final class AiException extends RuntimeException
{
    /** auth | rate_limit | network | server | bad_response | config */
    private string $kind;

    public function __construct(string $kind, string $message)
    {
        parent::__construct($message);
        $this->kind = $kind;
    }

    public function kind(): string
    {
        return $this->kind;
    }
}
