<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOtpRateLimits extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'bucket_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'attempts' => ['type' => 'INT', 'unsigned' => true],
            'expires_at' => ['type' => 'BIGINT', 'unsigned' => true],
            'last_used_at' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey('bucket_key', true);
        $this->forge->addKey('expires_at');
        if (! $this->forge->createTable('otp_rate_limits')) {
            throw new \RuntimeException('Unable to create OTP rate limits.');
        }
    }

    public function down()
    {
        $this->forge->dropTable('otp_rate_limits');
    }
}
