<?php

namespace App\Services;

class OtpRateLimitException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Too many OTP requests or attempts. Please wait before trying again.');
    }
}
