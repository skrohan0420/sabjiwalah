<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliveryLocations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'delivery_user_id' => ['type' => 'INT', 'unsigned' => true],
            'order_id' => ['type' => 'INT', 'unsigned' => true],
            'assignment_id' => ['type' => 'INT', 'unsigned' => true],
            'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'accuracy' => ['type' => 'DECIMAL', 'constraint' => '7,2', 'null' => true],
            'captured_at_ms' => ['type' => 'BIGINT', 'unsigned' => true],
            'recorded_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('delivery_user_id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('delivery_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('assignment_id', 'delivery_assignments', 'id', 'CASCADE', 'CASCADE');
        if (!$this->forge->createTable('delivery_locations', true, ['ENGINE' => 'InnoDB'])) throw new \RuntimeException('Unable to create delivery locations.');
    }

    public function down() { $this->forge->dropTable('delivery_locations', true); }
}
