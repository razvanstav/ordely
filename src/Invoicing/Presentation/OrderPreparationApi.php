<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Presentation;

use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Presentation\SessionGuard;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Invoicing\Infrastructure\{InvoiceProfiles,OrderPreparations};
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class OrderPreparationApi
{
    public function __construct(private Sql $db) {}
    public function handle(Request $request,string $id): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);
        $cipher=new SecretCipher(KeyRing::fromEnvironment());
        // Reading the local profile cannot instantiate or call any external provider.
        $profiles=new InvoiceProfiles($this->db,new ProviderRegistry(),$cipher);
        return new JsonResponse((new OrderPreparations($this->db,new OrderCipher($cipher),$profiles))->get($actor,$request->query->getString('storeId'),$id));
    }
}
