<?php
declare(strict_types=1);

namespace App\Gateway;

use RuntimeException;

/** خطایی که پیامش برای نمایش به کاربر امن است. */
final class GatewayException extends RuntimeException
{
    private string $errorCode;
    private int $httpStatus;

    public function __construct(string $errorCode, string $userMessage, int $httpStatus)
    {
        parent::__construct($userMessage);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
