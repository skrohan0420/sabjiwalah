<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddVerifiedDeliveryCompletion extends Migration
{
    public function up()
    {
        $this->forge->addColumn('order_delivery_pins', [
            'failed_attempts' => ['type'=>'INT','unsigned'=>true,'default'=>0],
            'attempt_window_ends_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addField([
            'order_id'=>['type'=>'INT','unsigned'=>true],
            'assignment_id'=>['type'=>'INT','unsigned'=>true],
            'delivery_user_id'=>['type'=>'INT','unsigned'=>true],
            'verified_at'=>['type'=>'DATETIME'],
            'cash_amount'=>['type'=>'DECIMAL','constraint'=>'10,2','null'=>true],
            'collected_at'=>['type'=>'DATETIME','null'=>true],
            'reconciled_by'=>['type'=>'INT','unsigned'=>true,'null'=>true],
            'reconciled_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('order_id',true);
        $this->forge->addKey(['reconciled_at','collected_at']);
        $this->forge->addForeignKey('order_id','orders','id','RESTRICT','RESTRICT');
        $this->forge->addForeignKey('assignment_id','delivery_assignments','id','RESTRICT','RESTRICT');
        $this->forge->addForeignKey('delivery_user_id','users','id','RESTRICT','RESTRICT');
        $this->forge->addForeignKey('reconciled_by','users','id','RESTRICT','RESTRICT');
        if (!$this->forge->createTable('delivery_completions')) throw new \RuntimeException('Unable to create delivery records.');
    }
    public function down()
    {
        $this->forge->dropTable('delivery_completions');
        $this->forge->dropColumn('order_delivery_pins',['failed_attempts','attempt_window_ends_at']);
    }
}
