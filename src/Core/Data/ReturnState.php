<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
enum ReturnState: string { case Requested='requested'; case Approved='approved'; case Closed='closed'; case Cancelled='cancelled'; }
