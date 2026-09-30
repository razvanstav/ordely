<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;

use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Integrations\Infrastructure\SecretCipher;

final readonly class PreparationCipher
{
    public function __construct(private SecretCipher $cipher) {}
    /** @param array<string,mixed> $snapshot */
    public function seal(string $merchant,string $store,string $order,int $orderVersion,int $profileVersion,int $version,#[\SensitiveParameter] array $snapshot): string
    {
        return $this->context($orderVersion,$profileVersion,$version)->seal($merchant,$store,$order,$snapshot);
    }
    /** @return array<string,mixed> */
    public function open(string $merchant,string $store,string $order,int $orderVersion,int $profileVersion,int $version,#[\SensitiveParameter] string $envelope): array
    {
        try{return $this->context($orderVersion,$profileVersion,$version)->open($merchant,$store,$order,$envelope);}
        catch(\Throwable){throw new \RuntimeException('Invoice preparation unavailable.');}
    }
    private function context(int $orderVersion,int $profileVersion,int $version): OrderCipher
    {
        if($orderVersion<1||$profileVersion<0||$version<1){throw new \InvalidArgumentException('Invalid preparation version.');}
        return new OrderCipher($this->cipher,'invoice-preparation:'.$orderVersion.':'.$profileVersion.':'.$version);
    }
}
