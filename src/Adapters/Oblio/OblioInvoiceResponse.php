<?php
declare(strict_types=1);
namespace Ordely\Adapters\Oblio;
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Core\Data\{InvoiceDraft,InvoiceSnapshot};
use Ordely\Core\Value\{ExternalId,Money};

/** Offline parsing only. A create receipt is not proof of the issued invoice total. */
final class OblioInvoiceResponse
{
    /** @return array{series:string,number:string} */
    public static function receipt(#[\SensitiveParameter] string $body,InvoiceDraft $draft): array
    {
        $data=self::decode($body)['data']??null;
        if(!$data instanceof \stdClass||($data->seriesName??null)!==$draft->series->value||!is_string($data->number??null)||!preg_match('/^[0-9]{1,32}$/D',$data->number)){throw new ProviderFailure(ErrorCategory::Unknown);}
        return ['series'=>$data->seriesName,'number'=>$data->number];
    }
    /** The future transport must request the list scoped to this draft's company/series/number. */
    public static function confirmed(#[\SensitiveParameter] string $receiptBody,#[\SensitiveParameter] string $listBody,InvoiceDraft $draft): InvoiceSnapshot
    {
        $receipt=self::receipt($receiptBody,$draft);$data=self::decode($listBody)['data']??null;
        if(!is_array($data)||!array_is_list($data)||count($data)>100){throw new ProviderFailure(ErrorCategory::Unknown);}
        $matches=array_filter($data,static fn(mixed $row):bool=>$row instanceof \stdClass&&($row->seriesName??null)===$receipt['series']&&($row->number??null)===$receipt['number']);
        if(count($matches)!==1){throw new ProviderFailure(ErrorCategory::Unknown);}$row=array_values($matches)[0];
        if(($row->type??null)!=='Factura'||($row->draft??null)!=='0'||($row->canceled??null)!=='0'||($row->currency??null)!==$draft->total->currency->code||($row->precision??null)!==(string)$draft->total->currency->exponent||($row->useStock??null)!=='0'||$draft->details===null||($row->issueDate??null)!==$draft->details->issuedOn||($row->dueDate??null)!==$draft->details->dueOn||!is_string($row->total??null)||!is_string($row->id??null)||!preg_match('/^[0-9]{1,32}$/D',$row->id)){throw new ProviderFailure(ErrorCategory::Unknown);}
        try{
            // Oblio may return four decimal places. Discard only trailing zero precision, never round.
            $amount=str_contains($row->total,'.')?rtrim(rtrim($row->total,'0'),'.'):$row->total;
            $total=Money::decimal($amount,$draft->total->currency);
            if($total->minor!==$draft->total->minor){throw new \InvalidArgumentException();}
            $reference=new ExternalId('oblio:'.$row->id);
            return new InvoiceSnapshot($reference,$receipt['series'].' '.$receipt['number'],$total);
        }catch(\InvalidArgumentException|\OverflowException){throw new ProviderFailure(ErrorCategory::Unknown);}
    }
    /** @return array<string,mixed> */
    private static function decode(string $body): array
    {
        if(strlen($body)>1048576){throw new ProviderFailure(ErrorCategory::Unknown);}
        try{$decoded=json_decode($body,false,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ProviderFailure(ErrorCategory::Unknown);}
        if(!$decoded instanceof \stdClass||!in_array($decoded->status??null,[200,'200'],true)){throw new ProviderFailure(ErrorCategory::Unknown);}return get_object_vars($decoded);
    }
}
