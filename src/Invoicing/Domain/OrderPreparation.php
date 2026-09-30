<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Domain;

use Ordely\Core\Value\{Currency,Money};

/** Read-only proposal from a normalized CMS order. Never an InvoiceDraft or authority to issue. */
final class OrderPreparation
{
    /** @param array<string,mixed> $order
     * @param array<string,mixed>|null $profile
     * @return array<string,mixed> */
    public static function build(#[\SensitiveParameter] array $order,#[\SensitiveParameter] ?array $profile): array
    {
        $issues=[];
        $add=static function(string $code,string $path,string $message)use(&$issues):void{$issues[]=['code'=>$code,'path'=>$path,'message'=>$message];};
        $billing=$order['billingAddress']??null;
        if(!is_array($billing)){$billing=[];$add('billing_address_missing','customer.address','Adresa de facturare lipsește din CMS. Adresa de livrare nu o înlocuiește automat.');}
        $address=[];
        foreach(['street'=>'address1','streetExtra'=>'address2','city'=>'city','region'=>'provinceCode','postalCode'=>'zip','country'=>'countryCodeV2'] as $key=>$field){$address[$key]=self::text($billing[$field]??null);}
        foreach(['street'=>'Strada','city'=>'Localitatea','country'=>'Țara'] as $key=>$label){if($address[$key]===null){$add('customer_address_missing','customer.address.'.$key,$label.' lipsește din adresa de facturare.');}}
        $company=self::text($billing['company']??null);$name=$company??self::text($billing['name']??null);
        if($name===null){$add('customer_name_missing','customer.name','Numele destinatarului lipsește din datele de facturare.');}
        $add('customer_type_unconfirmed','customer.type','Confirmă persoană fizică sau juridică; o adresă fără firmă nu confirmă automat tipul clientului.');
        if($company!==null){$add('customer_tax_id_missing','customer.taxId','Firma este prezentă în CMS, dar identificarea fiscală nu este disponibilă în importul actual.');}
        $customer=['name'=>$name,'company'=>$company,'contactName'=>self::text($billing['name']??null),'type'=>null,'taxId'=>null,'vatStatus'=>null,'email'=>self::text($order['email']??null),'address'=>$address];
        if($profile===null){$add('profile_missing','seller.profile','Alege și salvează firma și seria în Integrări → Oblio.');}
        elseif(($profile['needsVerification']??true)!==false){$add('profile_needs_verification','seller.profile','Conexiunea firmei/seriei s-a schimbat. Reverifică profilul în Oblio.');}
        $add('seller_details_missing','seller.details','Profilul salvat conține firma și seria; adresa și datele fiscale complete ale emitentului urmează să fie completate.');
        $seller=$profile===null?null:array_intersect_key($profile,array_flip(['companyName','companyId','series','version','connectionVersion','needsVerification']));
        $totals=[];$currency=null;
        foreach(['original','current','discount','tax','shipping','received','refunded','outstanding'] as $key){
            $totals[$key]=self::amount($order['totals'][$key]??null);
        }
        if($totals['current']===null){$add('order_total_missing','totals.current','Totalul curent lipsește din import.');}
        else{$currency=$totals['current']['currency'];if(!in_array($currency,['RON','EUR'],true)||$totals['current']['exponent']!==2){$add('currency_unsupported','currency','Moneda sau precizia importată nu este încă suportată pentru facturare.');}}
        if($currency==='EUR'){$add('exchange_rate_missing','document.exchangeRate','Cursul și configurația de emitere în valută trebuie stabilite explicit.');}
        if(($order['cancelledAt']??null)!==null){$add('order_cancelled','source','Comanda este anulată; verifică documentele originale înainte de facturare.');}
        if(($totals['refunded']['minor']??'0')!=='0'||($totals['original']!==null&&$totals['current']!==null&&$totals['original']['minor']!==$totals['current']['minor'])){$add('order_changed','source','Comanda are modificări sau rambursări. Sumele originale și curente trebuie reconciliate înainte de facturare.');}
        $basis=match($order['taxesIncluded']??null){true=>'tax_inclusive',false=>'tax_exclusive',default=>null};
        if($basis===null){$add('price_basis_missing','priceBasis','CMS nu precizează dacă prețurile includ taxe.');}
        $lines=[];$seen=[];
        $raw=$order['lines']??null;
        if(!is_array($raw)||!array_is_list($raw)){throw new \InvalidArgumentException('Invalid normalized order.');}
        if(count($raw)<1||count($raw)>50){$add('line_count_unsupported','lines','Ciorna de facturare acceptă între 1 și 50 de linii; comanda necesită verificare.');}
        foreach($raw as $index=>$line){
            if(!is_array($line)){throw new \InvalidArgumentException('Invalid normalized line.');}
            $path='lines.'.$index;$id=self::text($line['id']??null);
            if($id===null||isset($seen[$id])){throw new \InvalidArgumentException('Invalid normalized line identity.');}$seen[$id]=true;
            $quantity=$line['quantity']??null;$current=$line['currentQuantity']??null;
            if(!is_int($quantity)||!is_int($current)||$quantity<0||$current<0||$current>$quantity){throw new \InvalidArgumentException('Invalid normalized quantity.');}
            if($quantity<1||$quantity>1000000||$quantity!==$current){$add('quantity_requires_review',$path.'.quantity','Cantitatea inițială și cea curentă necesită verificare înainte de facturare.');}
            $prices=[];foreach(['originalUnitPrice','originalTotal','lineDiscountedTotal'] as $key){$prices[$key]=self::amount($line[$key]??null);if($prices[$key]===null){$add('line_amount_missing',$path.'.'.$key,'O sumă a liniei lipsește din importul CMS.');}}
            $discounts=self::amounts($line['discountAllocations']??null);$taxes=[];
            if($discounts===null){$add('line_discounts_missing',$path.'.discounts','Datele reducerilor lipsesc din importul liniei.');}
            if(!is_array($line['taxes']??null)||!array_is_list($line['taxes'])){$add('line_taxes_missing',$path.'.taxes','Datele taxelor lipsesc din importul liniei.');}
            else{foreach($line['taxes'] as $tax){if(!is_array($tax)){throw new \InvalidArgumentException('Invalid normalized tax.');}$amount=self::amount($tax['amount']??null);if($amount===null){$add('line_taxes_missing',$path.'.taxes','O valoare a taxei lipsește din importul liniei.');}$taxes[]=['title'=>self::text($tax['title']??null),'amount'=>$amount];}}
            if(self::text($line['title']??null)===null){$add('line_description_missing',$path.'.description','Descrierea liniei lipsește din import.');}
            $add('line_unit_missing',$path.'.unit','Unitatea de măsură trebuie precizată pentru această linie.');
            $add('line_tax_treatment_missing',$path.'.taxTreatment','Alege explicit tratamentul TVA. Valoarea taxei din CMS nu determină singură cota sau scutirea.');
            $lines[]=['id'=>$id,'description'=>self::text($line['title']??null),'variant'=>self::text($line['variantTitle']??null),'sku'=>self::text($line['sku']??null),'quantity'=>$quantity,'currentQuantity'=>$current,'unit'=>null,'taxTreatment'=>null,'prices'=>$prices,'discounts'=>$discounts,'taxes'=>$taxes];
        }
        $amounts=$totals;
        foreach($lines as $line){array_push($amounts,...array_values($line['prices']),...($line['discounts']??[]),...array_column($line['taxes'],'amount'));}
        foreach($amounts as $amount){if($amount!==null&&$currency!==null&&($amount['currency']!==$currency||$amount['exponent']!==$totals['current']['exponent'])){$add('currency_mismatch','amounts','Sumele importate folosesc monede sau precizii diferite. Nu se face conversie implicită.');break;}}
        if(($totals['shipping']['minor']??'0')!=='0'){$add('shipping_tax_details_missing','shipping','Transportul comenzii există, dar importul nu conține o linie completă cu tratamentul TVA. Nu este tariful de transport al unui schimb.');}
        $add('document_dates_missing','document','Data emiterii și scadența trebuie stabilite; data comenzii nu este folosită automat.');
        return ['status'=>'INCOMPLETE','canIssue'=>false,'customer'=>$customer,'seller'=>$seller,'currency'=>$currency,'priceBasis'=>$basis,'totals'=>$totals,'lines'=>$lines,'issues'=>$issues];
    }
    private static function text(mixed $value): ?string
    {
        if($value===null){return null;}
        if(!is_string($value)||mb_strlen($value)>255||preg_match('/[\x00-\x1f\x7f]/',$value)){throw new \InvalidArgumentException('Invalid normalized text.');}
        return trim($value)===''?null:trim($value);
    }
    /** @return array{minor:string,decimal:string,currency:string,exponent:int}|null */
    private static function amount(mixed $value): ?array
    {
        if($value===null){return null;}
        if(!is_array($value)||!is_string($value['minor']??null)||!preg_match('/^-?(0|[1-9][0-9]{0,15})$/D',$value['minor'])||!is_string($value['currency']??null)||!is_int($value['exponent']??null)){throw new \InvalidArgumentException('Invalid normalized money.');}
        try{$money=new Money((int)$value['minor'],new Currency($value['currency'],$value['exponent']));}
        catch(\OverflowException){throw new \InvalidArgumentException('Invalid normalized money.');}
        return ['minor'=>(string)$money->minor,'decimal'=>$money->format(),'currency'=>$money->currency->code,'exponent'=>$money->currency->exponent];
    }
    /** @return list<array{minor:string,decimal:string,currency:string,exponent:int}>|null */
    private static function amounts(mixed $value): ?array
    {
        if($value===null){return null;}
        if(!is_array($value)||!array_is_list($value)){throw new \InvalidArgumentException('Invalid normalized amounts.');}
        $result=[];foreach($value as $item){$amount=self::amount($item);if($amount===null){throw new \InvalidArgumentException('Invalid normalized amount.');}$result[]=$amount;}return $result;
    }
}
