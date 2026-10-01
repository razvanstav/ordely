<?php
declare(strict_types=1);
namespace Ordely\Adapters\Oblio;
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Core\Data\{FiscalCustomer,FiscalLine,InvoiceConfiguration,InvoiceDraft};
use Ordely\Core\Value\{InvoiceTax,OperationKey,PriceBasis};

/** Pure request mapping. This cannot authenticate, allocate a number, or issue a document. */
final class OblioInvoiceMapper
{
    /** Configuration must be freshly verified by the future durable issuance operation.
     * @return array<string,mixed> */
    public static function map(InvoiceDraft $draft,InvoiceConfiguration $configuration,OperationKey $key): array
    {
        $details=$draft->details;$customer=$draft->customer;
        if($details===null||!$customer instanceof FiscalCustomer||$draft->total->currency->code!=='RON'||$draft->total->currency->exponent!==2){throw new ProviderFailure(ErrorCategory::Unsupported);}
        if($configuration->companyId!==$draft->seller->taxId||!in_array($draft->series->value,array_column($configuration->series,'name'),true)){throw new ProviderFailure(ErrorCategory::Validation);}
        $companies=array_filter($configuration->companies,static fn(array $company):bool=>$company['id']===$draft->seller->taxId&&$company['name']===$draft->seller->legalName);
        if(count($companies)!==1){throw new ProviderFailure(ErrorCategory::Validation);}
        $products=[];
        foreach($draft->lines as $line){
            if(!$line instanceof FiscalLine||$line->taxTreatment->treatment!=='standard'){throw new ProviderFailure(ErrorCategory::Unsupported);}
            $names=[];foreach($configuration->taxRates as $rate){if((new InvoiceTax('standard',$rate['percent']))->scaledRate===$line->taxTreatment->scaledRate){$names[]=$rate['name'];}}
            if(count($names)!==1){throw new ProviderFailure(ErrorCategory::Validation);}
            $products[]=['name'=>$line->description,'code'=>$line->id->value,'price'=>$line->unitPrice->format(),'currency'=>$line->unitPrice->currency->code,'measuringUnit'=>$line->unit,'vatName'=>$names[0],'vatPercentage'=>$line->taxTreatment->rate,'vatIncluded'=>$line->basis===PriceBasis::TaxInclusive?1:0,'quantity'=>$line->quantity->value,'save'=>0];
            if($line->discount->minor!==0){$products[]=['name'=>'Reducere','discountType'=>'valoric','discount'=>$line->discount->format(),'discountAllAbove'=>0];}
        }
        $billing=$customer->billing;
        $client=['name'=>$customer->name,'address'=>$billing->line.', '.$billing->postalCode,'state'=>$customer->region,'city'=>$billing->city,'country'=>$billing->country,'vatPayer'=>$customer->vatStatus==='registered'?1:0,'save'=>0,'autocomplete'=>0];
        if($customer->type==='company'){$client['cif']=$customer->taxId;}
        // Email is neither needed for fiscal mapping nor sent while delivery remains disabled.
        return ['cif'=>$draft->seller->taxId,'client'=>$client,'issueDate'=>$details->issuedOn,'dueDate'=>$details->dueOn,'seriesName'=>$draft->series->value,'disableAutoSeries'=>0,'language'=>'RO','precision'=>2,'currency'=>'RON','products'=>$products,'orderNumber'=>$details->orderReference,'idempotencyKey'=>$key->value,'useStock'=>0,'sendEmail'=>0,'spvExtern'=>0];
    }
}
