<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
enum AuditAction: string
{
    case ImportStarted='import_started';case ImportCompleted='import_completed';case PrivacyProcessed='privacy_processed';case OrderViewed='order_viewed';
    case IntegrationLinked='integration_linked';case IntegrationTokenRefreshed='integration_token_refreshed';case IntegrationWebhookReceived='integration_webhook_received';
    case ConnectionCreated='connection_created';case ConnectionReencrypted='connection_reencrypted';case CredentialsReplaced='credentials_replaced';case ConnectionRevoked='connection_revoked';case ConnectionBound='connection_bound';case ConnectionUnbound='connection_unbound';
    case StoreCreated='store_created';case StoreRenamed='store_renamed';case EventConsumed='event_consumed';
    case JobClaimed='job_claimed';case JobSucceeded='job_succeeded';case JobRetried='job_retried';case JobDead='job_dead';case JobRequeued='job_requeued';
    case ExternalStarted='external_started';case ExternalConfirmed='external_confirmed';case ExternalUnknown='external_unknown';case ExternalFailed='external_failed';case ExternalReconciled='external_reconciled';
}
