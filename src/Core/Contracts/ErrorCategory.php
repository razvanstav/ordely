<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
enum ErrorCategory: string
{
    case Validation = 'validation';
    case Authentication = 'authentication';
    case Unsupported = 'unsupported';
    case Transient = 'transient';
    case Unknown = 'unknown';
    case Conflict = 'conflict';
    case NotFound = 'not_found';
}
