<?php

declare(strict_types=1);

namespace Ordely\Adapters\Shopify;

final readonly class WebhookSignature
{
    public function __construct(private AppConfig $config) {}

    public function valid(#[\SensitiveParameter] string $rawBody, string $header): bool
    {
        // Compare the decoded 32-byte MAC; parse JSON only after this succeeds.
        if (strlen($header) !== 44) { return false; }
        $signature = base64_decode($header, true);
        return $signature !== false && strlen($signature) === 32
            && hash_equals(hash_hmac('sha256', $rawBody, $this->config->secret(), true), $signature);
    }
}
