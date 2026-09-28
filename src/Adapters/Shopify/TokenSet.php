<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;
use Ordely\Integrations\Domain\Secrets;

final readonly class TokenSet
{
    public function __construct(public Secrets $secrets, public int $expiresAt, public int $refreshExpiresAt)
    {
        $values=$secrets->reveal();
        foreach(['accessToken','refreshToken','scope'] as $key){if(!isset($values[$key])){throw new \InvalidArgumentException('Invalid token set.');}}
        if($expiresAt<1||$refreshExpiresAt<1){throw new \InvalidArgumentException('Invalid token expiry.');}
    }

    /** @param array<string,mixed> $response */
    public static function fromResponse(#[\SensitiveParameter] array $response,int $requestedAt): self
    {
        foreach(['access_token','refresh_token'] as $key){if(!is_string($response[$key]??null)||$response[$key]===''){throw new ShopifyUnavailable();}}
        foreach(['expires_in','refresh_token_expires_in'] as $key){if(!is_int($response[$key]??null)||$response[$key]<1||$response[$key]>31536000){throw new ShopifyUnavailable();}}
        if(!is_string($response['scope']??null)){throw new ShopifyUnavailable();}
        return new self(new Secrets([
            'accessToken'=>$response['access_token'],'refreshToken'=>$response['refresh_token'],
            'scope'=>json_encode($response['scope'],JSON_THROW_ON_ERROR),
        ]),$requestedAt+$response['expires_in'],$requestedAt+$response['refresh_token_expires_in']);
    }

    public function stored(): Secrets
    {
        return new Secrets([...$this->secrets->reveal(),'expiresAt'=>(string)$this->expiresAt,'refreshExpiresAt'=>(string)$this->refreshExpiresAt]);
    }

    public static function fromStored(Secrets $stored): self
    {
        $values=$stored->reveal();
        foreach(['expiresAt','refreshExpiresAt'] as $key){if(!isset($values[$key])||!ctype_digit($values[$key])||strlen($values[$key])>12){throw new \RuntimeException('Token unavailable.');}}
        return new self($stored,(int)$values['expiresAt'],(int)$values['refreshExpiresAt']);
    }
}
