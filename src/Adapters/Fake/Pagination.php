<?php
declare(strict_types=1);
namespace Ordely\Adapters\Fake;
use Ordely\Core\Contracts\{ConnectionContext, Page, ProviderFailure, ErrorCategory};
use Ordely\Core\Value\PageRequest;
final class Pagination
{
    /** @template T
     * @param list<T> $items
     * @return Page<T> */
    public static function slice(ConnectionContext $context, string $queryKey, array $items, PageRequest $request): Page
    {
        $scope=substr(hash('sha256',$context->scope().':'.$queryKey),0,24); $offset=0;
        if ($request->cursor!==null) {
            if (!preg_match('/^'.preg_quote($scope,'/').':([0-9]{1,9})$/D',$request->cursor,$match)) { throw new ProviderFailure(ErrorCategory::Validation); }
            $offset=(int)$match[1];
            if ($offset>count($items)) { throw new ProviderFailure(ErrorCategory::Validation); }
        }
        $next=$offset+$request->limit;
        return new Page(array_slice($items,$offset,$request->limit),$next<count($items)?$scope.':'.$next:null);
    }
}
