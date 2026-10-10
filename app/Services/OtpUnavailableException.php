<?php

namespace App\Services;

class OtpUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Phone verification is unavailable. Please contact the shop.');
    }
}
