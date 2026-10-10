<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliveryPersonnelProfiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'availability' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'offline'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('user_id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        if (! $this->forge->createTable('delivery_personnel_profiles')) throw new \RuntimeException('Unable to create delivery profiles.');
    }

    public function down()
    {
        $this->forge->dropTable('delivery_personnel_profiles');
    }
}
