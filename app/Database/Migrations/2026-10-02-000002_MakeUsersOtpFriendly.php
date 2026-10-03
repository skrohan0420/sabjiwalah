<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeUsersOtpFriendly extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('users', [
            'email' => [
                'name'       => 'email',
                'type'       => 'VARCHAR',
                'constraint' => 190,
                'null'       => true,
            ],
            'password_hash' => [
                'name'       => 'password_hash',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);

        if (! $this->indexExists('users_phone_unique')) {
            $this->db->query('ALTER TABLE users ADD UNIQUE KEY users_phone_unique (phone)');
        }
    }

    public function down()
    {
        if ($this->indexExists('users_phone_unique')) {
            $this->db->query('ALTER TABLE users DROP INDEX users_phone_unique');
        }

        $this->forge->modifyColumn('users', [
            'email' => [
                'name'       => 'email',
                'type'       => 'VARCHAR',
                'constraint' => 190,
                'null'       => false,
            ],
            'password_hash' => [
                'name'       => 'password_hash',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ]);
    }

    private function indexExists(string $name): bool
    {
        $indexes = $this->db->query('SHOW INDEX FROM users WHERE Key_name = ?', [$name])->getResultArray();

        return $indexes !== [];
    }
}
