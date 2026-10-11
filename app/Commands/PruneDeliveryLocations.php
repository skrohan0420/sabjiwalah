<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PruneDeliveryLocations extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'delivery:prune-locations';
    protected $description = 'Delete expired latest rider locations; clear inactive and reassigned samples.';

    public function run(array $params)
    {
        $db = db_connect();
        $locations = $db->protectIdentifiers('delivery_locations', true);
        $orders = $db->protectIdentifiers('orders', true);
        $assignments = $db->protectIdentifiers('delivery_assignments', true);
        $users = $db->protectIdentifiers('users', true);
        if (!$db->query("UPDATE $locations l JOIN $orders o ON o.id = l.order_id JOIN $users u ON u.id = l.delivery_user_id SET l.latitude = NULL, l.longitude = NULL, l.accuracy = NULL WHERE o.order_status <> 'out_for_delivery' OR u.status <> 'active' OR u.role <> 'delivery' OR EXISTS (SELECT 1 FROM $assignments a WHERE a.order_id = l.order_id AND a.id > l.assignment_id)")) throw new \RuntimeException('Unable to clear inactive locations.');
        // Drain the fixed cutoff in bounded batches so a busy interval cannot extend retention.
        $cutoff = gmdate('Y-m-d H:i:s'); $removed = 0;
        do {
            if (!$db->query("DELETE FROM $locations WHERE expires_at <= ? ORDER BY expires_at LIMIT 1000", [$cutoff])) throw new \RuntimeException('Unable to prune locations.');
            $batch = $db->affectedRows(); $removed += $batch;
        } while ($batch === 1000);
        CLI::write('Removed ' . $removed . ' expired rider location records.');
    }
}
