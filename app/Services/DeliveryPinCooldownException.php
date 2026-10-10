<?php

namespace App\Services;

class DeliveryPinCooldownException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Wait before generating another delivery PIN.');
    }
}
