<?php
declare(strict_types=1);
namespace Ordely\Integrations\Domain;
enum ProviderKind: string { case Commerce='commerce'; case Carrier='carrier'; case Invoice='invoice'; }
