<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Shared\Id;

final class FixtureCleanup
{
    /** Only call with the IDs allocated by the current test. Never run against an application DB. */
    public static function merchant(Sql $db,string $merchantId,string $userId): void
    {
        $name=(string)$db->run('SELECT DATABASE()')->fetchColumn();
        if(!str_ends_with($name,'_test')){throw new \LogicException('Fixture cleanup requires a test database.');}
        $db->transaction(function()use($db,$merchantId,$userId):void{
            foreach(['invoice_issue_intents','invoice_profiles','invoice_draft_revisions','invoice_drafts'] as $table){$db->run('DELETE FROM '.$table.' WHERE merchant_id=?',[Id::bytes($merchantId)]);}
            foreach(['commerce_privacy_blocks','commerce_records','commerce_sync_records','commerce_sync_tasks','commerce_sync_heads','commerce_sync_runs','shopify_webhook_events','shopify_link_intents','shopify_links','external_attempts','external_operations','event_deliveries','inbox_events','job_attempts','jobs','outbox_events','audit_logs','idempotency_requests','auth_sessions','membership_store_grants','store_provider_bindings','provider_connections','stores','memberships'] as $table){$db->run('DELETE FROM '.$table.' WHERE merchant_id=?',[Id::bytes($merchantId)]);}
            $db->run('DELETE FROM users WHERE id=?',[Id::bytes($userId)]);
            $db->run('DELETE FROM merchants WHERE id=?',[Id::bytes($merchantId)]);
        });
    }
}
