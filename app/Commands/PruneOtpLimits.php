<?php

namespace App\Commands;

use App\Services\OtpService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PruneOtpLimits extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'otp:prune';
    protected $description = 'Remove up to 1000 expired OTP rate-limit rows without resetting active cooldowns.';

    public function run(array $params)
    {
        $db = db_connect();
        $table = $db->protectIdentifiers('otp_rate_limits', true);
        $now = time();
        if (! $db->query('DELETE FROM ' . $table . ' WHERE expires_at <= ? AND last_used_at <= ? ORDER BY expires_at LIMIT 1000', [$now, $now - OtpService::RESEND_SECONDS])) {
            throw new \RuntimeException('Unable to prune OTP limits.');
        }
        CLI::write('Removed ' . $db->affectedRows() . ' expired OTP rate-limit rows.');
    }
}
