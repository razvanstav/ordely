<?php
declare(strict_types=1);
namespace Ordely\Commerce\Infrastructure;

use Ordely\Infrastructure\Database\Sql;

/** Abandoned temporary imports expire after seven days; published business records are untouched. */
final readonly class ImportRetention
{
    public function __construct(private Sql $db) {}
    public function clean(): int
    {
        return $this->db->transaction(function():int {
            // Match importer lock order; never delete a page being published.
            $heads=$this->db->run("SELECT h.* FROM commerce_sync_heads h JOIN commerce_sync_runs r ON r.id=h.run_id WHERE r.status='running' AND r.started_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 7 DAY) LIMIT 25 FOR UPDATE OF h SKIP LOCKED")->fetchAll();
            $count=0;
            foreach($heads as $head) {
                $run=$this->db->one("SELECT id FROM commerce_sync_runs WHERE id=? AND status='running' AND started_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 7 DAY) FOR UPDATE",[(string)$head['run_id']]);
                if ($run===null) { continue; }
                $this->db->run("UPDATE commerce_sync_runs SET status='cancelled' WHERE id=?",[(string)$run['id']]);
                $this->db->run('DELETE FROM commerce_sync_records WHERE run_id=?',[(string)$run['id']]);++$count;
            }
            return $count;
        });
    }
}
