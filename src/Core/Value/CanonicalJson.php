<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) { throw new \InvalidArgumentException('Floating point is not allowed in operation payloads.'); }
        if ($value instanceof \JsonSerializable) { return self::normalize($value->jsonSerialize()); }
        if ($value instanceof \BackedEnum) { return $value->value; }
        if ($value instanceof \DateTimeInterface) { return \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'); }
        if (is_object($value)) {
            $properties = get_object_vars($value); ksort($properties,SORT_STRING);
            foreach ($properties as $key=>$item) { $properties[$key]=self::normalize($item); }
            return (object) $properties;
        }
        if (is_array($value)) { if (!array_is_list($value)) { ksort($value,SORT_STRING); } return array_map(self::normalize(...),$value); }
        if (is_resource($value)) { throw new \InvalidArgumentException('Resources are not payloads.'); }
        return $value;
    }
}
