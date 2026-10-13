<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveUnusedUserPasswordHash extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('password_hash', 'users')) {
            $this->forge->dropColumn('users', 'password_hash');
        }
    }

    public function down()
    {
        // Legacy hash values are intentionally discarded and cannot be restored.
        if (! $this->db->fieldExists('password_hash', 'users')) {
            $this->forge->addColumn('users', [
                'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
        }
    }
}
