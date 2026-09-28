<?php
declare(strict_types=1);
namespace Ordely\Commerce\Infrastructure;

use Ordely\Core\Value\CanonicalJson;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Actor,AuditAction,EventType,SafePayload,Scope};
use Ordely\Operations\Infrastructure\{AuditLog,Outbox};
use Ordely\Shared\Id;

/** The document is a versioned, provider-independent projection, never a raw API payload. */
final readonly class ImportRepository
{
    public function __construct(private Sql $db,private OrderCipher $cipher) {}

    /** @param array<string,mixed> $run
     * @param array<string,mixed> $record */
    public function stage(array $run,array $record,bool $append): void
    {
        $kind=$record['kind']??null;$external=$record['externalId']??null;
        if (!in_array($kind,['order','product','variant','inventory'],true) || !is_string($external) || strlen($external)>255) { throw new \InvalidArgumentException('Invalid source record.'); }
        $merchant=bin2hex((string)$run['merchant_id']);$store=bin2hex((string)$run['store_id']);
        $existing=$this->db->one('SELECT * FROM commerce_sync_records WHERE run_id=? AND kind=? AND external_id=?',[(string)$run['id'],$kind,$external]);
        $id=$existing===null?Id::new():bin2hex((string)$existing['record_id']);
        if ($kind==='order') {
            if ($this->blocked($merchant,$store,$record)) { return; }
            if ($append && $existing!==null) {
                $old=$this->cipher->open($merchant,$store,$id,(string)$existing['document']);
                if ($old['updatedAt']!==$record['updatedAt']) { throw new \Ordely\Operations\Domain\Conflict('source_changed_during_pagination'); }
                $lines=[];foreach([...$old['lines'],...$record['lines']] as $line) { $lines[$line['externalId']]=$line; }$record['lines']=array_values($lines);
            }
        }
        $document=$kind==='order'?$this->cipher->seal($merchant,$store,$id,$record):CanonicalJson::encode($record);
        $version=$record['updatedAt']??null;
        $this->db->run('INSERT INTO commerce_sync_records(merchant_id,store_id,run_id,kind,external_id,record_id,source_version,document) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE document=?,source_version=?',
            [(string)$run['merchant_id'],(string)$run['store_id'],(string)$run['id'],$kind,$external,Id::bytes($id),$version,$document,$document,$version]);
    }

    /** @param array<string,mixed> $record */
    public function blocked(string $merchant,string $store,array $record): bool
    {
        return $this->db->one("SELECT 1 FROM commerce_privacy_blocks WHERE merchant_id=? AND store_id=? AND ((subject_type='shop') OR (subject_type='order' AND external_id=?) OR (subject_type='customer' AND external_id=?)) LIMIT 1",
            [Id::bytes($merchant),Id::bytes($store),(string)$record['externalId'],$record['customerId']??''])!==null;
    }

    /** Caller holds the run lock and has revalidated the active connection.
     * @param array<string,mixed> $run */
    public function publish(array $run): void
    {
        $merchant=bin2hex((string)$run['merchant_id']);$store=bin2hex((string)$run['store_id']);$provider=(string)$run['provider_key'];$scope=new Scope($merchant,$store);
        $rows=$this->db->run('SELECT * FROM commerce_sync_records WHERE run_id=? ORDER BY kind,external_id',[(string)$run['id']]);
        while($row=$rows->fetch()) {
            $kind=(string)$row['kind'];$record=$kind==='order'?$this->cipher->open($merchant,$store,bin2hex((string)$row['record_id']),(string)$row['document']):json_decode((string)$row['document'],true,64,JSON_THROW_ON_ERROR);
            if ($kind==='order' && $this->blocked($merchant,$store,$record)) { continue; }
            $existing=$this->db->one('SELECT * FROM commerce_records WHERE merchant_id=? AND store_id=? AND provider_key=? AND kind=? AND external_id=? FOR UPDATE',[(string)$run['merchant_id'],(string)$run['store_id'],$provider,$kind,(string)$row['external_id']]);
            if ($existing!==null && $row['source_version']!==null && $existing['source_version']!==null && strcmp((string)$existing['source_version'],(string)$row['source_version'])>0) {
                $this->db->run('UPDATE commerce_records SET last_run_id=? WHERE id=?',[(string)$run['id'],(string)$existing['id']]);continue;
            }
            $id=$existing===null?Id::new():bin2hex((string)$existing['id']);
            if ($kind==='order') {
                $oldLines=[];
                if ($existing!==null) {
                    $oldOrder=$this->cipher->open($merchant,$store,$id,(string)$existing['document']);
                    foreach($oldOrder['lines'] as $oldLine) { $oldLines[$oldLine['externalId']]=$oldLine['id']; }
                }
                foreach($record['lines'] as &$line) { $line['id']=$oldLines[$line['externalId']]??Id::new(); }
                unset($line);
            }
            // Observation time changes do not create a new business version.
            $hashable=$record;unset($hashable['observedAt']);$hash=hash('sha256',CanonicalJson::encode($hashable),true);
            $changed=$existing===null || !hash_equals((string)$existing['content_hash'],$hash) || !(bool)$existing['active'];
            $version=$existing===null?1:(int)$existing['version']+($changed?1:0);
            $document=$kind==='order'?$this->cipher->seal($merchant,$store,$id,$record):CanonicalJson::encode($record);
            $this->db->run('INSERT INTO commerce_records(id,merchant_id,store_id,provider_key,kind,external_id,parent_id,source_version,document,content_hash,version,last_run_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE parent_id=?,source_version=?,document=?,content_hash=?,version=?,active=TRUE,last_run_id=?,observed_at=UTC_TIMESTAMP(6)',
                [Id::bytes($id),(string)$run['merchant_id'],(string)$run['store_id'],$provider,$kind,(string)$row['external_id'],$record['parentId']??null,$row['source_version'],$document,$hash,$version,(string)$run['id'],
                    $record['parentId']??null,$row['source_version'],$document,$hash,$version,(string)$run['id']]);
            if ($changed && $kind==='order') { (new Outbox($this->db))->append($scope,EventType::OrderImported,$id,$version,new SafePayload(['order_id'=>$id,'version'=>$version])); }
        }
        // Absence means removal only for a successfully completed full catalog scan, never for the 60-day order window.
        $this->db->run("UPDATE commerce_records SET active=FALSE,version=version+1 WHERE merchant_id=? AND store_id=? AND provider_key=? AND kind<>'order' AND active=TRUE AND last_run_id<>?",[(string)$run['merchant_id'],(string)$run['store_id'],$provider,(string)$run['id']]);
        $this->db->run("UPDATE commerce_sync_runs SET status='completed',completed_at=UTC_TIMESTAMP(6) WHERE id=?",[(string)$run['id']]);
        $watermark=(new \DateTimeImmutable((string)$run['started_at'],new \DateTimeZone('UTC')))->modify('-5 minutes')->format('Y-m-d\TH:i:s.u\Z');
        $this->db->run('UPDATE commerce_sync_heads SET watermark=? WHERE merchant_id=? AND store_id=? AND provider_key=? AND run_id=?',[$watermark,(string)$run['merchant_id'],(string)$run['store_id'],$provider,(string)$run['id']]);
        $this->db->run('DELETE FROM commerce_sync_records WHERE run_id=?',[(string)$run['id']]);
        (new AuditLog($this->db))->append($scope,Actor::system(),AuditAction::ImportCompleted,bin2hex((string)$run['id']),new SafePayload());
    }
}
