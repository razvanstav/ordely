<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
enum AuditAction: string
{
    case StoreCreated='store_created';case StoreRenamed='store_renamed';case EventConsumed='event_consumed';
    case JobClaimed='job_claimed';case JobSucceeded='job_succeeded';case JobRetried='job_retried';case JobDead='job_dead';case JobRequeued='job_requeued';
    case ExternalStarted='external_started';case ExternalConfirmed='external_confirmed';case ExternalUnknown='external_unknown';case ExternalFailed='external_failed';case ExternalReconciled='external_reconciled';
}
