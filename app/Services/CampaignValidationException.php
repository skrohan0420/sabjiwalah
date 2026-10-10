<?php
namespace App\Services;

class CampaignValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Please correct the campaign fields.');
    }
}
