<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAdminOrderIndexes extends Migration
{
    public function up()
    {
        $this->forge->addKey(['created_at', 'id'], false, false, 'orders_admin_created');
        $this->forge->addKey(['order_status', 'created_at', 'id'], false, false, 'orders_admin_status_created');
        if (! $this->forge->processIndexes('orders')) {
            throw new \RuntimeException('Unable to create admin order indexes.');
        }
    }

    public function down()
    {
        $this->forge->dropKey('orders', 'orders_admin_status_created');
        $this->forge->dropKey('orders', 'orders_admin_created');
    }
}
