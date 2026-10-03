<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInitialSabjiwalahSchema extends Migration
{
    public function up()
    {
        $this->createUsers();
        $this->createUserAddresses();
        $this->createProducts();
        $this->createOrders();
        $this->createOrderItems();
        $this->createOrderStatusHistory();
        $this->createDeliveryAssignments();
        $this->createOffers();
        $this->createPromotions();
    }

    public function down()
    {
        $this->forge->dropTable('promotions', true);
        $this->forge->dropTable('offers', true);
        $this->forge->dropTable('delivery_assignments', true);
        $this->forge->dropTable('order_status_history', true);
        $this->forge->dropTable('order_items', true);
        $this->forge->dropTable('orders', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('user_addresses', true);
        $this->forge->dropTable('users', true);
    }

    private function createUsers(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'           => ['type' => 'VARCHAR', 'constraint' => 40],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'role'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'customer'],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('email', false, true);
        $this->forge->addKey('role');
        $this->forge->addKey('status');
        $this->forge->createTable('users');
    }

    private function createUserAddresses(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'            => ['type' => 'VARCHAR', 'constraint' => 40],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'label'          => ['type' => 'VARCHAR', 'constraint' => 60],
            'recipient_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 30],
            'address_line_1' => ['type' => 'VARCHAR', 'constraint' => 255],
            'address_line_2' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'state'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'postal_code'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'is_default'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_addresses');
    }

    private function createProducts(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'            => ['type' => 'VARCHAR', 'constraint' => 40],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug'           => ['type' => 'VARCHAR', 'constraint' => 190],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'image'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'price'          => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'sale_price'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'unit'           => ['type' => 'VARCHAR', 'constraint' => 40],
            'stock_quantity' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('slug', false, true);
        $this->forge->addKey('is_active');
        $this->forge->createTable('products');
    }

    private function createOrders(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'             => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_number'    => ['type' => 'VARCHAR', 'constraint' => 40],
            'user_id'         => ['type' => 'INT', 'unsigned' => true],
            'subtotal'        => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'delivery_charge' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'total_amount'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'payment_method'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'cod'],
            'payment_status'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'pending'],
            'order_status'    => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'pending'],
            'customer_name'   => ['type' => 'VARCHAR', 'constraint' => 120],
            'customer_phone'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'address_line'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'city'            => ['type' => 'VARCHAR', 'constraint' => 120],
            'state'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'postal_code'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('order_number', false, true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('order_status');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('orders');
    }

    private function createOrderItems(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'          => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_id'     => ['type' => 'INT', 'unsigned' => true],
            'product_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'unit'         => ['type' => 'VARCHAR', 'constraint' => 40],
            'quantity'     => ['type' => 'INT', 'unsigned' => true],
            'unit_price'   => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'total_price'  => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('order_items');
    }

    private function createOrderStatusHistory(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_id'   => ['type' => 'INT', 'unsigned' => true],
            'old_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'new_status' => ['type' => 'VARCHAR', 'constraint' => 40],
            'changed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('changed_by');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('order_status_history');
    }

    private function createDeliveryAssignments(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'              => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_id'         => ['type' => 'INT', 'unsigned' => true],
            'delivery_user_id' => ['type' => 'INT', 'unsigned' => true],
            'assigned_by'      => ['type' => 'INT', 'unsigned' => true],
            'assigned_at'      => ['type' => 'DATETIME'],
            'completed_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('delivery_user_id');
        $this->forge->addKey('assigned_by');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('delivery_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('assigned_by', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('delivery_assignments');
    }

    private function createOffers(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'                  => ['type' => 'VARCHAR', 'constraint' => 40],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 150],
            'code'                 => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'type'                 => ['type' => 'VARCHAR', 'constraint' => 20],
            'value'                => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'minimum_order_amount' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'maximum_discount'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'starts_at'            => ['type' => 'DATETIME', 'null' => true],
            'ends_at'              => ['type' => 'DATETIME', 'null' => true],
            'usage_limit'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('code', false, true);
        $this->forge->addKey('is_active');
        $this->forge->createTable('offers');
    }

    private function createPromotions(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uid'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'image'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'position'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'starts_at'  => ['type' => 'DATETIME', 'null' => true],
            'ends_at'    => ['type' => 'DATETIME', 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('uid', false, true);
        $this->forge->addKey('position');
        $this->forge->addKey('is_active');
        $this->forge->createTable('promotions');
    }
}
