<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateOfferRedemptions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'order_id' => ['type' => 'INT', 'unsigned' => true],
            'offer_id' => ['type' => 'INT', 'unsigned' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('order_id', true);
        $this->forge->addKey('offer_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('offer_id', 'offers', 'id', 'RESTRICT', 'RESTRICT');
        if (!$this->forge->createTable('offer_redemptions')) throw new \RuntimeException('Unable to create offer redemptions.');
    }
    public function down() { $this->forge->dropTable('offer_redemptions'); }
}
