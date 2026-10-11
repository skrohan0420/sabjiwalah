<?php
namespace App\Services;
class DeliveryVerificationException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422, public readonly ?string $reason = null, public readonly int $retryAfter = 0) { parent::__construct($message); }
}
