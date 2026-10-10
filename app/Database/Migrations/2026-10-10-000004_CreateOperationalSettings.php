<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateOperationalSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true],
            'shop_open'=>['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'orders_paused'=>['type'=>'TINYINT','constraint'=>1,'default'=>0],
            'delivery_charge'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>40],
            'free_delivery_minimum'=>['type'=>'DECIMAL','constraint'=>'8,2','null'=>true],
            'minimum_order_amount'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>0],
            'opens_at'=>['type'=>'VARCHAR','constraint'=>5,'null'=>true],
            'closes_at'=>['type'=>'VARCHAR','constraint'=>5,'null'=>true],
            'maximum_active_orders'=>['type'=>'INT','unsigned'=>true,'null'=>true],
            'revision'=>['type'=>'INT','unsigned'=>true,'default'=>1],
            'updated_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        if (!$this->forge->createTable('operational_settings')) throw new \RuntimeException('Unable to create settings.');
        if (!$this->db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,
            'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')])) throw new \RuntimeException('Unable to initialize settings.');
        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'changed_by'=>['type'=>'INT','unsigned'=>true],
            'old_values'=>['type'=>'TEXT'], 'new_values'=>['type'=>'TEXT'], 'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addForeignKey('changed_by','users','id','RESTRICT','RESTRICT');
        if (!$this->forge->createTable('operational_setting_history')) throw new \RuntimeException('Unable to create setting history.');
    }
    public function down() { $this->forge->dropTable('operational_setting_history'); $this->forge->dropTable('operational_settings'); }
}
