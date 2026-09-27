<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
enum ShipmentState: string { case Created='created'; case InTransit='in_transit'; case Delivered='delivered'; case Cancelled='cancelled'; }
