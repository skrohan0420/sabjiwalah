<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderDeliveryPin extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'delivery_latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'delivery_longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', ['delivery_latitude', 'delivery_longitude']);
    }
}
