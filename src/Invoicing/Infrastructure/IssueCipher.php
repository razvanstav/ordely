<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;
use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Integrations\Infrastructure\SecretCipher;

final readonly class IssueCipher
{
    public function __construct(private SecretCipher $cipher) {}
    /** @param array<string,mixed> $row
     * @param array<string,mixed> $snapshot */
    public function seal(array $row,#[\SensitiveParameter] array $snapshot): string {return $this->context($row)->seal(bin2hex($row['merchant_id']),bin2hex($row['store_id']),bin2hex($row['id']),$snapshot);}
    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    public function open(#[\SensitiveParameter] array $row): array
    {
        try{return $this->context($row)->open(bin2hex($row['merchant_id']),bin2hex($row['store_id']),bin2hex($row['id']),$row['snapshot_envelope']);}
        catch(\Throwable){throw new \RuntimeException('Invoice issue snapshot unavailable.');}
    }
    /** @param array<string,mixed> $row */
    private function context(array $row): OrderCipher {return new OrderCipher($this->cipher,'invoice-issue:'.bin2hex($row['order_id']).':'.$row['draft_version'].':'.bin2hex($row['connection_id']).':'.$row['connection_version'].':'.$row['profile_version']);}
}
