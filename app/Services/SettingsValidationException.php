<?php

namespace App\Services;

class SettingsValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Please correct the settings fields.');
    }
}
