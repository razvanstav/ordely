<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Domain;
use Ordely\Core\Data\{CompanyDetails,FiscalCustomer,FiscalLine,InvoiceDetails,InvoiceDraft};
use Ordely\Core\Value\{Address,Currency,EmailAddress,ExternalId,InvoiceTax,Money,OperationKey,PriceBasis,Quantity};

/** Reconcile the frozen CMS source before it can become a provider-neutral document. */
final class InvoiceAssembly
{
    /** @param array<string,mixed> $snapshot
     * @param array<string,mixed> $fiscal
     * @return array<string,mixed> */
    public static function check(array $snapshot,array $fiscal): array {return self::evaluate($snapshot,$fiscal,new OperationKey('preparation-check'))['report'];}
    /** @param array<string,mixed> $snapshot
     * @param array<string,mixed> $fiscal */
    public static function draft(array $snapshot,array $fiscal,OperationKey $key): InvoiceDraft
    {
        $result=self::evaluate($snapshot,$fiscal,$key);if($result['draft']===null){throw new \InvalidArgumentException('Invoice preparation is not reconciled.');}return $result['draft'];
    }
    /** @param array<string,mixed> $snapshot
     * @param array<string,mixed> $fiscal
     * @return array{report:array<string,mixed>,draft:?InvoiceDraft} */
    private static function evaluate(array $snapshot,array $fiscal,OperationKey $key): array
    {
        $issues=[];$lines=[];$draft=null;$totals=null;
        $add=static function(string $code,string $path,string $message)use(&$issues):void{$issues[]=['code'=>$code,'path'=>$path,'message'=>$message];};
        try{
            $total=self::money($snapshot['totals']['current']??null);$currency=$total->currency;
            if($currency->code!=='RON'||$currency->exponent!==2){throw new \InvalidArgumentException('Unsupported document currency.');}
            $basis=PriceBasis::from($snapshot['priceBasis']??'');$gross=$tax=$discount=$net=new Money(0,$currency);
            foreach($snapshot['lines'] as $index=>$source){
                $path='lines.'.$index;$before=count($issues);
                try{
                    $unit=self::money($source['prices']['originalUnitPrice']??null,$currency);$original=self::money($source['prices']['originalTotal']??null,$currency);$subtotal=self::money($source['prices']['lineDiscountedTotal']??null,$currency);$quantity=new Quantity($source['quantity']);
                    $lineDiscount=new Money(0,$currency);foreach($source['discounts']??[] as $allocation){$lineDiscount=$lineDiscount->plus(self::money($allocation,$currency));}
                    $lineTax=new Money(0,$currency);foreach($source['taxes'] as $component){$lineTax=$lineTax->plus(self::money($component['amount']??null,$currency));}
                    if($unit->times($quantity->value)->minor!==$original->minor){$add('line_original_mismatch',$path.'.prices','Prețul unitar × cantitate diferă de totalul inițial al liniei.');}
                    if($original->minus($lineDiscount)->minor!==$subtotal->minor){$add('line_discount_mismatch',$path.'.discounts','Reducerile alocate diferă de reducerea din totalul liniei.');}
                    $lineNet=$basis===PriceBasis::TaxInclusive?$subtotal->minus($lineTax):$subtotal;
                    if($lineNet->minor<0){throw new \InvalidArgumentException('Tax exceeds line amount.');}
                    $gross=$gross->plus($basis===PriceBasis::TaxInclusive?$subtotal:$subtotal->plus($lineTax));$tax=$tax->plus($lineTax);$discount=$discount->plus($lineDiscount);$net=$net->plus($lineNet);
                    if(count($source['taxes'])>1){$add('tax_components_unsupported',$path.'.taxes','Linia conține mai multe componente fiscale; trebuie mapate separat.');}
                    $choice=$fiscal['lines'][$index];
                    if($choice['unit']!==''&&$choice['taxTreatment']!==''&&($choice['taxTreatment']==='standard'?$choice['taxRate']!=='':$choice['taxReason']!=='')){
                        try{
                            $treatment=new InvoiceTax($choice['taxTreatment'],$choice['taxTreatment']==='standard'?$choice['taxRate']:null,$choice['taxReason']!==''?$choice['taxReason']:null);
                            if($treatment->on($subtotal,$basis)->minor!==$lineTax->minor){$add('line_tax_mismatch',$path.'.taxes','TVA-ul importat diferă de calculul cotei alese pe suma după reduceri.');}
                            if(($fiscal['seller']['vatStatus']??'')==='not_registered'&&$lineTax->minor!==0){$add('seller_tax_mismatch','seller.vatStatus','Emitentul neînregistrat TVA are taxe nenule în comandă.');}
                            if(count($issues)===$before){$lines[]=new FiscalLine(new ExternalId($source['id']),$source['description']??'',$quantity,$choice['unit'],$unit,$lineDiscount,$lineTax,$basis,$treatment);}
                        }catch(\InvalidArgumentException|\OverflowException){$add('line_fiscal_invalid',$path.'.taxTreatment','Tratamentul fiscal sau unitatea liniei nu poate fi pregătit(ă).');}
                    }
                }catch(\InvalidArgumentException|\OverflowException){$add('line_money_invalid',$path,'Sumele, moneda sau cantitatea liniei sunt incomplete ori depășesc limitele.');}
            }
            $shipping=self::money($snapshot['totals']['shipping']??null,$currency);
            if($shipping->minor!==0){$add('shipping_mapping_pending','shipping','Transportul necesită o linie fiscală completă din sursa CMS înainte de mapare.');}
            foreach(['tax'=>$tax,'discount'=>$discount] as $name=>$calculated){if(self::money($snapshot['totals'][$name]??null,$currency)->minor!==$calculated->minor){$add('order_'.$name.'_mismatch','totals.'.$name,'Totalul '.($name==='tax'?'taxelor':'reducerilor').' diferă de suma liniilor.');}}
            if($gross->plus($shipping)->minor!==$total->minor){$add('order_total_mismatch','totals.current','Totalul comenzii diferă de produse, taxe și transport. Nu se ajustează automat.');}
            $totals=['net'=>$net,'tax'=>$tax,'discount'=>$discount,'gross'=>$gross];
            if($issues===[]&&$fiscal['readyForMapping']===true&&count($lines)===count($snapshot['lines'])){
                try{
                    $seller=$fiscal['seller'];$customer=$fiscal['customer'];
                    $recipient=new FiscalCustomer($customer['name'],self::address($customer['name'],$customer['address']),$customer['type'],$customer['taxId']!==''?$customer['taxId']:null,$customer['vatStatus'],$customer['address']['region']??'',$customer['email']!==null?new EmailAddress($customer['email']):null);
                    $details=new InvoiceDetails($fiscal['document']['issuedOn'],$fiscal['document']['dueOn'],$seller['vatStatus'],$basis,$snapshot['source']['reference']??'');
                    $draft=new InvoiceDraft(new CompanyDetails($seller['companyName'],$seller['companyId'],self::address($seller['companyName'],$seller['address'])),$recipient,$lines,$total,new ExternalId($seller['series']),$key,$details);
                }catch(\InvalidArgumentException|\OverflowException){$add('document_fiscal_invalid','document','Identitatea fiscală, adresele sau datele documentului nu respectă contractul de facturare.');}
            }
        }catch(\InvalidArgumentException|\OverflowException|\ValueError){$add('document_money_invalid','totals','Moneda, baza prețului sau totalurile documentului nu pot fi reconciliate.');}
        $amounts=null;if($totals!==null){$amounts=[];foreach($totals as $name=>$money){$amounts[$name]=['minor'=>(string)$money->minor,'decimal'=>$money->format(),'currency'=>$money->currency->code];}}
        return ['report'=>['status'=>$issues!==[]?'MISMATCH':($draft!==null?'RECONCILED':'INCOMPLETE'),'readyForProvider'=>$draft!==null,'canIssue'=>false,'totals'=>$amounts,'issues'=>$issues],'draft'=>$draft];
    }
    private static function money(mixed $data,?Currency $expected=null): Money
    {
        if(!is_array($data)||!is_string($data['minor']??null)||!preg_match('/^(0|[1-9][0-9]{0,15})$/D',$data['minor'])||!is_string($data['currency']??null)||!is_int($data['exponent']??null)){throw new \InvalidArgumentException('Invalid imported money.');}
        $value=new Money((int)$data['minor'],new Currency($data['currency'],$data['exponent']));if($expected!==null&&!$value->currency->same($expected)){throw new \InvalidArgumentException('Currency mismatch.');}return $value;
    }
    /** @param array<string,mixed> $data */
    private static function address(string $name,array $data): Address {return new Address($name,$data['country'],$data['city'],implode(', ',array_filter([$data['street'],$data['streetExtra']??''])),$data['postalCode']);}
}
