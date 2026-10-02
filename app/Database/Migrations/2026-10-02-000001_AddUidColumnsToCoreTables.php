<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUidColumnsToCoreTables extends Migration
{
    private array $tables = [
        'users'                  => 'usr',
        'user_addresses'         => 'adr',
        'products'               => 'prd',
        'orders'                 => 'ord',
        'order_items'            => 'itm',
        'order_status_history'   => 'osh',
        'delivery_assignments'   => 'das',
        'offers'                 => 'off',
        'promotions'             => 'pro',
    ];

    public function up()
    {
        foreach ($this->tables as $table => $prefix) {
            if (! $this->db->fieldExists('uid', $table)) {
                $this->forge->addColumn($table, [
                    'uid' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 40,
                        'null'       => true,
                        'after'      => 'id',
                    ],
                ]);
            }

            $this->backfillUids($table, $prefix);
            $this->ensureUniqueIndex($table);

            $this->forge->modifyColumn($table, [
                'uid' => [
                    'name'       => 'uid',
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => false,
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (array_keys(array_reverse($this->tables, true)) as $table) {
            if ($this->db->fieldExists('uid', $table)) {
                $this->dropUniqueIndex($table);
                $this->forge->dropColumn($table, 'uid');
            }
        }
    }

    private function backfillUids(string $table, string $prefix): void
    {
        $rows = $this->db->table($table)
            ->select('id')
            ->groupStart()
                ->where('uid', null)
                ->orWhere('uid', '')
            ->groupEnd()
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $this->db->table($table)
                ->where('id', (int) $row['id'])
                ->update(['uid' => $this->generateUid($prefix)]);
        }
    }

    private function generateUid(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(12));
    }

    private function ensureUniqueIndex(string $table): void
    {
        $indexName = $this->indexName($table);

        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        $this->db->query(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (uid)',
            $this->db->escapeIdentifiers($indexName),
            $this->db->escapeIdentifiers($table),
        ));
    }

    private function dropUniqueIndex(string $table): void
    {
        $indexName = $this->indexName($table);

        if (! $this->hasIndex($table, $indexName)) {
            return;
        }

        $this->db->query(sprintf(
            'DROP INDEX %s ON %s',
            $this->db->escapeIdentifiers($indexName),
            $this->db->escapeIdentifiers($table),
        ));
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach ($this->db->getIndexData($table) as $index) {
            if (($index->name ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function indexName(string $table): string
    {
        return $table . '_uid_unique';
    }
}
