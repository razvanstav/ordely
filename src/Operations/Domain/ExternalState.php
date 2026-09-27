<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
enum ExternalState:string { case Pending='PENDING';case InFlight='IN_FLIGHT';case Confirmed='CONFIRMED';case Retryable='RETRYABLE';case Failed='FAILED';case Unknown='UNKNOWN'; }
