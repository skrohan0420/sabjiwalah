<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderDeliveryPins extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'order_id' => ['type' => 'INT', 'unsigned' => true],
            'pin_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'issued_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('order_id', true);
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'RESTRICT', 'RESTRICT');
        if (! $this->forge->createTable('order_delivery_pins')) throw new \RuntimeException('Unable to create delivery PIN storage.');
    }

    public function down()
    {
        $this->forge->dropTable('order_delivery_pins');
    }
}
