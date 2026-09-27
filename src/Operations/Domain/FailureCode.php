<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
enum FailureCode:string
{
    case Transient='transient';case Unknown='unknown';case Invalid='invalid';case ScopeInactive='scope_inactive';case LeaseExpired='lease_expired';case HandlerMissing='handler_missing';
}
