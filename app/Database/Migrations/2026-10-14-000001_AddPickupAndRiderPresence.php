<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddPickupAndRiderPresence extends Migration
{
    public function up()
    {
        $this->forge->addColumn('operational_settings', ['pickup_latitude'=>['type'=>'DECIMAL','constraint'=>'10,7','null'=>true], 'pickup_longitude'=>['type'=>'DECIMAL','constraint'=>'10,7','null'=>true]]);
        $this->forge->addField(['delivery_user_id'=>['type'=>'INT','unsigned'=>true], 'latitude'=>['type'=>'DECIMAL','constraint'=>'10,7'], 'longitude'=>['type'=>'DECIMAL','constraint'=>'10,7'], 'accuracy'=>['type'=>'FLOAT'], 'captured_at_ms'=>['type'=>'BIGINT','unsigned'=>true], 'recorded_at'=>['type'=>'DATETIME'], 'expires_at'=>['type'=>'DATETIME']]);
        $this->forge->addKey('delivery_user_id', true); $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('delivery_user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('delivery_rider_presence');
    }
    public function down() { $this->forge->dropTable('delivery_rider_presence'); $this->forge->dropColumn('operational_settings',['pickup_latitude','pickup_longitude']); }
}
