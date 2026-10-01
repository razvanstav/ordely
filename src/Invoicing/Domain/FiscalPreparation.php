<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Domain;

/** Explicit local fiscal facts, separate from immutable CMS money and provider configuration. */
final class FiscalPreparation
{
    private const ADDRESS=['street','streetExtra','city','region','postalCode','country'];
    /** @param array<string,mixed> $input
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed> */
    public static function normalize(#[\SensitiveParameter] array $input,array $snapshot): array
    {
        self::keys($input,['document','seller','customer','lineDefaults','lines']);$result=[];
        foreach(['document'=>['issuedOn','dueOn'],'seller'=>[...self::ADDRESS,'vatStatus'],'customer'=>[...self::ADDRESS,'name','type','taxId','vatStatus'],'lineDefaults'=>['unit','treatment','rate','reason']] as $group=>$fields){
            $values=$input[$group]??[];if(!is_array($values)){throw new \InvalidArgumentException('Invalid fiscal fields.');}self::keys($values,$fields);
            $result[$group]=[];foreach($fields as $field){$value=$values[$field]??'';if(!is_string($value)||mb_strlen($value)>255||preg_match('/[\x00-\x1f\x7f]/',$value)){throw new \InvalidArgumentException('Invalid fiscal text.');}$result[$group][$field]=trim($value);}
        }
        foreach(['issuedOn','dueOn'] as $field){$value=$result['document'][$field];if($value!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$value,$parts)||!checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))){throw new \InvalidArgumentException('Invalid document date.');}}
        if($result['document']['issuedOn']!==''&&$result['document']['dueOn']!==''&&$result['document']['dueOn']<$result['document']['issuedOn']){throw new \InvalidArgumentException('Invalid due date.');}
        self::choice($result['customer']['type']??'',['individual','company']);
        foreach(['seller','customer'] as $group){self::choice($result[$group]['vatStatus']??'',['registered','not_registered','not_applicable']);$country=$result[$group]['country']??'';if($country!==''&&!preg_match('/^[A-Z]{2}$/D',$country)){throw new \InvalidArgumentException('Country must be a two-letter code.');}}
        foreach([...self::ADDRESS,'name'] as $field){$imported=$field==='name'?$snapshot['customer']['name']:($snapshot['customer']['address'][$field]??null);$value=$result['customer'][$field]??'';if($imported!==null&&$value!==''&&$value!==$imported){throw new \InvalidArgumentException('Imported billing facts cannot be replaced.');}}
        $result['lineDefaults']=self::tax($result['lineDefaults']);
        $lines=$input['lines']??[];if(!is_array($lines)||!array_is_list($lines)||count($lines)>50){throw new \InvalidArgumentException('Invalid fiscal lines.');}
        $ids=array_column($snapshot['lines'],'id');$seen=[];$result['lines']=[];
        foreach($lines as $line){
            if(!is_array($line)){throw new \InvalidArgumentException('Invalid fiscal line.');}self::keys($line,['id','unit','treatment','rate','reason']);$id=$line['id']??null;
            if(!is_string($id)||!in_array($id,$ids,true)||isset($seen[$id])){throw new \InvalidArgumentException('Invalid fiscal line identity.');}$seen[$id]=true;
            $values=[];foreach(['unit','treatment','rate','reason'] as $field){$value=$line[$field]??'';if(!is_string($value)||mb_strlen($value)>255||preg_match('/[\x00-\x1f\x7f]/',$value)){throw new \InvalidArgumentException('Invalid fiscal text.');}$values[$field]=trim($value);}
            $result['lines'][]=['id'=>$id,...self::tax($values)];
        }
        // Client ordering does not create a different revision on an exact retry.
        usort($result['lines'],static fn(array $a,array $b):int=>strcmp($a['id'],$b['id']));
        return $result;
    }
    /** @param array<string,mixed> $snapshot
     * @return array<string,mixed> */
    public static function build(array $snapshot,bool $sourceChanged=false): array
    {
        $details=$snapshot['fiscalDetails']??[];$customer=$snapshot['customer'];$seller=$snapshot['seller'];$issues=[];
        foreach(self::ADDRESS as $field){$customer['address'][$field]??=$details['customer'][$field]??'';}
        $customer['name']??=$details['customer']['name']??'';
        foreach(['type','taxId','vatStatus'] as $field){$customer[$field]=$details['customer'][$field]??'';}
        $sellerDetails=$details['seller']??[];$document=$details['document']??[];$lines=[];
        $add=static function(string $path,string $message)use(&$issues):void{$issues[]=['code'=>'fiscal_details_missing','path'=>$path,'message'=>$message];};
        foreach(['customer'=>$customer['address'],'seller'=>$sellerDetails] as $group=>$address){foreach(['street'=>'Strada','city'=>'Localitatea','postalCode'=>'Codul poștal','country'=>'Țara'] as $field=>$label){if(($address[$field]??'')===''){$add($group.'.address.'.$field,$label.' trebuie completat(ă) pentru '.($group==='seller'?'emitent.':'client.'));}}}
        if($customer['name']===''){$add('customer.name','Completează numele destinatarului.');}
        if($customer['type']===''){$add('customer.type','Confirmă tipul clientului.');}
        if($customer['type']==='company'&&$customer['taxId']===''){$add('customer.taxId','Completează identificarea fiscală a firmei.');}
        if($customer['company']!==null&&$customer['type']==='individual'){$add('customer.type','Firma din CMS necesită verificarea tipului de client.');}
        foreach(['customer'=>$customer['vatStatus'],'seller'=>$sellerDetails['vatStatus']??''] as $group=>$status){if($status===''||($group==='seller'&&$status==='not_applicable')||($group==='customer'&&$customer['type']==='company'&&$status==='not_applicable')){$add($group.'.vatStatus','Confirmă statutul TVA al '.($group==='seller'?'emitentului.':'clientului.'));}}
        foreach(['issuedOn'=>'Data emiterii','dueOn'=>'Scadența'] as $field=>$label){if(($document[$field]??'')===''){$add('document.'.$field,$label.' trebuie aleasă explicit.');}}
        $overrides=array_column($details['lines']??[],null,'id');
        foreach($snapshot['lines'] as $index=>$line){
            // A line override replaces the entire common tax choice, never mixes two treatments.
            $common=$details['lineDefaults']??[];$override=$overrides[$line['id']]??[];
            $values=($override['treatment']??'')!==''||($override['rate']??'')!==''||($override['reason']??'')!==''?$override:$common;
            $line['unit']=($override['unit']??'')!==''?$override['unit']:($common['unit']??'');$line['taxTreatment']=$values['treatment']??'';$line['taxRate']=$values['rate']??'';$line['taxReason']=$values['reason']??'';
            if($line['unit']===''){$add('lines.'.$index.'.unit','Alege unitatea de măsură.');}
            if($line['taxTreatment']===''||($line['taxTreatment']==='standard'&&$line['taxRate']==='')||($line['taxTreatment']!=='standard'&&$line['taxReason']==='')){$add('lines.'.$index.'.taxTreatment','Alege tratamentul TVA și cota sau motivul aplicabil.');}
            $lines[]=$line;
        }
        $resolved=['billing_address_missing','customer_address_missing','customer_name_missing','customer_type_unconfirmed','customer_tax_id_missing','seller_details_missing','document_dates_missing','line_unit_missing','line_tax_treatment_missing'];
        foreach($snapshot['issues'] as $issue){if(!in_array($issue['code'],$resolved,true)){$issues[]=$issue;}}
        if($sourceChanged){$issues[]=['code'=>'preparation_source_changed','path'=>'source','message'=>'Actualizează ciorna din CMS și revalidează completările.'];}
        return ['status'=>$issues===[]?'READY_FOR_MAPPING':'INCOMPLETE','readyForMapping'=>$issues===[],'canIssue'=>false,'document'=>$document,'customer'=>$customer,'seller'=>$seller===null?null:[...$seller,'address'=>array_intersect_key($sellerDetails,array_flip(self::ADDRESS)),'vatStatus'=>$sellerDetails['vatStatus']??''],'lines'=>$lines,'issues'=>$issues];
    }
    /** @param array<string,mixed> $values
     * @param list<string> $allowed */
    private static function keys(array $values,array $allowed): void {if(array_diff(array_keys($values),$allowed)!==[]){throw new \InvalidArgumentException('Unknown fiscal field.');}}
    /** @param list<string> $allowed */
    private static function choice(string $value,array $allowed): void {if($value!==''&&!in_array($value,$allowed,true)){throw new \InvalidArgumentException('Invalid fiscal choice.');}}
    /** @param array<string,string> $values
     * @return array<string,string> */
    private static function tax(array $values): array
    {
        self::choice($values['treatment'],['standard','exempt','outside_scope']);$rate=$values['rate'];
        if($rate!==''&&(!preg_match('/^(0|[1-9][0-9]?|100)(\.[0-9]{1,4})?$/D',$rate)||($rate!=='100'&&str_starts_with($rate,'100.')&&trim(substr($rate,4),'0')!==''))){throw new \InvalidArgumentException('Invalid decimal tax rate.');}
        if($values['treatment']!==''&&$values['treatment']!=='standard'&&$rate!==''){throw new \InvalidArgumentException('Tax rate does not apply to this treatment.');}
        return $values;
    }
}
