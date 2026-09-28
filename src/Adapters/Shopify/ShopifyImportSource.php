<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ImportSource,ProviderFailure};
use Ordely\Core\Data\ImportPage;

final readonly class ShopifyImportSource implements ImportSource
{
    public function __construct(private GraphqlReader $reader) {}
    public function page(ConnectionContext $context,string $collection,?string $parent,?string $cursor,?string $since): ImportPage
    {
        $variables=['after'=>$cursor];
        if ($collection==='orders') { $variables['query']=$since===null?null:"updated_at:>='".ImportNormalizer::date($since)."'"; }
        if ($parent!==null) { $variables['id']=ImportNormalizer::id($parent,match($collection) {'order'=>'Order','variants'=>'Product','inventory'=>'ProductVariant',default=>throw new \InvalidArgumentException('Invalid parent.')}); }
        $data=$this->reader->read($context,$collection,$variables);$records=[];$children=[];
        switch ($collection) {
            case 'orders':
                $connection=$data['orders']??null;
                foreach(ImportNormalizer::nodes($connection['nodes']??null) as $node) { $children[]=['collection'=>'order','parent'=>ImportNormalizer::id($node['id']??null,'Order')]; }
                break;
            case 'order':
                $node=$data['order']??null;
                if (!is_array($node)) { throw new ProviderFailure(ErrorCategory::NotFound); }
                $records[] = ImportNormalizer::order($node);$connection=$node['lineItems']??null;break;
            case 'products':
                $connection=$data['products']??null;
                foreach(ImportNormalizer::nodes($connection['nodes']??null) as $node) { $record=ImportNormalizer::product($node);$records[]=$record;$children[]=['collection'=>'variants','parent'=>$record['externalId']]; }
                break;
            case 'variants':
                $node=$data['product']??null;if (!is_array($node)) { throw new ProviderFailure(ErrorCategory::Conflict); }
                $connection=$node['variants']??null;$currency=ImportNormalizer::text($data['shop']['currencyCode']??null,3);
                foreach(ImportNormalizer::nodes($connection['nodes']??null) as $variant) { $record=ImportNormalizer::variant($variant,(string)$parent,$currency);$records[]=$record;$children[]=['collection'=>'inventory','parent'=>$record['externalId']]; }break;
            case 'inventory':
                $node=$data['productVariant']??null;if (!is_array($node)) { throw new ProviderFailure(ErrorCategory::Conflict); }
                $connection=$node['inventoryItem']['inventoryLevels']??null;$tracked=ImportNormalizer::boolean($node['inventoryItem']['tracked']??null);
                foreach(ImportNormalizer::nodes($connection['nodes']??null) as $level) { $records[]=ImportNormalizer::inventory($level,(string)$parent,$tracked); }break;
            default: throw new \InvalidArgumentException('Invalid collection.');
        }
        $more=ImportNormalizer::boolean($connection['pageInfo']['hasNextPage']??null);
        $next=$more?ImportNormalizer::text($connection['pageInfo']['endCursor']??null,2048):null;
        if ($more && ($next==='' || $next===$cursor)) { throw new ProviderFailure(ErrorCategory::Validation); }
        return new ImportPage($records,$next,$children);
    }
}
